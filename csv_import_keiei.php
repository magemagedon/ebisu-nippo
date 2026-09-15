<?php
// ============================================================
// 経営数値集計：販売管理システムからの伝票データCSV取込
// ヘッダー行の列名を見て必要な列だけを取り込む（列順の変動に対応）。
// 集計（事業部・指標への分類）はt_keiei_bunrui_ruleにより表示時に行う。
// ============================================================
require_once 'db.php';
require_once 'common.php';
if ($_SESSION['kengen'] !== '管理者') { header('Location: index.php'); exit; }

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['csv_file']['tmp_name'])) {
    header('Location: keiei.php');
    exit;
}

$tmp = $_FILES['csv_file']['tmp_name'];
$raw = file_get_contents($tmp);

$enc = mb_detect_encoding($raw, ['UTF-8', 'SJIS-win', 'SJIS', 'EUC-JP'], true);
if ($enc && $enc !== 'UTF-8') {
    $raw = mb_convert_encoding($raw, 'UTF-8', $enc);
}
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // BOM除去

$lines = preg_split("/\r\n|\r|\n/", $raw);
$rows = [];
foreach ($lines as $line) {
    if (trim($line) === '') continue;
    $rows[] = str_getcsv($line);
}

if (count($rows) < 2) {
    header('Location: keiei.php?csv_msg=' . urlencode('CSVにデータ行がありません。') . '&csv_msg_type=danger');
    exit;
}

// ヘッダー行から必要な列の位置を特定（列順の変動に対応するため名前で探す）
$header = $rows[0];
$colIdx = [];
foreach ($header as $i => $name) {
    $colIdx[trim($name)] = $i;
}
function col($row, $colIdx, $name) {
    return isset($colIdx[$name]) ? trim($row[$colIdx[$name]] ?? '') : '';
}

$need = ['伝票日付', '部門', '業種', '商品種別', '商品', '売上仕入区分', '数量', '単位', '金額'];
$missing = array_filter($need, fn($n) => !isset($colIdx[$n]));
if ($missing) {
    header('Location: keiei.php?csv_msg=' . urlencode('CSVに必要な列がありません：' . implode('、', $missing)) . '&csv_msg_type=danger');
    exit;
}
$sakiCol = isset($colIdx['集計先']) ? '集計先' : (isset($colIdx['得意先']) ? '得意先' : null);

$added = 0; $skipped = 0; $errors = [];
$batch = date('YmdHis') . '-' . substr(generate_uuid(), 0, 8);

$ins = $pdo->prepare("INSERT INTO t_keiei_denpyo
    (pk_denpyo_id,f_denpyo_date,f_yearmonth,f_bumon,f_gyoshu,f_shohin_shubetsu,f_shohin,f_uriage_shiire_kubun,f_suryo,f_tani,f_kingaku,f_saki_meisho,f_import_batch,f_created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");

for ($i = 1; $i < count($rows); $i++) {
    $r = $rows[$i];
    if (count($r) < 2) { $skipped++; continue; }

    $dateRaw = col($r, $colIdx, '伝票日付');
    if ($dateRaw === '') { $skipped++; continue; }
    // "2026/08/01 0:00:00" のような形式を想定
    $ts = strtotime($dateRaw);
    if ($ts === false) { $errors[] = ($i + 1) . '行目：伝票日付の形式が不正です（' . $dateRaw . '）'; continue; }
    $date = date('Y-m-d', $ts);
    $ym = date('Y-m', $ts);

    $kingakuRaw = str_replace(',', '', col($r, $colIdx, '金額'));
    $suryoRaw   = str_replace(',', '', col($r, $colIdx, '数量'));

    try {
        $ins->execute([
            generate_uuid(),
            $date,
            $ym,
            col($r, $colIdx, '部門') ?: null,
            col($r, $colIdx, '業種') ?: null,
            col($r, $colIdx, '商品種別') ?: null,
            col($r, $colIdx, '商品') ?: null,
            col($r, $colIdx, '売上仕入区分') ?: null,
            $suryoRaw !== '' ? (float)$suryoRaw : null,
            col($r, $colIdx, '単位') ?: null,
            $kingakuRaw !== '' ? (float)$kingakuRaw : 0,
            $sakiCol ? (col($r, $colIdx, $sakiCol) ?: null) : null,
            $batch,
        ]);
        $added++;
    } catch (Exception $e) {
        $errors[] = ($i + 1) . '行目：' . $e->getMessage();
    }
}

$msg = "取込完了：{$added} 件" . ($skipped ? "／スキップ {$skipped} 件（日付なし等）" : '');
if ($errors) $msg .= '／エラー ' . count($errors) . ' 件（' . implode('　', array_slice($errors, 0, 3)) . (count($errors) > 3 ? ' 他' : '') . '）';

header('Location: keiei.php?csv_msg=' . urlencode($msg) . ($errors ? '&csv_msg_type=danger' : '&csv_msg_type=success'));
exit;
