<?php
// functions.php - общие функции для работы с плейлистами

define('PLAYLISTS_DIR', __DIR__ . '/playlists/');
define('DEFAULT_PLAYLIST', 'MainList.m3u8');

function getPlaylists() {
    $files = scandir(PLAYLISTS_DIR);
    $playlists = [];
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && preg_match('/\.m3u8?$/i', $file)) {
            $playlists[] = $file;
        }
    }
    return $playlists;
}

function getCurrentPlaylist() {
    session_start();
    if (isset($_SESSION['current_playlist']) && file_exists(PLAYLISTS_DIR . $_SESSION['current_playlist'])) {
        return $_SESSION['current_playlist'];
    }
    if (file_exists(PLAYLISTS_DIR . DEFAULT_PLAYLIST)) {
        return DEFAULT_PLAYLIST;
    }
    $playlists = getPlaylists();
    return !empty($playlists) ? $playlists[0] : DEFAULT_PLAYLIST;
}

function setCurrentPlaylist($filename) {
    session_start();
    if (file_exists(PLAYLISTS_DIR . $filename)) {
        $_SESSION['current_playlist'] = $filename;
        return true;
    }
    return false;
}

function parseM3U8($filename) {
    if (!file_exists($filename)) {
        return [];
    }
    
    $content = file_get_contents($filename);
    $lines = explode("\n", $content);
    $channels = [];
    $currentChannel = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        if (strpos($line, '#EXTINF:') === 0) {
            preg_match('/#EXTINF:([-\d]+).*,(.+)/', $line, $matches);
            $duration = $matches[1] ?? '-1';
            $title = $matches[2] ?? '';
            
            preg_match_all('/([a-z-]+)="([^"]*)"/i', $line, $attrMatches, PREG_SET_ORDER);
            $attributes = [];
            foreach ($attrMatches as $attr) {
                $attributes[$attr[1]] = $attr[2];
            }
            
            $currentChannel = [
                'duration' => $duration,
                'title' => $title,
                'tvg_id' => $attributes['tvg-id'] ?? '',
                'tvg_name' => $attributes['tvg-name'] ?? '',
                'tvg_logo' => $attributes['tvg-logo'] ?? '',
                'group_title' => $attributes['group-title'] ?? 'Без категории',
                'catchup' => $attributes['catchup'] ?? '',
                'catchup_days' => $attributes['catchup-days'] ?? '',
                'catchup_source' => $attributes['catchup-source'] ?? '',
                'tvg_rec' => $attributes['tvg-rec'] ?? '',
                'audio_track_lang' => $attributes['audio-track'] ?? '',
                'url' => '',
                'attributes' => $attributes,
                'vlc_options' => [],
                'raw_extinf' => $line
            ];
        } elseif (strpos($line, '#EXTVLCOPT:') === 0 && $currentChannel) {
            $currentChannel['vlc_options'][] = $line;
        } elseif (strpos($line, 'http') === 0 && $currentChannel) {
            $currentChannel['url'] = $line;
            $channels[] = $currentChannel;
            $currentChannel = null;
        }
    }
    
    return $channels;
}

function savePlaylist($filename, $channels) {
    $content = "#EXTM3U\n";
    
    foreach ($channels as $channel) {
        $extinf = "#EXTINF:{$channel['duration']}";
        
        $attributes = $channel['attributes'] ?? [];
        
        if (empty($attributes)) {
            if (!empty($channel['tvg_id'])) $attributes['tvg-id'] = $channel['tvg_id'];
            if (!empty($channel['tvg_name'])) $attributes['tvg-name'] = $channel['tvg_name'];
            if (!empty($channel['tvg_logo'])) $attributes['tvg-logo'] = $channel['tvg_logo'];
            if (!empty($channel['group_title'])) $attributes['group-title'] = $channel['group_title'];
            if (!empty($channel['catchup'])) $attributes['catchup'] = $channel['catchup'];
            if (!empty($channel['catchup_days'])) $attributes['catchup-days'] = $channel['catchup_days'];
            if (!empty($channel['catchup_source'])) $attributes['catchup-source'] = $channel['catchup_source'];
            if (!empty($channel['tvg_rec'])) $attributes['tvg-rec'] = $channel['tvg_rec'];
            if (!empty($channel['audio_track_lang'])) $attributes['audio-track'] = $channel['audio_track_lang'];
        }
        
        ksort($attributes);
        
        foreach ($attributes as $key => $value) {
            if (!empty($value)) {
                $extinf .= " $key=\"$value\"";
            }
        }
        
        $extinf .= ",{$channel['title']}\n";
        $content .= $extinf;
        
        if (!empty($channel['vlc_options'])) {
            foreach ($channel['vlc_options'] as $opt) {
                $content .= $opt . "\n";
            }
        }
        
        $content .= $channel['url'] . "\n";
    }
    
    file_put_contents($filename, $content);
}

function checkChannelStream($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_RANGE => '0-2048',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['working' => false, 'reason' => 'Ошибка соединения: ' . $error, 'code' => $httpCode];
    }
    
    if ($httpCode >= 200 && $httpCode < 400) {
        $body = substr($response, $headerSize);
        
        if (strlen($body) === 0) {
            return ['working' => false, 'reason' => 'Пустой ответ', 'code' => $httpCode];
        }
        
        $isHls = preg_match('/\.m3u8?$/i', $url) || 
                 stripos($contentType, 'application/vnd.apple.mpegurl') !== false ||
                 stripos($contentType, 'audio/mpegurl') !== false ||
                 stripos($contentType, 'application/x-mpegURL') !== false;
        
        if ($isHls) {
            if (strpos($body, '#EXTM3U') !== false) {
                return ['working' => true, 'method' => 'HLS', 'code' => $httpCode];
            } else {
                return ['working' => false, 'reason' => 'Неверный HLS плейлист', 'code' => $httpCode];
            }
        } else {
            $isTransportStream = strpos($body, "\x47") === 0;
            if ($isTransportStream) {
                return ['working' => true, 'method' => 'MPEG-TS', 'code' => $httpCode];
            }
            
            if (stripos($contentType, 'video/') !== false || 
                stripos($contentType, 'audio/') !== false ||
                stripos($contentType, 'application/octet-stream') !== false) {
                return ['working' => true, 'method' => 'Stream', 'code' => $httpCode];
            }
            
            return ['working' => true, 'method' => 'Generic', 'code' => $httpCode];
        }
    } else {
        return ['working' => false, 'reason' => 'HTTP ошибка', 'code' => $httpCode];
    }
}

function sortChannelsByCategoryAndName($channels) {
    usort($channels, function($a, $b) {
        $catA = $a['group_title'] ?? '';
        $catB = $b['group_title'] ?? '';
        if ($catA !== $catB) {
            return strcasecmp($catA, $catB);
        }
        $titleA = $a['title'] ?? '';
        $titleB = $b['title'] ?? '';
        return strcasecmp($titleA, $titleB);
    });
    return $channels;
}
?>