<?php
ob_start();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require './vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

// Ваша локальная timezone
date_default_timezone_set('Europe/Moscow');

// Временная директория
$tempDir = __DIR__ . '/tmp/';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// Директория для загрузки файлов
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Загружаем шаблон Excel
$inputFileName = __DIR__ . '/blank.xlsx';
$reader = new Xlsx();
$spreadsheet = $reader->load($inputFileName);
$worksheet = $spreadsheet->getActiveSheet();

// === Вспомогательные функции ===
function getPost(string $key): string {
    $val = $_POST[$key] ?? '';
    return is_array($val) ? implode(', ', $val) : trim($val);
}

function add_main_info($worksheet, string $cell, string $name): void {
    $worksheet->getCell($cell)->setValueExplicit(getPost($name));
}

function add_table_values($worksheet): void {
    $articles = $_POST['article']        ?? [];
    $titles   = $_POST['title']          ?? [];
    $docs     = $_POST['doc']            ?? [];
    $facts    = $_POST['fact']           ?? [];
    $shortages= $_POST['shortage']       ?? [];
    $surpluses= $_POST['surplus']        ?? [];
    $defects  = $_POST['defect']         ?? [];
    $comments = $_POST['comment']        ?? [];
    $declined = $_POST['declined']       ?? [];
    $presale  = $_POST['presale']        ?? [];
    $exchange = $_POST['exchange']       ?? [];
    $warranty = $_POST['warranty']       ?? [];
    $paid     = $_POST['paid_repair']    ?? [];

    $count = max(
        count($articles), count($titles), count($docs),
        count($facts), count($shortages), count($surpluses),
        count($defects), count($comments),
        count($declined), count($presale),
        count($exchange), count($warranty), count($paid)
    );

    for ($i = 0; $i < $count; $i++) {
        $row = 17 + $i;

        $worksheet->setCellValue("C$row", $titles[$i]    ?? '');  // Наименование
        $worksheet->setCellValue("F$row", $articles[$i]  ?? '');  // Артикул
        $worksheet->setCellValue("G$row", $docs[$i]      ?? '');  // Док.
        $worksheet->setCellValue("H$row", $facts[$i]     ?? '');  // Факт.
        $worksheet->setCellValue("I$row", $shortages[$i] ?? '');  // Недостача
        $worksheet->setCellValue("J$row", $surpluses[$i] ?? '');  // Излишек
        $worksheet->setCellValue("K$row", $defects[$i]   ?? '');  // Брак
        $worksheet->setCellValue("Q$row", $comments[$i]  ?? '');  // Комментарий

        if (!empty($presale[$i]))   $worksheet->setCellValue("L$row", '+');
        if (!empty($declined[$i]))  $worksheet->setCellValue("M$row", '+');
        if (!empty($exchange[$i]))  $worksheet->setCellValue("N$row", '+');
        if (!empty($warranty[$i]))  $worksheet->setCellValue("O$row", '+');
        if (!empty($paid[$i]))      $worksheet->setCellValue("P$row", '+');
    }
}

// === Заполняем Excel ===
add_main_info($worksheet, 'D4',  'customer_name');
add_main_info($worksheet, 'D5',  'customer_contact');
add_main_info($worksheet, 'D6',  'delivery_address');
add_main_info($worksheet, 'D7',  'supplier');
add_main_info($worksheet, 'D8',  'manager');
add_main_info($worksheet, 'D9',  'delivery_date');
add_main_info($worksheet, 'D10', 'invoice');

add_table_values($worksheet);

// Сохраняем Excel во временный файл
$tempXlsx = $tempDir . 'blank_written_' . uniqid() . '.xlsx';
$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save($tempXlsx);

// ==== Обработка загруженных файлов ====
$savedFiles = [];

/**
 * Переместить загруженные файлы во временное хранилище и вернуть их пути
 */
function processUploads(string $fieldName, string $prefix, array &$savedFiles, string $uploadDir): void {
    if (!empty($_FILES[$fieldName]) && is_array($_FILES[$fieldName]['tmp_name'])) {
        foreach ($_FILES[$fieldName]['tmp_name'] as $key => $tmpName) {
            if ($_FILES[$fieldName]['error'][$key] === UPLOAD_ERR_OK) {
                $origName = $_FILES[$fieldName]['name'][$key] ?? ('file_' . $key);
                $ext = pathinfo($origName, PATHINFO_EXTENSION);
                if ($ext === '') $ext = 'bin';
                $newName = $prefix . '_' . uniqid('', true) . '.' . $ext;
                $filePath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newName;

                if (is_uploaded_file($tmpName) && move_uploaded_file($tmpName, $filePath)) {
                    $savedFiles[] = [
                        'path' => $filePath,
                        'name' => $prefix . '_' . ($key + 1) . '.' . $ext
                    ];
                }
            }
        }
    }
}

processUploads('blank_photos',      'Бланк',        $savedFiles, $uploadDir);
processUploads('additional_photos', 'Дополнительно',$savedFiles, $uploadDir);

// === Отправка письма ===
$mail = new PHPMailer(true);

// Единая очистка временных файлов
$cleanup = function () use ($tempXlsx, &$savedFiles) {
    if (!empty($tempXlsx) && file_exists($tempXlsx)) {
        @unlink($tempXlsx);
    }
    if (!empty($savedFiles)) {
        foreach ($savedFiles as $file) {
            if (!empty($file['path']) && file_exists($file['path'])) {
                @unlink($file['path']);
            }
        }
    }
};

try {
    $mail->CharSet = 'UTF-8';
    $mail->setFrom('no-reply@zayvka.ru', 'Форма рекламации');
    $mail->addAddress('darsorokinaa@gmail.com');
    $mail->isHTML(true);
    $mail->Subject = "Рекламация от " . getPost('customer_name') . " (". getPost('manager') . ")";

    // Тело письма
    $mail->Body = "
        <h3>Новая заявка на рекламацию</h3>
    ";

    // Вложение Excel
    $mail->addAttachment($tempXlsx, 'Бланк_рекламации_' . date('Y-m-d') . '.xlsx');

    // Вложения изображений
    foreach ($savedFiles as $file) {
        $mail->addAttachment($file['path'], $file['name']);
    }

    $mail->send();

    // Очистка и редирект
    $cleanup();
    if (function_exists('ob_end_clean')) { @ob_end_clean(); }
    header('Location: ./success.html');
    exit;

} catch (Exception $e) {
    $cleanup();
    error_log('Ошибка отправки письма: ' . $e->getMessage());
    die('Ошибка при отправке письма: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
} catch (Throwable $e) {
    $cleanup();
    error_log('Критическая ошибка: ' . $e->getMessage());
    die('Критическая ошибка: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
