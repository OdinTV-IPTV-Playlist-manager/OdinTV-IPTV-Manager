<?php
session_start();

$playlistsDir = __DIR__ . '/playlists';
if (!is_dir($playlistsDir)) {
    mkdir($playlistsDir, 0755, true);
}

$mainListFile = $playlistsDir . '/MainList.m3u8';
if (!file_exists($mainListFile)) {
    file_put_contents($mainListFile, "#EXTM3U\n");
}

$_SESSION['currentPlaylist'] = $_SESSION['currentPlaylist'] ?? 'MainList.m3u8';
$_SESSION['filterGroup'] = $_SESSION['filterGroup'] ?? '';
$_SESSION['filterSearch'] = $_SESSION['filterSearch'] ?? '';

function parseM3U($content) {
    $channels = [];
    $lines = explode("\n", $content);
    $currentChannel = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        if (strpos($line, '#EXTINF:') === 0) {
            $currentChannel = [
                'extinf' => $line,
                'name' => '',
                'group' => '',
                'logo' => '',
                'tvg_id' => '',
                'tvg_name' => '',
                'tvg_rec' => '',
                'catchup' => '',
                'catchup_days' => '',
                'catchup_source' => '',
                'audio_track' => '',
                'useragent' => '',
                'url' => '',
                'extvlcopt' => []
            ];
            
            preg_match('/group-title="([^"]*)"/', $line, $matches);
            $currentChannel['group'] = $matches[1] ?? '';
            
            preg_match('/tvg-logo="([^"]*)"/', $line, $matches);
            $currentChannel['logo'] = $matches[1] ?? '';
            
            preg_match('/tvg-id="([^"]*)"/', $line, $matches);
            $currentChannel['tvg_id'] = $matches[1] ?? '';
            
            preg_match('/tvg-name="([^"]*)"/', $line, $matches);
            $currentChannel['tvg_name'] = $matches[1] ?? '';
            
            preg_match('/tvg-rec="([^"]*)"/', $line, $matches);
            $currentChannel['tvg_rec'] = $matches[1] ?? '';
            
            preg_match('/catchup="([^"]*)"/', $line, $matches);
            $currentChannel['catchup'] = $matches[1] ?? '';
            
            preg_match('/catchup-days="([^"]*)"/', $line, $matches);
            $currentChannel['catchup_days'] = $matches[1] ?? '';
            
            preg_match('/catchup-source="([^"]*)"/', $line, $matches);
            $currentChannel['catchup_source'] = $matches[1] ?? '';
            
            preg_match('/audio-track="([^"]*)"/', $line, $matches);
            $currentChannel['audio_track'] = $matches[1] ?? '';
            
            $nameParts = explode(',', $line);
            $currentChannel['name'] = trim(end($nameParts));
            
        } elseif (strpos($line, '#EXTVLCOPT:') === 0) {
            if ($currentChannel !== null) {
                if (preg_match('/#EXTVLCOPT:\s*user-agent=(.+)/i', $line, $matches)) {
                    $currentChannel['useragent'] = trim($matches[1]);
                } else {
                    $currentChannel['extvlcopt'][] = $line;
                }
            }
        } elseif (strpos($line, 'http') === 0 || strpos($line, 'rtmp') === 0 || strpos($line, 'udp') === 0 || strpos($line, 'rtp') === 0) {
            if ($currentChannel !== null) {
                $currentChannel['url'] = $line;
                $channels[] = $currentChannel;
                $currentChannel = null;
            }
        }
    }
    
    return $channels;
}

function generateM3U($channels) {
    $content = "#EXTM3U\n";
    foreach ($channels as $channel) {
        $extinf = '#EXTINF:-1';
        
        if (!empty($channel['tvg_id'])) $extinf .= ' tvg-id="' . $channel['tvg_id'] . '"';
        if (!empty($channel['tvg_name'])) $extinf .= ' tvg-name="' . $channel['tvg_name'] . '"';
        if (!empty($channel['group'])) $extinf .= ' group-title="' . $channel['group'] . '"';
        if (!empty($channel['logo'])) $extinf .= ' tvg-logo="' . $channel['logo'] . '"';
        if (!empty($channel['tvg_rec'])) $extinf .= ' tvg-rec="' . $channel['tvg_rec'] . '"';
        if (!empty($channel['catchup'])) $extinf .= ' catchup="' . $channel['catchup'] . '"';
        if (!empty($channel['catchup_days'])) $extinf .= ' catchup-days="' . $channel['catchup_days'] . '"';
        if (!empty($channel['catchup_source'])) $extinf .= ' catchup-source="' . $channel['catchup_source'] . '"';
        if (!empty($channel['audio_track'])) $extinf .= ' audio-track="' . $channel['audio_track'] . '"';
        
        $extinf .= ', ' . $channel['name'];
        
        $content .= $extinf . "\n";
        
        if (!empty($channel['useragent'])) {
            $content .= '#EXTVLCOPT:user-agent=' . $channel['useragent'] . "\n";
        }
        
        foreach ($channel['extvlcopt'] ?? [] as $opt) {
            $content .= $opt . "\n";
        }
        
        $content .= $channel['url'] . "\n";
    }
    return $content;
}

