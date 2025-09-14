<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

const MAIL_TO = 'darsorokinaa@gmail.com';
const MAIL_TO_NAME = 'Получатель';
const MAIL_FROM = 'no-reply@zayvka.ru';
const MAIL_FROM_NAME = 'Форма рекламации';

$tempDir = __DIR__ . '/tmp';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

function safe_filename(string $name): string {
    $name = preg_replace('~[^\w\s\.-]+~u', '_', $name);
    return trim($name) ?: ('file_' . uniqid());
}

$mail = new PHPMailer(true);
try {
    $mail->CharSet = 'UTF-8';
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress(MAIL_TO, MAIL_TO_NAME);
    $mail->isHTML(true);
    $mail->Subject = 'Бланк рекламации (фото)';
    $mail->Body    = '<p>Фотографии рекламации приложены к письму.</p>';
    $mail->AltBody = 'Фотографии рекламации приложены к письму.';

    if (!empty($_FILES['blank_photos'])) {
        foreach ($_FILES['blank_photos']['tmp_name'] as $i => $tmpPath) {
            if ($_FILES['blank_photos']['error'][$i] === UPLOAD_ERR_OK) {
                $origName = safe_filename($_FILES['blank_photos']['name'][$i]);
                $targetPath = $tempDir . '/' . uniqid('upload_', true) . '_' . $origName;

                if (move_uploaded_file($tmpPath, $targetPath)) {
                    $mail->addAttachment($targetPath, $origName);
                }
            }
        }
    }

    if (!$mail->send()) {
        exit('Ошибка отправки письма');
    }

    header('Location: ./success.html');
    exit;
} catch (Exception $e) {
    exit('Ошибка: ' . $e->getMessage());
}
