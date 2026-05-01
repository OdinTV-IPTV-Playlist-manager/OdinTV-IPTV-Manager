<?php
require_once 'functions.php';

// Обработка смены плейлиста
if (isset($_GET['switch'])) {
    $newPlaylist = $_GET['switch'];
    if (setCurrentPlaylist($newPlaylist)) {
        // Сохраняем текущие параметры фильтрации перед редиректом
        $params = [];
        if (isset($_GET['category'])) $params['category'] = $_GET['category'];
        if (isset($_GET['search'])) $params['search'] = $_GET['search'];
        $query = http_build_query($params);
        header('Location: index.php' . ($query ? '?' . $query : ''));
        exit;
    }
}

// Читаем фильтры из URL
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : 'all';
$searchFilter = isset($_GET['search']) ? $_GET['search'] : '';

$currentPlaylist = getCurrentPlaylist();
$playlists = getPlaylists();
$mainPlaylistPath = PLAYLISTS_DIR . $currentPlaylist;
$channels = parseM3U8($mainPlaylistPath);
$categories = array_unique(array_column($channels, 'group_title'));
sort($categories);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPTV Менеджер плейлистов</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
</head>
<body>
    <div class="container">
        <h1>IPTV Менеджер плейлистов</h1>
        
        <div class="toolbar">
            <div>
                <h2>Текущий плейлист: 
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
                <a href="upload.php" class="btn btn-primary">Загрузить новый плейлист</a>
                <button onclick="location.reload()" class="btn btn-secondary">Обновить список</button>
            </div>
        </div>
        
        <div class="filters">
            <label for="category-filter">Фильтр по категории:</label>
            <select id="category-filter" onchange="filterChannels()">
                <option value="all">Все категории</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category); ?>" 
                        <?php echo $category === $categoryFilter ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label for="search-input">Поиск:</label>
            <input type="text" id="search-input" placeholder="Название канала..." 
                   value="<?php echo htmlspecialchars($searchFilter); ?>" onkeyup="filterChannels()">
        </div>
        
        <div class="stats">
            Всего каналов: <span id="total-channels"><?php echo count($channels); ?></span>
        </div>
        
        <table class="channels-table" id="channels-table">
            <thead>
                <tr>
                    <th>Логотип</th>
                    <th>Название</th>
                    <th>Категория</th>
                    <th>TVG ID</th>
                    <th>Архив</th>
                    <th>Опции</th>
                    <th>URL</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($channels as $index => $channel): ?>
                <tr data-category="<?php echo htmlspecialchars($channel['group_title']); ?>" 
                    data-title="<?php echo htmlspecialchars($channel['title']); ?>">
                    <td>
                        <?php if (!empty($channel['tvg_logo'])): ?>
                            <img src="<?php echo htmlspecialchars($channel['tvg_logo']); ?>" 
                                 alt="logo" class="channel-logo" 
                                 onerror="this.style.display='none'">
                        <?php endif; ?>
                    </td>
                    <td class="channel-title"><?php echo htmlspecialchars($channel['title']); ?></td>
                    <td><?php echo htmlspecialchars($channel['group_title']); ?></td>
                    <td><?php echo htmlspecialchars($channel['tvg_id']); ?></td>
                    <td>
                        <?php
                        $archiveText = '';
                        $hasArchive = false;
                        if (!empty($channel['catchup'])) {
                            $days = $channel['catchup_days'] ?: '?';
                            $archiveText = "📺 Архив: {$channel['catchup']} ({$days} дн)";
                            $hasArchive = true;
                        } elseif (!empty($channel['tvg_rec']) && $channel['tvg_rec'] > 0) {
                            $archiveText = "📺 Архив (tvg-rec: {$channel['tvg_rec']} дн)";
                            $hasArchive = true;
                        }
                        ?>
                        <?php if ($hasArchive): ?>
                            <span class="catchup-badge" title="<?php echo htmlspecialchars($archiveText); ?>">
                                <?php echo htmlspecialchars($archiveText); ?>
                            </span>
                        <?php else: ?>
                            <span class="catchup-none">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="channel-options">
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
                            <span class="option-badge" title="Язык аудио: <?php echo htmlspecialchars($channel['audio_track_lang']); ?>">🔊</span>
                        <?php endif; ?>
                        <?php if ($hasAudioTrackId): ?>
                            <span class="option-badge" title="Audio Track ID задан">🎚️</span>
                        <?php endif; ?>
                        <?php if ($hasUserAgent): ?>
                            <span class="option-badge" title="User-Agent задан">🖥️</span>
                        <?php endif; ?>
                        <?php if (!$hasAudioLang && !$hasAudioTrackId && !$hasUserAgent): ?>
                            <span class="option-none">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="channel-url"><?php echo htmlspecialchars($channel['url']); ?></td>
                    <td>
                        <span class="status-badge status-unknown" id="status-<?php echo $index; ?>">
                            Не проверен
                        </span>
                    </td>
                    <td class="actions">
                        <div class="actions-container">
                            <div class="action-row">
                                <button onclick="checkChannel(<?php echo $index; ?>)" class="btn btn-small btn-check">Проверить</button>
                                <button onclick="playChannel(<?php echo $index; ?>)" class="btn btn-small btn-play">Смотреть</button>
                                <a href="edit_channel.php?index=<?php echo $index; ?>" class="btn btn-small btn-edit">Редактировать</a>
                                <button onclick="deleteChannel(<?php echo $index; ?>)" class="btn btn-small btn-delete">Удалить</button>
                            </div>
                            <div class="action-row">
                                <button onclick="moveChannel(<?php echo $index; ?>, 'CHECK')" class="btn btn-small btn-move-check">CHECK</button>
                                <button onclick="moveChannel(<?php echo $index; ?>, 'BAD')" class="btn btn-small btn-move-bad">BAD</button>
                                <button onclick="moveChannel(<?php echo $index; ?>, 'GOOD')" class="btn btn-small btn-move-good">GOOD</button>
                                <button onclick="moveChannel(<?php echo $index; ?>, 'WORK')" class="btn btn-small btn-move-work">WORK</button>
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
                <p class="vjs-no-js">Для просмотра видео включите JavaScript</p>
            </video>
        </div>
    </div>

    <script>
    // Функция обновления URL параметров (без перезагрузки)
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

    // Фильтрация каналов
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
        
        // Обновляем счётчик видимых каналов
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        document.getElementById('total-channels').textContent = visibleRows.length;
        
        // Сохраняем параметры в URL
        updateURLParams();
    }
    
    function checkChannel(index) {
        const statusSpan = document.getElementById('status-' + index);
        statusSpan.className = 'status-badge status-checking';
        statusSpan.textContent = 'Проверка...';
        
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
                statusSpan.textContent = 'Работает (' + data.method + ', код ' + data.code + ')';
            } else {
                statusSpan.className = 'status-badge status-dead';
                statusSpan.textContent = 'Не работает: ' + (data.reason || 'Ошибка') + ' (код ' + data.code + ')';
            }
        })
        .catch(error => {
            statusSpan.className = 'status-badge status-dead';
            statusSpan.textContent = 'Ошибка запроса';
        });
    }
    
    function deleteChannel(index) {
        if (confirm('Вы уверены, что хотите удалить этот канал?')) {
            // Сохраняем текущие параметры фильтрации перед перезагрузкой
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
                    // Перезагружаем с сохранением параметров
                    window.location.href = currentUrl;
                } else {
                    alert('Ошибка при удалении канала');
                }
            });
        }
    }
    
    function switchPlaylist(playlist) {
        let url = 'index.php?switch=' + encodeURIComponent(playlist);
        // Сохраняем текущие фильтры
        const category = document.getElementById('category-filter').value;
        const search = document.getElementById('search-input').value;
        if (category !== 'all') url += '&category=' + encodeURIComponent(category);
        if (search !== '') url += '&search=' + encodeURIComponent(search);
        window.location.href = url;
    }

    function moveChannel(index, target) {
        if (confirm(`Переместить канал в плейлист ${target}.m3u8?`)) {
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
                    alert('Ошибка: ' + (data.error || 'неизвестная ошибка'));
                }
            })
            .catch(error => alert('Ошибка запроса: ' + error));
        }
    }

    // Переменные для модального окна
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
    
    // При загрузке страницы применяем фильтры из URL (если они есть)
    document.addEventListener('DOMContentLoaded', function() {
        // Убедимся, что фильтры уже установлены из PHP (в атрибутах selected и value)
        // и вызовем filterChannels для применения скрытия строк
        filterChannels();
    });
    </script>
</body>
</html>