<?php
require_once 'db.php';

$id = $_POST['nippo_id'] ?? '';
if (!$id) { header('Location: index.php'); exit; }

// ファイルアップロード処理
if (!isset($_FILES['f_file']) || $_FILES['f_file']['error'] !== UPLOAD_ERR_OK) {
    header('Location: detail.php?id=' . $id . '&msg=' . urlencode('ファイルのアップロードに失敗しました'));
    exit;
}

$file     = $_FILES['f_file'];
$orig     = basename($file['name']);
$ext      = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
$allowed  = ['jpg','jpeg','png','gif','pdf','xlsx','xls','docx','doc','csv','txt'];

if (!in_array($ext, $allowed)) {
    header('Location: detail.php?id=' . $id . '&msg=' . urlencode('許可されていないファイル形式です'));
    exit;
}

if ($file['size'] > 10 * 1024 * 1024) {
    header('Location: detail.php?id=' . $id . '&msg=' . urlencode('ファイルサイズは10MB以下にしてください'));
    exit;
}

$new_name = generate_uuid() . '.' . $ext;
$dest     = __DIR__ . '/uploads/' . $new_name;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    header('Location: detail.php?id=' . $id . '&msg=' . urlencode('ファイルの保存に失敗しました'));
    exit;
}

$pdo = get_db();
$pdo->prepare("
    INSERT INTO t_attachment (pk_attachment_id, fk_nippo_id, fk_tantosha_id, f_filename, f_original_name, f_filesize, f_mimetype, f_created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
")->execute([
    generate_uuid(), $id, $_SESSION['tantosha_id'],
    $new_name, $orig, $file['size'], $file['type']
]);

header('Location: detail.php?id=' . $id . '&msg=' . urlencode('ファイルをアップロードしました'));
exit;
