<?php
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['index'])) {
    $index = (int)$_POST['index'];
    $currentPlaylist = getCurrentPlaylist();
    $playlistPath = PLAYLISTS_DIR . $currentPlaylist;
    $channels = parseM3U8($playlistPath);
    
    if (isset($channels[$index])) {
        $url = $channels[$index]['url'];
        $result = checkChannelStream($url);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Channel not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}
?>