<?php
require_once 'functions.php';

if (isset($_GET['index'])) {
    $index = (int)$_GET['index'];
    $currentPlaylist = getCurrentPlaylist();
    $playlistPath = PLAYLISTS_DIR . $currentPlaylist;
    $channels = parseM3U8($playlistPath);
    
    if (isset($channels[$index])) {
        header('Content-Type: application/json');
        echo json_encode(['url' => $channels[$index]['url']]);
        exit;
    }
}

http_response_code(404);
echo json_encode(['error' => 'Channel not found']);
?>