function sortChannels($channels) {
    usort($channels, function($a, $b) {
        $groupCompare = strcmp($a['group'], $b['group']);
        if ($groupCompare !== 0) return $groupCompare;
        return strcmp($a['name'], $b['name']);
    });
    return $channels;
}

$currentPlaylist = $_SESSION['currentPlaylist'];
$currentPlaylistPath = $playlistsDir . '/' . $currentPlaylist;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload') {
        $targetPlaylist = $_POST['target_playlist'] ?? 'MainList.m3u8';
        $targetPath = $playlistsDir . '/' . $targetPlaylist;
        
        if (isset($_FILES['playlist']) && $_FILES['playlist']['error'] === UPLOAD_ERR_OK) {
            $content = file_get_contents($_FILES['playlist']['tmp_name']);
            if (strpos($content, '#EXTM3U') === false) {
                $content = "#EXTM3U\n" . $content;
            }
            file_put_contents($targetPath, $content);
        }
        
        if (!empty($_POST['playlist_content'])) {
            $content = $_POST['playlist_content'];
            if (strpos($content, '#EXTM3U') === false) {
                $content = "#EXTM3U\n" . $content;
            }
            file_put_contents($targetPath, $content);
        }
        
        header('Location: index.php');
        exit;
    }
    
    if ($action === 'save_channel') {
        $index = (int)$_POST['index'];
        $content = file_get_contents($currentPlaylistPath);
        $channels = parseM3U($content);
        
        if (isset($channels[$index])) {
            $channels[$index]['name'] = $_POST['name'] ?? '';
            $channels[$index]['group'] = $_POST['group'] ?? '';
            $channels[$index]['logo'] = $_POST['logo'] ?? '';
            $channels[$index]['tvg_id'] = $_POST['tvg_id'] ?? '';
            $channels[$index]['tvg_name'] = $_POST['tvg_name'] ?? '';
            $channels[$index]['tvg_rec'] = $_POST['tvg_rec'] ?? '';
            $channels[$index]['catchup'] = $_POST['catchup'] ?? '';
            $channels[$index]['catchup_days'] = $_POST['catchup_days'] ?? '';
            $channels[$index]['catchup_source'] = $_POST['catchup_source'] ?? '';
            $channels[$index]['audio_track'] = $_POST['audio_track'] ?? '';
            $channels[$index]['useragent'] = $_POST['useragent'] ?? '';
            
            $extvlcopt_lines = $_POST['extvlcopt_lines'] ?? '';
            $channels[$index]['extvlcopt'] = array_filter(array_map('trim', explode("\n", $extvlcopt_lines)));
            
            file_put_contents($currentPlaylistPath, generateM3U($channels));
        }
        
        header('Location: index.php?group=' . urlencode($_SESSION['filterGroup']) . '&search=' . urlencode($_SESSION['filterSearch']));
        exit;
    }
    
    if ($action === 'delete_channel') {
        $index = (int)$_POST['index'];
        $content = file_get_contents($currentPlaylistPath);
        $channels = parseM3U($content);
        
        if (isset($channels[$index])) {
            unset($channels[$index]);
            $channels = array_values($channels);
            file_put_contents($currentPlaylistPath, generateM3U($channels));
        }
        
        header('Location: index.php?group=' . urlencode($_SESSION['filterGroup']) . '&search=' . urlencode($_SESSION['filterSearch']));
        exit;
    }
    
    if ($action === 'move_channel') {
        $index = (int)$_POST['index'];
        $targetFile = $_POST['target_file'] ?? '';
        
        $validTargets = ['check.m3u', 'bad.m3u', 'good.m3u', 'work.m3u'];
        if (!in_array($targetFile, $validTargets)) {
            header('Location: index.php');
            exit;
        }
        
        $targetPath = $playlistsDir . '/' . $targetFile;
        
        $content = file_get_contents($currentPlaylistPath);
        $channels = parseM3U($content);
        
        if (isset($channels[$index])) {
            $channel = $channels[$index];
            unset($channels[$index]);
            $channels = array_values($channels);
            
            file_put_contents($currentPlaylistPath, generateM3U($channels));
            
            $existingContent = file_exists($targetPath) ? file_get_contents($targetPath) : "#EXTM3U\n";
            $existingChannels = parseM3U($existingContent);
            $existingChannels[] = $channel;
            $existingChannels = sortChannels($existingChannels);
            
            file_put_contents($targetPath, generateM3U($existingChannels));
        }
        
        header('Location: index.php?group=' . urlencode($_SESSION['filterGroup']) . '&search=' . urlencode($_SESSION['filterSearch']));
        exit;
    }
    
    if ($action === 'switch_playlist') {
        $_SESSION['currentPlaylist'] = $_POST['playlist'] ?? 'MainList.m3u8';
        header('Location: index.php');
        exit;
    }
    
    if ($action === 'create_playlist') {
        $newName = preg_replace('/[^a-zA-Z0-9._-]/', '', $_POST['new_playlist_name'] ?? '');
        if (!empty($newName)) {
            if (!str_ends_with(strtolower($newName), '.m3u') && !str_ends_with(strtolower($newName), '.m3u8')) {
                $newName .= '.m3u8';
            }
            $newPath = $playlistsDir . '/' . $newName;
            if (!file_exists($newPath)) {
                file_put_contents($newPath, "#EXTM3U\n");
            }
            $_SESSION['currentPlaylist'] = $newName;
        }
        header('Location: index.php');
        exit;
    }
    
    if ($action === 'delete_playlist') {
        $playlistToDelete = $_POST['playlist_to_delete'] ?? '';
        $deletePath = $playlistsDir . '/' . $playlistToDelete;
        if (file_exists($deletePath) && $playlistToDelete !== 'MainList.m3u8') {
            unlink($deletePath);
        }
        if ($_SESSION['currentPlaylist'] === $playlistToDelete) {
            $_SESSION['currentPlaylist'] = 'MainList.m3u8';
        }
        header('Location: index.php');
        exit;
    }
}

