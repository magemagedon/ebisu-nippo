<?php
require_once 'db.php';

$id = $_GET['id'] ?? '';
if (!$id) { header('Location: index.php'); exit; }

$pdo = get_db();

// 元日報取得
$stmt = $pdo->prepare("SELECT * FROM t_nippo_header WHERE pk_nippo_id = ?");
$stmt->execute([$id]);
$nippo = $stmt->fetch();
if (!$nippo) { header('Location: index.php'); exit; }

// 明細取得
$stmt = $pdo->prepare("SELECT * FROM t_nippo_meisai WHERE fk_nippo_id = ? ORDER BY f_created_at");
$stmt->execute([$id]);
$meisais = $stmt->fetchAll();

try {
    $pdo->beginTransaction();

    // 新規ヘッダ作成（日付は今日・ステータスは未確認にリセット）
    $new_id = generate_uuid();
    $pdo->prepare("
        INSERT INTO t_nippo_header
        (pk_nippo_id, fk_tantosha_id, f_date, f_kakunin_status, f_biko, f_created_at, f_updated_at)
        VALUES (?, ?, ?, '未確認', ?, NOW(), NOW())
    ")->execute([
        $new_id,
        $_SESSION['tantosha_id'],
        date('Y-m-d'),
        $nippo['f_biko']
    ]);

    // 明細コピー
    $stmt = $pdo->prepare("
        INSERT INTO t_nippo_meisai
        (pk_meisai_id, fk_nippo_id, fk_torihikisaki_id, fk_kyoten_id, fk_busho_id, fk_saki_tantosha_id,
         f_homonsakimei, f_saki_tantosha,
         f_taiou_naiyo, f_juchu_mikomikubun, f_jikai_action, f_jikai_yoteibi,
         f_furushi_soba, f_kaishu_ryo, f_tanka, f_created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    foreach ($meisais as $m) {
        $stmt->execute([
            generate_uuid(), $new_id,
            $m['fk_torihikisaki_id'], $m['fk_kyoten_id'] ?? null, $m['fk_busho_id'] ?? null, $m['fk_saki_tantosha_id'] ?? null,
            $m['f_homonsakimei'], $m['f_saki_tantosha'],
            $m['f_taiou_naiyo'], $m['f_juchu_mikomikubun'], $m['f_jikai_action'],
            null, // 次回予定日はリセット
            $m['f_furushi_soba'], $m['f_kaishu_ryo'], $m['f_tanka']
        ]);
    }

    $pdo->commit();
    header('Location: create.php?id=' . $new_id . '&copied=1');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: index.php?msg=' . urlencode('コピーに失敗しました'));
    exit;
}
