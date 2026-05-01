<?php
require_once 'functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['index'], $_POST['target'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$index = (int)$_POST['index'];
$target = preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['target']);
if (empty($target)) {
    echo json_encode(['success' => false, 'error' => 'Invalid target']);
    exit;
}
// Приводим имя к нижнему регистру и меняем расширение на .m3u
$targetFile = strtolower($target) . '.m3u';

$currentPlaylist = getCurrentPlaylist();
$currentPath = PLAYLISTS_DIR . $currentPlaylist;
$targetPath = PLAYLISTS_DIR . $targetFile;

$channels = parseM3U8($currentPath);
if (!isset($channels[$index])) {
    echo json_encode(['success' => false, 'error' => 'Channel not found']);
    exit;
}

$channel = $channels[$index];
array_splice($channels, $index, 1);
savePlaylist($currentPath, $channels);

$targetChannels = file_exists($targetPath) ? parseM3U8($targetPath) : [];
$targetChannels[] = $channel;
$targetChannels = sortChannelsByCategoryAndName($targetChannels);
savePlaylist($targetPath, $targetChannels);

echo json_encode(['success' => true]);
?>