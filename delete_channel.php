<?php
require_once 'functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['index'])) {
    $index = (int)$_POST['index'];
    $currentPlaylist = getCurrentPlaylist();
    $playlistPath = PLAYLISTS_DIR . $currentPlaylist;
    $channels = parseM3U8($playlistPath);
    
    if (isset($channels[$index])) {
        array_splice($channels, $index, 1);
        savePlaylist($playlistPath, $channels);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Channel not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>