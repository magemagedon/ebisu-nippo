<?php
require_once 'db.php';

$aid = $_GET['id'] ?? '';
if (!$aid) { header('Location: index.php'); exit; }

$pdo  = get_db();
$stmt = $pdo->prepare("SELECT * FROM t_attachment WHERE pk_attachment_id = ?");
$stmt->execute([$aid]);
$file = $stmt->fetch();
if (!$file) { header('Location: index.php'); exit; }

$path = __DIR__ . '/uploads/' . $file['f_filename'];
if (!file_exists($path)) {
    header('Location: index.php?msg=' . urlencode('ファイルが見つかりません'));
    exit;
}

header('Content-Type: ' . $file['f_mimetype']);
header('Content-Disposition: attachment; filename="' . rawurlencode($file['f_original_name']) . '"');
header('Content-Length: ' . $file['f_filesize']);
readfile($path);
exit;
