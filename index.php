<?php
require_once 'functions.php';

// Обработка смены языка
if (isset($_GET['lang'])) {
    $newLang = $_GET['lang'];
    if (setLanguage($newLang)) {
        $params = [];
        if (isset($_GET['category'])) $params['category'] = $_GET['category'];
        if (isset($_GET['search'])) $params['search'] = $_GET['search'];
        $query = http_build_query($params);
        header('Location: index.php' . ($query ? '?' . $query : ''));
        exit;
    }
}

// Обработка смены плейлиста
if (isset($_GET['switch'])) {
    $newPlaylist = $_GET['switch'];
    if (setCurrentPlaylist($newPlaylist)) {
        $params = [];
        if (isset($_GET['category'])) $params['category'] = $_GET['category'];
        if (isset($_GET['search'])) $params['search'] = $_GET['search'];
        $query = http_build_query($params);
        header('Location: index.php' . ($query ? '?' . $query : ''));
        exit;
    }
}

$categoryFilter = isset($_GET['category']) ? $_GET['category'] : 'all';
$searchFilter = isset($_GET['search']) ? $_GET['search'] : '';

$currentPlaylist = getCurrentPlaylist();
$playlists = getPlaylists();
$mainPlaylistPath = PLAYLISTS_DIR . $currentPlaylist;
$channels = parseM3U8($mainPlaylistPath);
$categories = array_unique(array_column($channels, 'group_title'));
sort($categories);

