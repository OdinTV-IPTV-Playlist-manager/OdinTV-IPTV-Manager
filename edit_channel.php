<?php
require_once 'functions.php';

$currentPlaylist = getCurrentPlaylist();
$playlistPath = PLAYLISTS_DIR . $currentPlaylist;
$channels = parseM3U8($playlistPath);
$index = isset($_GET['index']) ? (int)$_GET['index'] : -1;
$channel = isset($channels[$index]) ? $channels[$index] : null;

if (!$channel) {
    header('Location: index.php');
    exit;
}

// Извлекаем audio-track-id и user-agent из vlc_options
$channel['audio_track_id'] = '';
$channel['user_agent'] = '';
foreach ($channel['vlc_options'] as $opt) {
    if (strpos($opt, '#EXTVLCOPT:audio-track-id=') === 0) {
        $channel['audio_track_id'] = substr($opt, strlen('#EXTVLCOPT:audio-track-id='));
    }
    if (strpos($opt, '#EXTVLCOPT:http-user-agent=') === 0) {
        $channel['user_agent'] = substr($opt, strlen('#EXTVLCOPT:http-user-agent='));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Обновляем основные поля
    $channel['duration'] = $_POST['duration'] ?? '-1';
    $channel['title'] = $_POST['title'];
    $channel['tvg_id'] = $_POST['tvg_id'];
    $channel['tvg_name'] = $_POST['tvg_name'];
    $channel['tvg_logo'] = $_POST['tvg_logo'];
    $channel['group_title'] = $_POST['group_title'];
    $channel['catchup'] = $_POST['catchup'];
    $channel['catchup_days'] = $_POST['catchup_days'];
    $channel['catchup_source'] = $_POST['catchup_source'];
    $channel['tvg_rec'] = $_POST['tvg_rec'];
    $channel['audio_track_lang'] = $_POST['audio_track_lang'];
    $channel['url'] = $_POST['url'];
    
    // Обработка EXTVLCOPT
    $channel['audio_track_id'] = $_POST['audio_track_id'] ?? '';
    $channel['user_agent'] = $_POST['user_agent'] ?? '';
    $channel['vlc_options'] = [];
    if (!empty($channel['audio_track_id'])) {
        $channel['vlc_options'][] = "#EXTVLCOPT:audio-track-id={$channel['audio_track_id']}";
    }
    if (!empty($channel['user_agent'])) {
        $channel['vlc_options'][] = "#EXTVLCOPT:http-user-agent={$channel['user_agent']}";
    }
    
    // Обновляем массив атрибутов (attributes)
    $attributes = $channel['attributes'] ?? [];
    $keysToRemove = ['tvg-id', 'tvg-name', 'tvg-logo', 'group-title', 
                     'catchup', 'catchup-days', 'catchup-source', 
                     'tvg-rec', 'audio-track'];
    foreach ($keysToRemove as $key) {
        unset($attributes[$key]);
    }
    if ($channel['tvg_id'] !== '') $attributes['tvg-id'] = $channel['tvg_id'];
    if ($channel['tvg_name'] !== '') $attributes['tvg-name'] = $channel['tvg_name'];
    if ($channel['tvg_logo'] !== '') $attributes['tvg-logo'] = $channel['tvg_logo'];
    if ($channel['group_title'] !== '') $attributes['group-title'] = $channel['group_title'];
    if ($channel['catchup'] !== '') $attributes['catchup'] = $channel['catchup'];
    if ($channel['catchup_days'] !== '') $attributes['catchup-days'] = $channel['catchup_days'];
    if ($channel['catchup_source'] !== '') $attributes['catchup-source'] = $channel['catchup_source'];
    if ($channel['tvg_rec'] !== '') $attributes['tvg-rec'] = $channel['tvg_rec'];
    if ($channel['audio_track_lang'] !== '') $attributes['audio-track'] = $channel['audio_track_lang'];
    $channel['attributes'] = $attributes;
    
    $channels[$index] = $channel;
    savePlaylist($playlistPath, $channels);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование канала</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Редактирование канала (плейлист: <?php echo htmlspecialchars($currentPlaylist); ?>)</h1>
        
        <form method="POST" class="edit-form">
            <div class="form-group">
                <label for="title">Название канала:</label>
                <input type="text" id="title" name="title" 
                       value="<?php echo htmlspecialchars($channel['title']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="group_title">Категория:</label>
                <input type="text" id="group_title" name="group_title" 
                       value="<?php echo htmlspecialchars($channel['group_title']); ?>">
            </div>
            
            <div class="form-group">
                <label for="tvg_id">TVG ID:</label>
                <input type="text" id="tvg_id" name="tvg_id" 
                       value="<?php echo htmlspecialchars($channel['tvg_id']); ?>">
            </div>
            
            <div class="form-group">
                <label for="tvg_name">TVG Name:</label>
                <input type="text" id="tvg_name" name="tvg_name" 
                       value="<?php echo htmlspecialchars($channel['tvg_name']); ?>">
            </div>
            
            <div class="form-group">
                <label for="tvg_logo">URL логотипа:</label>
                <input type="url" id="tvg_logo" name="tvg_logo" 
                       value="<?php echo htmlspecialchars($channel['tvg_logo']); ?>">
            </div>
            
            <div class="form-group">
                <label for="catchup">Тип архива (catchup):</label>
                <input type="text" id="catchup" name="catchup" 
                       value="<?php echo htmlspecialchars($channel['catchup']); ?>"
                       placeholder="append, shift, etc.">
            </div>
            
            <div class="form-group">
                <label for="catchup_days">Дней архива (catchup-days):</label>
                <input type="text" id="catchup_days" name="catchup_days" 
                       value="<?php echo htmlspecialchars($channel['catchup_days']); ?>"
                       placeholder="7">
            </div>
            
            <div class="form-group">
                <label for="catchup_source">Шаблон ссылки архива (catchup-source):</label>
                <input type="text" id="catchup_source" name="catchup_source" 
                       value="<?php echo htmlspecialchars($channel['catchup_source']); ?>"
                       placeholder="?offset=-${offset}&utcstart=${timestamp}">
            </div>
            
            <div class="form-group">
                <label for="tvg_rec">tvg-rec (архив, дней):</label>
                <input type="number" id="tvg_rec" name="tvg_rec" 
                       value="<?php echo htmlspecialchars($channel['tvg_rec']); ?>"
                       placeholder="0 или 7" min="0" step="1">
                <small>0 = нет архива, 7 = архив на 7 дней и т.д. Оставьте поле пустым, чтобы удалить атрибут.</small>
            </div>
            
            <div class="form-group">
                <label for="audio_track_lang">Audio Track Language (audio-track):</label>
                <input type="text" id="audio_track_lang" name="audio_track_lang" 
                       value="<?php echo htmlspecialchars($channel['audio_track_lang']); ?>"
                       placeholder="rus, eng, etc.">
                <small>Язык аудиодорожки по умолчанию</small>
            </div>
            
            <div class="form-group">
                <label for="audio_track_id">Audio Track ID (EXTVLCOPT):</label>
                <input type="text" id="audio_track_id" name="audio_track_id" 
                       value="<?php echo htmlspecialchars($channel['audio_track_id']); ?>"
                       placeholder="2, 3, etc.">
                <small>Выбор аудиодорожки по умолчанию</small>
            </div>
            
            <div class="form-group">
                <label for="user_agent">User-Agent (EXTVLCOPT):</label>
                <input type="text" id="user_agent" name="user_agent" 
                       value="<?php echo htmlspecialchars($channel['user_agent']); ?>"
                       placeholder="Mozilla/5.0 ...">
                <small>User-Agent для запросов к потоку</small>
            </div>
            
            <div class="form-group">
                <label for="url">URL канала:</label>
                <input type="url" id="url" name="url" 
                       value="<?php echo htmlspecialchars($channel['url']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="duration">Длительность (обычно -1):</label>
                <input type="text" id="duration" name="duration" 
                       value="<?php echo htmlspecialchars($channel['duration']); ?>">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="index.php" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </div>
</body>
</html>