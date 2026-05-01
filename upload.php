<?php
require_once 'functions.php';

$message = '';
$uploadedFile = '';
$uploadError = '';

// Обработка загрузки по URL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url_import'])) {
    $remoteUrl = trim($_POST['remote_url']);
    if (filter_var($remoteUrl, FILTER_VALIDATE_URL)) {
        $filename = basename(parse_url($remoteUrl, PHP_URL_PATH));
        if (empty($filename) || !preg_match('/\.m3u8?$/i', $filename)) {
            $filename = 'imported_' . time() . '.m3u8';
        }
        $destination = PLAYLISTS_DIR . $filename;
        
        $ch = curl_init($remoteUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $content) {
            if (file_put_contents($destination, $content)) {
                $uploadedFile = $filename;
                $message = 'Плейлист успешно загружен по ссылке';
            } else {
                $uploadError = 'Ошибка сохранения файла';
            }
        } else {
            $uploadError = 'Не удалось загрузить файл по ссылке (HTTP ' . $httpCode . ')';
        }
    } else {
        $uploadError = 'Некорректная ссылка';
    }
}

// Обработка загрузки файла с компьютера
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['playlist'])) {
    $file = $_FILES['playlist'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (in_array(strtolower($extension), ['m3u', 'm3u8'])) {
            $destination = PLAYLISTS_DIR . $file['name'];
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $uploadedFile = $file['name'];
                $message = 'Плейлист успешно загружен';
            } else {
                $uploadError = 'Ошибка при сохранении файла';
            }
        } else {
            $uploadError = 'Пожалуйста, загрузите файл с расширением .m3u или .m3u8';
        }
    } else {
        $uploadError = 'Ошибка при загрузке файла';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#3498db">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no">
    <title>Загрузка плейлиста</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Загрузка плейлиста</h1>
        
        <?php if ($message): ?>
            <div class="message success">
                <?php echo htmlspecialchars($message); ?>
                <?php if ($uploadedFile): ?>
                    <br>
                    <a href="index.php?switch=<?php echo urlencode($uploadedFile); ?>" class="btn btn-small btn-primary">
                        Переключиться на этот плейлист
                    </a>
                <?php endif; ?>
            </div>
        <?php elseif ($uploadError): ?>
            <div class="message error">
                <?php echo htmlspecialchars($uploadError); ?>
            </div>
        <?php endif; ?>
        
        <div class="upload-section">
            <h3>Загрузка с компьютера</h3>
            <form method="POST" enctype="multipart/form-data" class="upload-form">
                <div class="form-group">
                    <label for="playlist">Выберите M3U/M3U8 файл:</label>
                    <input type="file" id="playlist" name="playlist" accept=".m3u,.m3u8" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Загрузить файл</button>
                </div>
            </form>
        </div>
        
        <div class="upload-section">
            <h3>Загрузка по ссылке</h3>
            <form method="POST" class="upload-form">
                <div class="form-group">
                    <label for="remote_url">Ссылка на плейлист (m3u/m3u8):</label>
                    <input type="url" id="remote_url" name="remote_url" 
                           placeholder="https://example.com/playlist.m3u8" required>
                </div>
                <div class="form-actions">
                    <button type="submit" name="url_import" class="btn btn-primary">Загрузить по ссылке</button>
                </div>
            </form>
        </div>
        
        <div class="info">
            <h3>Существующие плейлисты:</h3>
            <ul>
                <?php
                $files = scandir(PLAYLISTS_DIR);
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                $baseUrl .= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && preg_match('/\.m3u8?$/i', $file)) {
                        $fileUrl = $baseUrl . 'playlists/' . urlencode($file);
                        echo '<li>' . htmlspecialchars($file) . 
                             ' <a href="index.php?switch=' . urlencode($file) . '" class="btn btn-small btn-primary">Переключиться</a>' .
                             ' <a href="' . $fileUrl . '" class="btn btn-small btn-secondary" target="_blank">Ссылка</a>' .
                             ' <button onclick="copyToClipboard(\'' . $fileUrl . '\')" class="btn btn-small btn-copy">Копировать ссылку</button>' .
                             '</li>';
                    }
                }
                ?>
            </ul>
        </div>
    </div>
    
    <script>
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Ссылка скопирована в буфер обмена');
            }).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            alert('Ссылка скопирована в буфер обмена');
        } catch (err) {
            alert('Не удалось скопировать ссылку. Скопируйте вручную: ' + text);
        }
        document.body.removeChild(textarea);
    }
    </script>
</body>
</html>