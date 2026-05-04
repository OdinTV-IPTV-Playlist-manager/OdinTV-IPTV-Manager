<?php
require_once 'functions.php';

// Инициализация языка
$lang = getCurrentLanguage();
$t = loadTranslations();
$availableLanguages = getAvailableLanguages();

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
                $message = $t['playlist_uploaded_url_success'] ?? 'Плейлист успешно загружен по ссылке';
            } else {
                $uploadError = $t['file_save_error'] ?? 'Ошибка сохранения файла';
            }
        } else {
            $uploadError = sprintf($t['url_download_failed'] ?? 'Не удалось загрузить файл по ссылке (HTTP %s)', $httpCode);
        }
    } else {
        $uploadError = $t['invalid_url'] ?? 'Некорректная ссылка';
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
                $message = $t['playlist_uploaded_success'] ?? 'Плейлист успешно загружен';
            } else {
                $uploadError = $t['file_save_error'] ?? 'Ошибка при сохранении файла';
            }
        } else {
            $uploadError = $t['invalid_file_extension'] ?? 'Пожалуйста, загрузите файл с расширением .m3u или .m3u8';
        }
    } else {
        $uploadError = $t['file_upload_error'] ?? 'Ошибка при загрузке файла';
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#3498db">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no">
    <title><?php echo htmlspecialchars($t['upload_new'] ?? 'Загрузка плейлиста'); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header-actions">
            <a href="index.php" class="btn btn-secondary"><?php echo htmlspecialchars($t['back_to_list'] ?? 'Назад к списку'); ?></a>
            
            <?php if (count($availableLanguages) > 1): ?>
            <div class="language-selector">
                <label for="language-select"><?php echo htmlspecialchars($t['language'] ?? 'Язык'); ?>:</label>
                <select id="language-select" onchange="changeLanguage(this.value)">
                    <?php foreach ($availableLanguages as $code => $langInfo): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>" 
                            <?php echo $code === $lang ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($langInfo['flag']); ?> <?php echo htmlspecialchars($langInfo['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        
        <h1><?php echo htmlspecialchars($t['upload_new'] ?? 'Загрузка плейлиста'); ?></h1>
        
        <?php if ($message): ?>
            <div class="message success">
                <?php echo htmlspecialchars($message); ?>
                <?php if ($uploadedFile): ?>
                    <br>
                    <a href="index.php?switch=<?php echo urlencode($uploadedFile); ?>" class="btn btn-small btn-primary">
                        <?php echo htmlspecialchars($t['switch_to_playlist'] ?? 'Переключиться на этот плейлист'); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php elseif ($uploadError): ?>
            <div class="message error">
                <?php echo htmlspecialchars($uploadError); ?>
            </div>
        <?php endif; ?>
        
        <div class="upload-section">
            <h3><?php echo htmlspecialchars($t['upload_from_computer'] ?? 'Загрузка с компьютера'); ?></h3>
            <form method="POST" enctype="multipart/form-data" class="upload-form">
                <div class="form-group">
                    <label for="playlist"><?php echo htmlspecialchars($t['select_m3u_file'] ?? 'Выберите M3U/M3U8 файл:'); ?></label>
                    <input type="file" id="playlist" name="playlist" accept=".m3u,.m3u8" required 
                           data-choose-file="<?php echo htmlspecialchars($t['choose_file'] ?? 'Выберите файл'); ?>"
                           data-no-file-chosen="<?php echo htmlspecialchars($t['no_file_chosen'] ?? 'Файл не выбран'); ?>"
                           data-text="<?php echo htmlspecialchars($t['no_file_chosen'] ?? 'Файл не выбран'); ?>">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($t['upload_file_btn'] ?? 'Загрузить файл'); ?></button>
                </div>
            </form>
        </div>
        
        <div class="upload-section">
            <h3><?php echo htmlspecialchars($t['upload_from_url'] ?? 'Загрузка по ссылке'); ?></h3>
            <form method="POST" class="upload-form">
                <div class="form-group">
                    <label for="remote_url"><?php echo htmlspecialchars($t['playlist_url_label'] ?? 'Ссылка на плейлист (m3u/m3u8):'); ?></label>
                    <input type="url" id="remote_url" name="remote_url" 
                           placeholder="https://example.com/playlist.m3u8" required>
                </div>
                <div class="form-actions">
                    <button type="submit" name="url_import" class="btn btn-primary"><?php echo htmlspecialchars($t['upload_url_btn'] ?? 'Загрузить по ссылке'); ?></button>
                </div>
            </form>
        </div>
        
        <div class="info">
            <h3><?php echo htmlspecialchars($t['existing_playlists'] ?? 'Существующие плейлисты:'); ?></h3>
            <ul>
                <?php
                $files = scandir(PLAYLISTS_DIR);
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                $baseUrl .= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && preg_match('/\.m3u8?$/i', $file)) {
                        $fileUrl = $baseUrl . 'playlists/' . urlencode($file);
                        echo '<li>' . htmlspecialchars($file) . 
                             ' <a href="index.php?switch=' . urlencode($file) . '" class="btn btn-small btn-primary">' . htmlspecialchars($t['switch_to'] ?? 'Переключиться') . '</a>' .
                             ' <a href="' . $fileUrl . '" class="btn btn-small btn-secondary" target="_blank">' . htmlspecialchars($t['link'] ?? 'Ссылка') . '</a>' .
                             ' <button onclick="copyToClipboard(\'' . $fileUrl . '\')" class="btn btn-small btn-copy">' . htmlspecialchars($t['copy_link'] ?? 'Копировать ссылку') . '</button>' .
                             '</li>';
                    }
                }
                ?>
            </ul>
        </div>
    </div>
    
    <script>
    // Перевод для input type="file"
    var chooseFileText = '<?php echo htmlspecialchars($t['choose_file'] ?? 'Выберите файл'); ?>';
    var noFileChosenText = '<?php echo htmlspecialchars($t['no_file_chosen'] ?? 'Файл не выбран'); ?>';
    
    function updateFileInputText() {
        var fileInput = document.getElementById('playlist');
        if (fileInput) {
            if (fileInput.files && fileInput.files.length > 0) {
                fileInput.setAttribute('data-text', chooseFileText + ': ' + fileInput.files[0].name);
            } else {
                fileInput.setAttribute('data-text', noFileChosenText);
            }
        }
    }
    
    // Инициализация при загрузке страницы и после смены языка
    document.addEventListener('DOMContentLoaded', function() {
        updateFileInputText();
        var fileInput = document.getElementById('playlist');
        if (fileInput) {
            fileInput.addEventListener('change', updateFileInputText);
        }
        
        // Обновляем текст при смене языка (после перезагрузки страницы)
        setTimeout(updateFileInputText, 100);
    });
    
    function changeLanguage(lang) {
        fetch('functions.php?set_lang=' + lang, { credentials: 'same-origin' })
            .then(() => location.reload());
    }
    
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                alert('<?php echo htmlspecialchars($t['link_copied'] ?? 'Ссылка скопирована в буфер обмена'); ?>');
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
            alert('<?php echo htmlspecialchars($t['link_copied'] ?? 'Ссылка скопирована в буфер обмена'); ?>');
        } catch (err) {
            alert('<?php echo htmlspecialchars($t['copy_failed'] ?? 'Не удалось скопировать ссылку. Скопируйте вручную: '); ?>' + text);
        }
        document.body.removeChild(textarea);
    }
    </script>
</body>
</html>