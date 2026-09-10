<?php
require_once 'db.php';

$id = $_GET['id'] ?? '';
if (!$id) { header('Location: index.php'); exit; }

$pdo = get_db();

$stmt = $pdo->prepare("
    SELECT h.*, t.f_tantosha_name, k.f_tantosha_name AS kakunin_name
    FROM t_nippo_header h
    LEFT JOIN t_tantosha t ON h.fk_tantosha_id = t.pk_tantosha_id
    LEFT JOIN t_tantosha k ON h.f_kakunin_sha_id = k.pk_tantosha_id
    WHERE h.pk_nippo_id = ?
");
$stmt->execute([$id]);
$nippo = $stmt->fetch();
if (!$nippo) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("
    SELECT m.*, tr.f_torihikisaki_name
    FROM t_nippo_meisai m
    LEFT JOIN t_torihikisaki tr ON m.fk_torihikisaki_id = tr.pk_torihikisaki_id
    WHERE m.fk_nippo_id = ?
    ORDER BY m.f_created_at
");
$stmt->execute([$id]);
$meisais = $stmt->fetchAll();

function he($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/vendor/autoload.php';

// フォント設定
$fontDir  = __DIR__ . '/fonts';
$fontData = [
    'vlgothic' => [
        'R'  => 'vlgothic.ttf',
        'B'  => 'vlgothic.ttf',
        'I'  => 'vlgothic.ttf',
        'BI' => 'vlgothic.ttf',
    ]
];

$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'ja',
    'format'        => 'A4',
    'margin_top'    => 12,
    'margin_bottom' => 12,
    'margin_left'   => 14,
    'margin_right'  => 14,
    'default_font'  => 'vlgothic',
    'fontDir'       => [$fontDir],
    'fontdata'      => $fontData,
]);

$mpdf->autoScriptToLang = true;
$mpdf->autoLangToFont   = true;

$date_str = date('Y年m月d日', strtotime($nippo['f_date']));

$status_color = match($nippo['f_kakunin_status']) {
    '確認済'   => '#2e7d32',
    '未確認'   => '#8a6200',
    '差し戻し' => '#b71c1c',
    default    => '#555'
};
$status_bg = match($nippo['f_kakunin_status']) {
    '確認済'   => '#e6f4ea',
    '未確認'   => '#fff8e1',
    '差し戻し' => '#fdecea',
    default    => '#f5f5f5'
};

$meisai_html = '';
foreach ($meisais as $i => $m) {
    $kin = ($m['f_kaishu_ryo'] && $m['f_tanka'])
        ? number_format($m['f_kaishu_ryo'] * $m['f_tanka']) . '円'
        : '―';
    $jcolor = match($m['f_juchu_mikomikubun']) {
        '受注'         => '#2e7d32',
        '見込み'       => '#1565c0',
        '継続フォロー' => '#e65100',
        '失注'         => '#b71c1c',
        default        => '#555'
    };
    $meisai_html .= "
    <div style='margin-bottom:12px;border:1px solid #c5d3e8;border-radius:4px;overflow:hidden;page-break-inside:avoid'>
      <div style='background:#1B3A6B;color:#fff;padding:6px 12px;font-size:11pt;font-weight:bold'>
        訪問先" . ($i+1) . "：" . he($m['f_homonsakimei']) . "
        <span style='margin-left:10px;font-size:9pt;background:{$jcolor};padding:2px 8px;border-radius:8px'>" . he($m['f_juchu_mikomikubun']) . "</span>
      </div>
      <table style='width:100%;border-collapse:collapse;font-size:9.5pt'>
        <tr>
          <td style='background:#EEF3FA;width:22%;padding:5px 10px;font-weight:bold;border-bottom:1px solid #e0e8f0;border-right:1px solid #e0e8f0'>先方担当者</td>
          <td style='padding:5px 10px;border-bottom:1px solid #e0e8f0;border-right:1px solid #e0e8f0;width:28%'>" . he($m['f_saki_tantosha'] ?: '―') . "</td>
          <td style='background:#EEF3FA;width:22%;padding:5px 10px;font-weight:bold;border-bottom:1px solid #e0e8f0;border-right:1px solid #e0e8f0'>次回予定日</td>
          <td style='padding:5px 10px;border-bottom:1px solid #e0e8f0'>" . ($m['f_jikai_yoteibi'] ? he(date('Y/m/d', strtotime($m['f_jikai_yoteibi']))) : '―') . "</td>
        </tr>
        <tr>
          <td style='background:#EEF3FA;padding:5px 10px;font-weight:bold;border-bottom:1px solid #e0e8f0;border-right:1px solid #e0e8f0'>対応内容</td>
          <td colspan='3' style='padding:5px 10px;border-bottom:1px solid #e0e8f0'>" . he($m['f_taiou_naiyo'] ?: '―') . "</td>
        </tr>
        <tr>
          <td style='background:#EEF3FA;padding:5px 10px;font-weight:bold;border-bottom:1px solid #e0e8f0;border-right:1px solid #e0e8f0'>次回アクション</td>
          <td colspan='3' style='padding:5px 10px;border-bottom:1px solid #e0e8f0'>" . he($m['f_jikai_action'] ?: '―') . "</td>
        </tr>
        <tr>
          <td style='background:#EEF3FA;padding:5px 10px;font-weight:bold;border-right:1px solid #e0e8f0'>数量・金額</td>
          <td colspan='3' style='padding:5px 10px'>
            古紙相場：" . ($m['f_furushi_soba'] ? number_format($m['f_furushi_soba']).'円/t' : '―') . "　
            回収量：" . ($m['f_kaishu_ryo'] ? $m['f_kaishu_ryo'].'t' : '―') . "　
            単価：" . ($m['f_tanka'] ? number_format($m['f_tanka']).'円/t' : '―') . "　
            <strong>計：{$kin}</strong>
          </td>
        </tr>
      </table>
    </div>";
}

$biko_row = $nippo['f_biko'] ? "
  <tr>
    <td style='background:#EEF3FA;padding:6px 12px;font-weight:bold;border-top:1px solid #c5d3e8;border-right:1px solid #c5d3e8'>備考</td>
    <td colspan='3' style='padding:6px 12px;border-top:1px solid #c5d3e8'>" . he($nippo['f_biko']) . "</td>
  </tr>" : '';

$kakunin_row = $nippo['kakunin_name'] ? "
  <tr>
    <td style='background:#EEF3FA;padding:6px 12px;font-weight:bold;border-top:1px solid #c5d3e8;border-right:1px solid #c5d3e8'>確認者</td>
    <td colspan='3' style='padding:6px 12px;border-top:1px solid #c5d3e8'>" . he($nippo['kakunin_name']) . "　" . he($nippo['f_kakunin_datetime']) . "</td>
  </tr>" : '';

$html = "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family:vlgothic,sans-serif;font-size:10pt;color:#1a1a2e;margin:0;padding:0'>

<div style='background:#1B3A6B;color:#fff;padding:8px 16px;margin-bottom:16px'>
  <table style='width:100%;border-collapse:collapse'>
    <tr>
      <td style='font-size:13pt;font-weight:bold;color:#fff'>エビス紙料株式会社　業務日報</td>
      <td style='text-align:right;font-size:9pt;color:#B8D4F0'>ARシステム 業務日報システム</td>
    </tr>
  </table>
</div>

<div style='font-size:15pt;font-weight:bold;color:#1B3A6B;margin-bottom:12px;padding-bottom:6px;border-bottom:2px solid #2E75B6'>業務日報</div>

<table style='width:100%;border-collapse:collapse;margin-bottom:16px;border:1px solid #c5d3e8;font-size:10pt'>
  <tr>
    <td style='background:#1B3A6B;color:#fff;width:20%;padding:7px 12px;font-weight:bold;border-right:1px solid #2E75B6'>日付</td>
    <td style='padding:7px 12px;width:30%;font-size:12pt;font-weight:bold;color:#1B3A6B;border-right:1px solid #c5d3e8'>{$date_str}</td>
    <td style='background:#1B3A6B;color:#fff;width:20%;padding:7px 12px;font-weight:bold;border-right:1px solid #2E75B6'>担当者</td>
    <td style='padding:7px 12px;font-size:11pt;font-weight:bold'>" . he($nippo['f_tantosha_name']) . "</td>
  </tr>
  <tr>
    <td style='background:#1B3A6B;color:#fff;padding:7px 12px;font-weight:bold;border-top:1px solid #2E75B6;border-right:1px solid #2E75B6'>確認ステータス</td>
    <td style='padding:7px 12px;border-top:1px solid #c5d3e8;border-right:1px solid #c5d3e8'>
      <span style='background:{$status_bg};color:{$status_color};border:1px solid {$status_color};padding:2px 10px;border-radius:8px;font-weight:bold'>" . he($nippo['f_kakunin_status']) . "</span>
    </td>
    <td style='background:#1B3A6B;color:#fff;padding:7px 12px;font-weight:bold;border-top:1px solid #2E75B6;border-right:1px solid #2E75B6'>訪問件数</td>
    <td style='padding:7px 12px;border-top:1px solid #c5d3e8;font-size:11pt;font-weight:bold'>" . count($meisais) . " 件</td>
  </tr>
  {$biko_row}
  {$kakunin_row}
</table>

<div style='font-size:12pt;font-weight:bold;color:#1B3A6B;margin-bottom:10px;padding-bottom:5px;border-bottom:2px solid #2E75B6'>
  訪問先明細（" . count($meisais) . "件）
</div>

{$meisai_html}

<div style='margin-top:20px;padding:6px 14px;background:#f0f4f8;border-top:1px solid #c5d3e8;font-size:8pt;color:#888'>
  <table style='width:100%;border-collapse:collapse'>
    <tr>
      <td>出力日時：" . date('Y/m/d H:i') . "</td>
      <td style='text-align:right'>エビス紙料株式会社　業務日報システム</td>
    </tr>
  </table>
</div>

</body>
</html>";

$mpdf->WriteHTML($html);
$filename = '業務日報_' . date('Ymd', strtotime($nippo['f_date'])) . '_' . $nippo['f_tantosha_name'] . '.pdf';
$mpdf->Output($filename, 'D');