// Загружаем переводы
$lang = getCurrentLanguage();
$availableLanguages = getAvailableLanguages();
$t = loadTranslations();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#3498db">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="format-detection" content="telephone=no">
    <title><?php echo htmlspecialchars($t['title'] ?? 'IPTV Менеджер плейлистов'); ?></title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($t['title'] ?? 'IPTV Менеджер плейлистов'); ?></h1>
        
        <div class="toolbar">
            <div>
                <h2><?php echo htmlspecialchars($t['current_playlist'] ?? 'Текущий плейлист:'); ?> 
                    <select onchange="switchPlaylist(this.value)">
                        <?php foreach ($playlists as $pl): ?>
                            <option value="<?php echo htmlspecialchars($pl); ?>" 
                                <?php echo $pl === $currentPlaylist ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pl); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </h2>
            </div>
            <div class="actions">
                <a href="upload.php" class="btn btn-primary"><?php echo htmlspecialchars($t['upload_new'] ?? 'Загрузить новый плейлист'); ?></a>
                <button onclick="location.reload()" class="btn btn-secondary"><?php echo htmlspecialchars($t['refresh_list'] ?? 'Обновить список'); ?></button>
                
                <!-- Выбор языка -->
                <?php if (count($availableLanguages) > 1): ?>
                <div class="language-selector">
                    <label for="language-select"><?php echo htmlspecialchars($t['language'] ?? 'Язык'); ?>: 
                        <?php 
                        // Получаем флаг текущего языка
                        $currentFlag = '';
                        if (isset($availableLanguages[$lang])) {
                            $currentFlag = $availableLanguages[$lang]['flag'];
                        }
                        ?>
                        <span><?php echo $currentFlag; ?></span>
                    </label>
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
        </div>
        
        <div class="filters">
            <label for="category-filter"><?php echo htmlspecialchars($t['filter_category'] ?? 'Фильтр по категории:'); ?></label>
            <select id="category-filter" onchange="filterChannels()">
                <option value="all"><?php echo htmlspecialchars($t['all_categories'] ?? 'Все категории'); ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category); ?>" 
                        <?php echo $category === $categoryFilter ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label for="search-input"><?php echo htmlspecialchars($t['search'] ?? 'Поиск:'); ?></label>
            <input type="text" id="search-input" placeholder="<?php echo htmlspecialchars($t['search_placeholder'] ?? 'Название канала...'); ?>" 
                   value="<?php echo htmlspecialchars($searchFilter); ?>" onkeyup="filterChannels()">
        </div>
        
        <div class="stats">
            <?php echo htmlspecialchars($t['total_channels'] ?? 'Всего каналов:'); ?> <span id="total-channels"><?php echo count($channels); ?></span>
        </div>
        
        <table class="channels-table" id="channels-table">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars($t['logo'] ?? 'Логотип'); ?></th>
                    <th><?php echo htmlspecialchars($t['name'] ?? 'Название'); ?></th>
                    <th><?php echo htmlspecialchars($t['category'] ?? 'Категория'); ?></th>
                    <th><?php echo htmlspecialchars($t['tvg_id'] ?? 'TVG ID'); ?></th>
                    <th><?php echo htmlspecialchars($t['archive'] ?? 'Архив'); ?></th>
                    <th><?php echo htmlspecialchars($t['options'] ?? 'Опции'); ?></th>
                    <th><?php echo htmlspecialchars($t['url'] ?? 'URL'); ?></th>
                    <th><?php echo htmlspecialchars($t['status'] ?? 'Статус'); ?></th>
                    <th><?php echo htmlspecialchars($t['actions'] ?? 'Действия'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($channels as $index => $channel): ?>
                <tr data-category="<?php echo htmlspecialchars($channel['group_title']); ?>" 
                    data-title="<?php echo htmlspecialchars($channel['title']); ?>">
                    <td data-label="<?php echo htmlspecialchars($t['logo'] ?? 'Логотип'); ?>">
                        <?php if (!empty($channel['tvg_logo'])): ?>
                            <img src="<?php echo htmlspecialchars($channel['tvg_logo']); ?>" 
                                 alt="logo" class="channel-logo" 
                                 onerror="this.style.display='none'">
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php echo htmlspecialchars($t['name'] ?? 'Название'); ?>" class="channel-title"><?php echo htmlspecialchars($channel['title']); ?></td>
                    <td data-label="<?php echo htmlspecialchars($t['category'] ?? 'Категория'); ?>"><?php echo htmlspecialchars($channel['group_title']); ?></td>
                    <td data-label="<?php echo htmlspecialchars($t['tvg_id'] ?? 'TVG ID'); ?>"><?php echo htmlspecialchars($channel['tvg_id']); ?></td>
                    <td data-label="<?php echo htmlspecialchars($t['archive'] ?? 'Архив'); ?>">
                        <?php
                        $archiveText = '';
                        $hasArchive = false;
                        if (!empty($channel['catchup'])) {
                            $days = $channel['catchup_days'] ?: '?';
                            $archiveText = sprintf($t['archive_badge'] ?? '📺 Архив: %s (%s дн)', $channel['catchup'], $days);
                            $hasArchive = true;
                        } elseif (!empty($channel['tvg_rec']) && $channel['tvg_rec'] > 0) {
                            $archiveText = sprintf($t['archive_rec'] ?? '📺 Архив (tvg-rec: %s дн)', $channel['tvg_rec']);
                            $hasArchive = true;
                        }
                        ?>
                        <?php if ($hasArchive): ?>
                            <span class="catchup-badge" title="<?php echo htmlspecialchars($archiveText); ?>">
                                <?php echo htmlspecialchars($archiveText); ?>
                            </span>
                        <?php else: ?>
                            <span class="catchup-none"><?php echo htmlspecialchars($t['no_archive'] ?? '—'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php echo htmlspecialchars($t['options'] ?? 'Опции'); ?>" class="channel-options">
                        <?php
                        $hasAudioLang = !empty($channel['audio_track_lang']);
                        $hasAudioTrackId = false;
                        $hasUserAgent = false;
                        foreach ($channel['vlc_options'] as $opt) {
                            if (strpos($opt, 'audio-track-id') !== false) $hasAudioTrackId = true;
                            if (strpos($opt, 'http-user-agent') !== false) $hasUserAgent = true;
                        }
                        ?>
                        <?php if ($hasAudioLang): ?>
                            <span class="option-badge" title="<?php echo htmlspecialchars(sprintf($t['audio_lang_tooltip'] ?? 'Язык аудио: %s', $channel['audio_track_lang'])); ?>">🔊</span>
                        <?php endif; ?>
                        <?php if ($hasAudioTrackId): ?>
                            <span class="option-badge" title="<?php echo htmlspecialchars($t['audio_track_tooltip'] ?? 'Audio Track ID задан'); ?>">🎚️</span>
                        <?php endif; ?>
                        <?php if ($hasUserAgent): ?>
                            <span class="option-badge" title="<?php echo htmlspecialchars($t['user_agent_tooltip'] ?? 'User-Agent задан'); ?>">🖥️</span>
                        <?php endif; ?>
                        <?php if (!$hasAudioLang && !$hasAudioTrackId && !$hasUserAgent): ?>
                            <span class="option-none"><?php echo htmlspecialchars($t['no_options'] ?? '—'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php echo htmlspecialchars($t['url'] ?? 'URL'); ?>" class="channel-url"><?php echo htmlspecialchars($channel['url']); ?></td>
                    <td data-label="<?php echo htmlspecialchars($t['status'] ?? 'Статус'); ?>">
                        <span class="status-badge status-unknown" id="status-<?php echo $index; ?>">
                            <?php echo htmlspecialchars($t['not_checked'] ?? 'Не проверен'); ?>
                        </span>
                    </td>
                    <td data-label="<?php echo htmlspecialchars($t['actions'] ?? 'Действия'); ?>" class="actions">
                        <div class="actions-container">
                            <div class="action-row">
                                <button onclick="checkChannel(<?php echo $index; ?>)" class="btn btn-small btn-check"><?php echo htmlspecialchars($t['check'] ?? 'Проверить'); ?></button>
                                <button onclick="playChannel(<?php echo $index; ?>)" class="btn btn-small btn-play"><?php echo htmlspecialchars($t['watch'] ?? 'Смотреть'); ?></button>
                                <a href="edit_channel.php?index=<?php echo $index; ?>" class="btn btn-small btn-edit"><?php echo htmlspecialchars($t['edit'] ?? 'Редактировать'); ?></a>
                                <button onclick="deleteChannel(<?php echo $index; ?>)" class="btn btn-small btn-delete"><?php echo htmlspecialchars($t['delete'] ?? 'Удалить'); ?></button>
                            </div>
                            <div class="action-row">
                                <button onclick="moveChannel(<?php echo $index; ?>, 'CHECK')" class="btn btn-small btn-move-check"><?php echo htmlspecialchars($t['move_check'] ?? 'CHECK'); ?></button>
                                <button onclick="moveChannel(<?php echo $index; ?>, 'BAD')" class="btn btn-small btn-move-bad"><?php echo htmlspecialchars($t['move_bad'] ?? 'BAD'); ?></button>
                                <button onclick="moveChannel(<?php echo $index; ?>, 'GOOD')" class="btn btn-small btn-move-good"><?php echo htmlspecialchars($t['move_good'] ?? 'GOOD'); ?></button>
                                <button onclick="moveChannel(<?php echo $index; ?>, 'WORK')" class="btn btn-small btn-move-work"><?php echo htmlspecialchars($t['move_work'] ?? 'WORK'); ?></button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Модальное окно для плеера -->
    <div id="playerModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <video id="player" controls preload="auto" width="100%" height="auto">
                <p class="vjs-no-js"><?php echo htmlspecialchars($t['video_no_js'] ?? 'Для просмотра видео включите JavaScript'); ?></p>
            </video>
        </div>
    </div>

    <script>
    function updateURLParams() {
        const category = document.getElementById('category-filter').value;
        const search = document.getElementById('search-input').value;
        const url = new URL(window.location.href);
        if (category !== 'all') {
            url.searchParams.set('category', category);
        } else {
            url.searchParams.delete('category');
        }
        if (search !== '') {
            url.searchParams.set('search', search);
        } else {
            url.searchParams.delete('search');
        }
        window.history.pushState({}, '', url);
    }

    function filterChannels() {
        const category = document.getElementById('category-filter').value;
        const search = document.getElementById('search-input').value.toLowerCase();
        const rows = document.querySelectorAll('#channels-table tbody tr');
        
        rows.forEach(row => {
            const rowCategory = row.dataset.category;
            const rowTitle = row.dataset.title.toLowerCase();
            
            const categoryMatch = category === 'all' || rowCategory === category;
            const searchMatch = search === '' || rowTitle.includes(search);
            
            if (categoryMatch && searchMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        document.getElementById('total-channels').textContent = visibleRows.length;
        updateURLParams();
    }
    
    function checkChannel(index) {
        const statusSpan = document.getElementById('status-' + index);
        statusSpan.className = 'status-badge status-checking';
        statusSpan.textContent = '<?php echo addslashes($t['checking'] ?? 'Проверка...'); ?>';
        
        fetch('check_channel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'index=' + index
        })
        .then(response => response.json())
        .then(data => {
            if (data.working) {
                statusSpan.className = 'status-badge status-working';
                statusSpan.textContent = '<?php echo addslashes(sprintf($t['working'] ?? 'Работает (%s, код %s)', '%s', '%s')); ?>'.replace('%s', data.method).replace('%s', data.code);
            } else {
                statusSpan.className = 'status-badge status-dead';
                statusSpan.textContent = '<?php echo addslashes(sprintf($t['not_working'] ?? 'Не работает: %s (код %s)', '%s', '%s')); ?>'.replace('%s', data.reason || '<?php echo addslashes($t['error_request'] ?? 'Ошибка'); ?>').replace('%s', data.code);
            }
        })
        .catch(error => {
            statusSpan.className = 'status-badge status-dead';
            statusSpan.textContent = '<?php echo addslashes($t['error_request'] ?? 'Ошибка запроса'); ?>';
        });
    }
    
    function deleteChannel(index) {
        if (confirm('<?php echo addslashes($t['confirm_delete'] ?? 'Вы уверены, что хотите удалить этот канал?'); ?>')) {
            const currentUrl = window.location.href;
            fetch('delete_channel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'index=' + index
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = currentUrl;
                } else {
                    alert('<?php echo addslashes($t['delete_error'] ?? 'Ошибка при удалении канала'); ?>');
                }
            });
        }
    }
    
    function switchPlaylist(playlist) {
        let url = 'index.php?switch=' + encodeURIComponent(playlist);
        const category = document.getElementById('category-filter').value;
        const search = document.getElementById('search-input').value;
        if (category !== 'all') url += '&category=' + encodeURIComponent(category);
        if (search !== '') url += '&search=' + encodeURIComponent(search);
        window.location.href = url;
    }

    function moveChannel(index, target) {
        if (confirm('<?php echo addslashes(sprintf($t['confirm_move'] ?? 'Переместить канал в плейлист %s.m3u8?', '%s')); ?>'.replace('%s', target))) {
            const currentUrl = window.location.href;
            fetch('move_channel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'index=' + index + '&target=' + target
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = currentUrl;
                } else {
                    alert('<?php echo addslashes(sprintf($t['move_error'] ?? 'Ошибка: %s', '%s')); ?>'.replace('%s', data.error || '<?php echo addslashes($t['move_request_error'] ?? 'неизвестная ошибка'); ?>'));
                }
            })
            .catch(error => alert('<?php echo addslashes(sprintf($t['move_request_error'] ?? 'Ошибка запроса: %s', '%s')); ?>'.replace('%s', error)));
        }
    }
    
    function changeLanguage(lang) {
        let url = 'index.php?lang=' + lang;
        const category = document.getElementById('category-filter').value;
        const search = document.getElementById('search-input').value;
        if (category !== 'all') url += '&category=' + encodeURIComponent(category);
        if (search !== '') url += '&search=' + encodeURIComponent(search);
        window.location.href = url;
    }

    // Модальное окно
    var modal = document.getElementById('playerModal');
    var span = document.getElementsByClassName('close')[0];
    var player = document.getElementById('player');
    var hls = null;

    span.onclick = function() {
        closePlayer();
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            closePlayer();
        }
    }

    function closePlayer() {
        modal.style.display = 'none';
        if (hls) {
            hls.destroy();
            hls = null;
        }
        player.pause();
        player.removeAttribute('src');
        player.load();
    }

    function playChannel(index) {
        fetch('get_channel_url.php?index=' + index)
            .then(response => response.json())
            .then(data => {
                if (data.url) {
                    modal.style.display = 'block';
                    
                    if (hls) {
                        hls.destroy();
                        hls = null;
                    }
                    player.pause();
                    player.removeAttribute('src');
                    player.load();

                    if (data.url.includes('.m3u8') || data.url.includes('.m3u')) {
                        if (Hls.isSupported()) {
                            hls = new Hls();
                            hls.loadSource(data.url);
                            hls.attachMedia(player);
                            hls.on(Hls.Events.MANIFEST_PARSED, function() {
                                player.play();
                            });
                        } else if (player.canPlayType('application/vnd.apple.mpegurl')) {
                            player.src = data.url;
                            player.addEventListener('loadedmetadata', function() {
                                player.play();
                            });
                        } else {
                            alert('Ваш браузер не поддерживает HLS');
                        }
                    } else {
                        player.src = data.url;
                        player.play();
                    }
                } else {
                    alert('Не удалось получить URL канала');
                }
            })
            .catch(error => {
                alert('Ошибка при загрузке канала');
            });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        filterChannels();
    });
    </script>
</body>
</html>