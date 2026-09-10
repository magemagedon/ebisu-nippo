<?php
require_once 'db.php';
require_once 'common.php';

$pdo = get_db();

$type    = $_GET['type']   ?? 'nippo';
$f_from  = $_GET['f_from'] ?? date('Y-m-01');
$f_to    = $_GET['f_to']   ?? date('Y-m-t');
$f_tanto = $_GET['f_tanto'] ?? '';

$where  = ['h.f_date BETWEEN ? AND ?'];
$params = [$f_from, $f_to];

[$cond, $ps] = visible_tantosha_where($pdo, 'h.fk_tantosha_id');
if ($cond) {
    $where[] = $cond; array_push($params, ...$ps);
} elseif ($f_tanto) {
    $where[] = 'h.fk_tantosha_id = ?';
    $params[] = $f_tanto;
}

if ($type === 'meisai') {
    // 明細CSV
    $stmt = $pdo->prepare("
        SELECT h.f_date, t.f_tantosha_name, h.f_kakunin_status,
               COALESCE(tr.f_torihikisaki_name, m.f_homonsakimei) AS torihikisaki_name,
               m.f_homonsakimei, m.f_saki_tantosha, m.f_taiou_naiyo,
               m.f_juchu_mikomikubun, m.f_jikai_action, m.f_jikai_yoteibi,
               m.f_furushi_soba, m.f_kaishu_ryo, m.f_tanka,
               COALESCE(m.f_kaishu_ryo * m.f_tanka, 0) AS kingaku
        FROM t_nippo_meisai m
        JOIN t_nippo_header h ON m.fk_nippo_id = h.pk_nippo_id
        JOIN t_tantosha t ON h.fk_tantosha_id = t.pk_tantosha_id
        LEFT JOIN t_torihikisaki tr ON m.fk_torihikisaki_id = tr.pk_torihikisaki_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY h.f_date DESC, h.f_created_at ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $headers = ['日付','担当者','確認ステータス','取引先名','訪問先名','先方担当者',
                '対応内容','受注見込み区分','次回アクション','次回予定日',
                '古紙相場(円/t)','回収量(t)','単価(円/t)','金額(円)'];
    $fields  = ['f_date','f_tantosha_name','f_kakunin_status','torihikisaki_name',
                'f_homonsakimei','f_saki_tantosha','f_taiou_naiyo','f_juchu_mikomikubun',
                'f_jikai_action','f_jikai_yoteibi','f_furushi_soba','f_kaishu_ryo',
                'f_tanka','kingaku'];
    $filename = '日報明細_' . $f_from . '_' . $f_to . '.csv';

} elseif ($type === 'soba') {
    // 古紙相場CSV
    $stmt = $pdo->prepare("
        SELECT h.f_date, t.f_tantosha_name,
               COALESCE(tr.f_torihikisaki_name, m.f_homonsakimei) AS torihikisaki_name,
               m.f_furushi_soba, m.f_kaishu_ryo, m.f_tanka,
               COALESCE(m.f_kaishu_ryo * m.f_tanka, 0) AS kingaku
        FROM t_nippo_meisai m
        JOIN t_nippo_header h ON m.fk_nippo_id = h.pk_nippo_id
        JOIN t_tantosha t ON h.fk_tantosha_id = t.pk_tantosha_id
        LEFT JOIN t_torihikisaki tr ON m.fk_torihikisaki_id = tr.pk_torihikisaki_id
        WHERE m.f_furushi_soba IS NOT NULL AND " . implode(' AND ', $where) . "
        ORDER BY h.f_date ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $headers = ['日付','担当者','取引先名','古紙相場(円/t)','回収量(t)','単価(円/t)','金額(円)'];
    $fields  = ['f_date','f_tantosha_name','torihikisaki_name',
                'f_furushi_soba','f_kaishu_ryo','f_tanka','kingaku'];
    $filename = '古紙相場_' . $f_from . '_' . $f_to . '.csv';

} else {
    // 日報ヘッダCSV
    $stmt = $pdo->prepare("
        SELECT h.f_date, t.f_tantosha_name, h.f_kakunin_status, h.f_biko,
               k.f_tantosha_name AS kakunin_name, h.f_kakunin_datetime,
               (SELECT COUNT(*) FROM t_nippo_meisai m WHERE m.fk_nippo_id = h.pk_nippo_id) AS meisai_count
        FROM t_nippo_header h
        JOIN t_tantosha t ON h.fk_tantosha_id = t.pk_tantosha_id
        LEFT JOIN t_tantosha k ON h.f_kakunin_sha_id = k.pk_tantosha_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY h.f_date DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $headers = ['日付','担当者','確認ステータス','訪問件数','備考','確認者','確認日時'];
    $fields  = ['f_date','f_tantosha_name','f_kakunin_status','meisai_count',
                'f_biko','kakunin_name','f_kakunin_datetime'];
    $filename = '日報一覧_' . $f_from . '_' . $f_to . '.csv';
}

// CSV出力
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

// BOM付きUTF-8（Excelで文字化けしないように）
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// ヘッダ行
fputcsv($out, $headers);

// データ行
foreach ($rows as $row) {
    $line = [];
    foreach ($fields as $f) {
        $line[] = $row[$f] ?? '';
    }
    fputcsv($out, $line);
}

fclose($out);
exit;
