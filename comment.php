<?php
require_once 'db.php';

$id   = $_POST['nippo_id'] ?? '';
$body = trim($_POST['f_body'] ?? '');

if (!$id || !$body) {
    header('Location: detail.php?id=' . $id);
    exit;
}

$pdo = get_db();
$pdo->prepare("
    INSERT INTO t_comment (pk_comment_id, fk_nippo_id, fk_tantosha_id, f_body, f_created_at)
    VALUES (?, ?, ?, ?, NOW())
")->execute([generate_uuid(), $id, $_SESSION['tantosha_id'], $body]);

header('Location: detail.php?id=' . $id . '#comments');
exit;
