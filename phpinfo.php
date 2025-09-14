<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";

    if (!empty($_FILES['blank_photos'])) {
        $file = $_FILES['blank_photos'];
        $target = __DIR__ . '/tmp/' . basename($file['name']);

        if (move_uploaded_file($file['tmp_name'], $target)) {
            echo "✅ Файл успешно сохранён: $target";
        } else {
            echo "❌ Ошибка при сохранении файла";
        }
    }
}
?>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="blank_photos">
    <button type="submit">Загрузить</button>
</form>