$content = file_exists($currentPlaylistPath) ? file_get_contents($currentPlaylistPath) : "#EXTM3U\n";
$channels = parseM3U($content);

$groups = array_unique(array_column($channels, 'group'));
sort($groups);

$filterGroup = $_GET['group'] ?? $_SESSION['filterGroup'] ?? '';
$filterSearch = $_GET['search'] ?? $_SESSION['filterSearch'] ?? '';

$_SESSION['filterGroup'] = $filterGroup;
$_SESSION['filterSearch'] = $filterSearch;

if (!empty($filterGroup)) {
    $channels = array_filter($channels, fn($c) => $c['group'] === $filterGroup);
}

if (!empty($filterSearch)) {
    $channels = array_filter($channels, fn($c) => stripos($c['name'], $filterSearch) !== false);
}

$playlists = glob($playlistsDir . '/*.m3u*');
$playlists = array_map('basename', $playlists);
sort($playlists);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPTV Playlist Manager</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>IPTV Playlist Manager</h1>
        
        <div class="playlist-selector">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="switch_playlist">
                <select name="playlist" onchange="this.form.submit()">
                    <?php foreach ($playlists as $pl): ?>
                        <option value="<?= htmlspecialchars($pl) ?>" <?= $currentPlaylist === $pl ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            
            <button onclick="document.getElementById('createPlaylistModal').style.display='block'">Создать плейлист</button>
        </div>
        
        <div class="upload-section">
            <h2>Загрузить плейлист</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <select name="target_playlist">
                    <?php foreach ($playlists as $pl): ?>
                        <option value="<?= htmlspecialchars($pl) ?>" <?= $currentPlaylist === $pl ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="file" name="playlist" accept=".m3u,.m3u8">
                <button type="submit">Загрузить файл</button>
            </form>
            <p>или вставьте содержимое:</p>
            <form method="POST">
                <input type="hidden" name="action" value="upload">
                <select name="target_playlist">
                    <?php foreach ($playlists as $pl): ?>
                        <option value="<?= htmlspecialchars($pl) ?>" <?= $currentPlaylist === $pl ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <textarea name="playlist_content" placeholder="Вставьте содержимое M3U плейлиста"></textarea>
                <button type="submit">Загрузить текст</button>
            </form>
        </div>
        
        <div class="existing-playlists">
            <h2>Существующие плейлисты</h2>
            <table>
                <thead>
                    <tr>
                        <th>Плейлист</th>
                        <th>Прямая ссылка</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($playlists as $pl): ?>
                        <tr>
                            <td><?= htmlspecialchars($pl) ?></td>
                            <td>
                                <a href="playlists/<?= htmlspecialchars($pl) ?>" target="_blank">
                                    playlists/<?= htmlspecialchars($pl) ?>
                                </a>
                            </td>
                            <td>
                                <button onclick="copyToClipboard('<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/playlists/' . $pl) ?>')">
                                    Копировать ссылку
                                </button>
                                <?php if ($pl !== 'MainList.m3u8'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить плейлист <?= htmlspecialchars($pl) ?>?')">
                                        <input type="hidden" name="action" value="delete_playlist">
                                        <input type="hidden" name="playlist_to_delete" value="<?= htmlspecialchars($pl) ?>">
                                        <button type="submit" class="btn-danger">Удалить</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="filters">
            <h2>Фильтры</h2>
            <form method="GET">
                <label>Группа:
                    <select name="group">
                        <option value="">Все группы</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= htmlspecialchars($g) ?>" <?= $filterGroup === $g ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Поиск:
                    <input type="text" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="Название канала">
                </label>
                <button type="submit">Применить</button>
                <a href="index.php"><button type="button">Сбросить</button></a>
            </form>
        </div>
        
        <div class="channels-section">
            <h2>Каналы в <?= htmlspecialchars($currentPlaylist) ?> (<?= count($channels) ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Группа</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($channels as $idx => $channel): 
                        $originalIndex = array_search($channel, parseM3U(file_get_contents($currentPlaylistPath)));
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($channel['name']) ?></td>
                            <td><?= htmlspecialchars($channel['group']) ?></td>
                            <td class="actions">
                                <button class="btn-play" onclick="openPlayModal(<?= $originalIndex ?>, '<?= htmlspecialchars($channel['url'], ENT_QUOTES) ?>')">
                                    ▶ Смотреть
                                </button>
                                <button class="btn-edit" onclick="openEditModal(<?= $originalIndex ?>)">
                                    ✏️ Редактировать
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить канал?')">
                                    <input type="hidden" name="action" value="delete_channel">
                                    <input type="hidden" name="index" value="<?= $originalIndex ?>">
                                    <button type="submit" class="btn-delete">🗑️ Удалить</button>
                                </form>
                            </td>
                        </tr>
                        <tr class="secondary-actions">
                            <td colspan="3">
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Переместить в CHECK?')">
                                    <input type="hidden" name="action" value="move_channel">
                                    <input type="hidden" name="index" value="<?= $originalIndex ?>">
                                    <input type="hidden" name="target_file" value="check.m3u">
                                    <button type="submit" class="btn-check">CHECK</button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Переместить в BAD?')">
                                    <input type="hidden" name="action" value="move_channel">
                                    <input type="hidden" name="index" value="<?= $originalIndex ?>">
                                    <input type="hidden" name="target_file" value="bad.m3u">
                                    <button type="submit" class="btn-bad">BAD</button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Переместить в GOOD?')">
                                    <input type="hidden" name="action" value="move_channel">
                                    <input type="hidden" name="index" value="<?= $originalIndex ?>">
                                    <input type="hidden" name="target_file" value="good.m3u">
                                    <button type="submit" class="btn-good">GOOD</button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Переместить в WORK?')">
                                    <input type="hidden" name="action" value="move_channel">
                                    <input type="hidden" name="index" value="<?= $originalIndex ?>">
                                    <input type="hidden" name="target_file" value="work.m3u">
                                    <button type="submit" class="btn-work">WORK</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="playModal" class="modal">
        <div class="modal-content modal-large">
            <span class="close" onclick="closePlayModal()">&times;</span>
            <h2>Воспроизведение</h2>
            <video id="videoPlayer" controls style="width: 100%; max-height: 500px; background: #000;">
                <source src="" type="application/x-mpegURL">
                Ваш браузер не поддерживает воспроизведение видео.
            </video>
            <p id="streamUrl"></p>
            <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
            <script>
                function openPlayModal(index, url) {
                    document.getElementById('playModal').style.display = 'block';
                    document.getElementById('streamUrl').textContent = url;
                    
                    const video = document.getElementById('videoPlayer');
                    const source = video.querySelector('source');
                    source.src = url;
                    
                    if (Hls.isSupported()) {
                        const hls = new Hls();
                        hls.loadSource(url);
                        hls.attachMedia(video);
                        video.play();
                    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                        video.play();
                    }
                }
                
                function closePlayModal() {
                    document.getElementById('playModal').style.display = 'none';
                    const video = document.getElementById('videoPlayer');
                    video.pause();
                    video.src = '';
                }
            </script>
        </div>
    </div>
    
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Редактировать канал</h2>
            <form method="POST">
                <input type="hidden" name="action" value="save_channel">
                <input type="hidden" name="index" id="editIndex">
                
                <label>Название:
                    <input type="text" name="name" id="editName" required>
                </label>
                
                <label>Группа:
                    <input type="text" name="group" id="editGroup">
                </label>
                
                <label>TVG ID:
                    <input type="text" name="tvg_id" id="editTvgId">
                </label>
                
                <label>TVG Name:
                    <input type="text" name="tvg_name" id="editTvgName">
                </label>
                
                <label>Логотип:
                    <input type="text" name="logo" id="editLogo">
                </label>
                
                <label>TVG Rec:
                    <input type="text" name="tvg_rec" id="editTvgRec">
                </label>
                
                <label>Catchup:
                    <input type="text" name="catchup" id="editCatchup">
                </label>
                
                <label>Catchup Days:
                    <input type="text" name="catchup_days" id="editCatchupDays">
                </label>
                
                <label>Catchup Source:
                    <input type="text" name="catchup_source" id="editCatchupSource">
                </label>
                
                <label>Audio Track:
                    <input type="text" name="audio_track" id="editAudioTrack">
                </label>
                
                <label>User Agent:
                    <input type="text" name="useragent" id="editUseragent">
                </label>
                
                <label>Дополнительные EXTVLCOPT (каждая строка отдельно):
                    <textarea name="extvlcopt_lines" id="editExtvlcopt" rows="4"></textarea>
                </label>
                
                <button type="submit">Сохранить</button>
            </form>
        </div>
    </div>
    
    <div id="createPlaylistModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('createPlaylistModal').style.display='none'">&times;</span>
            <h2>Создать новый плейлист</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_playlist">
                <label>Название плейлиста:
                    <input type="text" name="new_playlist_name" required placeholder="Например: MyPlaylist.m3u8">
                </label>
                <button type="submit">Создать</button>
            </form>
        </div>
    </div>
    
    <script>
        <?php
        $channels = parseM3U(file_get_contents($currentPlaylistPath));
        $jsonChannels = json_encode($channels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        if ($jsonChannels === false) {
            $jsonChannels = '[]';
        }
        ?>
        const channelsData = <?= $jsonChannels ?>;
        
        function openEditModal(index) {
            const channel = channelsData[index];
            document.getElementById('editIndex').value = index;
            document.getElementById('editName').value = channel.name || '';
            document.getElementById('editGroup').value = channel.group || '';
            document.getElementById('editTvgId').value = channel.tvg_id || '';
            document.getElementById('editTvgName').value = channel.tvg_name || '';
            document.getElementById('editLogo').value = channel.logo || '';
            document.getElementById('editTvgRec').value = channel.tvg_rec || '';
            document.getElementById('editCatchup').value = channel.catchup || '';
            document.getElementById('editCatchupDays').value = channel.catchup_days || '';
            document.getElementById('editCatchupSource').value = channel.catchup_source || '';
            document.getElementById('editAudioTrack').value = channel.audio_track || '';
            document.getElementById('editUseragent').value = channel.useragent || '';
            document.getElementById('editExtvlcopt').value = (channel.extvlcopt || []).join('\n');
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Ссылка скопирована в буфер обмена!');
            }).catch(err => {
                console.error('Ошибка копирования:', err);
                alert('Не удалось скопировать ссылку');
            });
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
