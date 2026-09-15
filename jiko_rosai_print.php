<?php
// ============================================================
// 労働者死傷病報告（様式第23号）下書き印刷ページ
// 事故報告（労災区分）の内容から、労基署提出用の下書きを組み立てて表示する。
// あくまで社内での下書き・叩き台であり、正式な提出書類そのものではない。
// ============================================================
require_once 'db.php';
require_once 'common.php';
$pdo = get_db();
$kengen = $_SESSION['kengen'] ?? '';
$can_view = in_array($kengen, ['部門管理者', '管理者'], true);
if (!$can_view) { header('Location: jiko.php'); exit; }

$id = $_GET['id'] ?? '';
$stmt = $pdo->prepare("
    SELECT j.*, f.f_factory_name, f.f_address AS factory_address, f.f_tel AS factory_tel,
           t.f_tantosha_name AS reporter_name,
           jt.f_tantosha_name AS joucho_name, kt.f_tantosha_name AS kanri_name
    FROM t_jiko j
    LEFT JOIN t_factory f ON j.fk_factory_id = f.pk_factory_id
    JOIN t_tantosha t ON j.fk_tantosha_id = t.pk_tantosha_id
    LEFT JOIN t_tantosha jt ON j.fk_joucho_kakunin_tantosha_id = jt.pk_tantosha_id
    LEFT JOIN t_tantosha kt ON j.fk_kanri_kakunin_tantosha_id = kt.pk_tantosha_id
    WHERE j.pk_jiko_id = ?
");
$stmt->execute([$id]);
$j = $stmt->fetch();
if (!$j) { header('Location: jiko.php'); exit; }

function age_from($birth) {
    if (empty($birth)) return '';
    $b = new DateTime($birth);
    $now = new DateTime();
    return $b->diff($now)->y;
}
function d($v, $default = '　') { return ($v === null || $v === '') ? $default : h($v); }

echo html_header('労災報告書（下書き）');
?>
<style>
@media print {
  .no-print { display: none !important; }
  body { background: #fff !important; }
}
.rosai-box { max-width: 800px; margin: 16px auto; background: #fff; border: 1px solid #ccc; padding: 24px; font-size: 13px; line-height: 1.7 }
.rosai-title { text-align: center; font-size: 18px; font-weight: 700; margin-bottom: 4px }
.rosai-sub { text-align: center; font-size: 12px; color: #666; margin-bottom: 18px }
.rosai-sec { margin-top: 18px }
.rosai-sec-title { font-weight: 700; font-size: 13px; background: #eef3f9; padding: 6px 10px; border-left: 4px solid #1B3A6B; margin-bottom: 8px }
.rosai-grid { display: grid; grid-template-columns: 140px 1fr 140px 1fr; border: 1px solid #ccc }
.rosai-grid > div { border: 1px solid #ddd; padding: 6px 8px }
.rosai-label { background: #f7f7f7; font-weight: 600 }
.rosai-full { grid-column: 1 / -1 }
.rosai-note { background: #fff8f6; border: 1px solid #f0d0c8; padding: 10px 12px; font-size: 12px; color: #611a15; margin-top: 8px; white-space: pre-wrap }
</style>
<div class="container no-print" style="max-width:800px;margin:12px auto;display:flex;justify-content:space-between;align-items:center">
  <a href="jiko.php" class="btn btn-gray btn-sm">← 事故報告一覧に戻る</a>
  <button onclick="window.print()" class="btn btn-primary btn-sm">🖨 印刷／PDF保存</button>
</div>

<div class="rosai-box">
  <div class="rosai-title">労働者死傷病報告（様式第23号）下書き</div>
  <div class="rosai-sub">※本ページは社内の事故報告から自動作成した「叩き台」です。正式な労基署提出の際は、内容をご確認のうえ厚生労働省の電子申請または正式様式に転記してください。</div>

  <div class="rosai-sec">
    <div class="rosai-sec-title">事業場情報</div>
    <div class="rosai-grid">
      <div class="rosai-label">事業場の名称</div><div><?= d($j['f_factory_name'] ?? 'エビス紙料株式会社') ?></div>
      <div class="rosai-label">労働保険番号</div><div>（未入力：手書きでご記入ください）</div>
      <div class="rosai-label">所在地</div><div class="rosai-full" style="grid-column:span 3"><?= d($j['factory_address']) ?></div>
      <div class="rosai-label">電話番号</div><div><?= d($j['factory_tel']) ?></div>
      <div class="rosai-label">事業の種類</div><div>（未入力：手書きでご記入ください）</div>
    </div>
  </div>

  <div class="rosai-sec">
    <div class="rosai-sec-title">被災者情報</div>
    <div class="rosai-grid">
      <div class="rosai-label">氏名</div><div><?= d($j['f_higaisha_name']) ?></div>
      <div class="rosai-label">性別</div><div><?= d($j['f_higaisha_sei']) ?></div>
      <div class="rosai-label">生年月日</div><div><?= $j['f_higaisha_seinengappi'] ? h(date('Y/m/d', strtotime($j['f_higaisha_seinengappi']))) . '（満' . age_from($j['f_higaisha_seinengappi']) . '歳）' : '　' ?></div>
      <div class="rosai-label">職種</div><div><?= d($j['f_higaisha_shokushu']) ?></div>
      <div class="rosai-label">当該業務の経験期間</div><div class="rosai-full" style="grid-column:span 3"><?= d($j['f_keiken_kikan']) ?></div>
    </div>
  </div>

  <div class="rosai-sec">
    <div class="rosai-sec-title">災害発生日時・場所</div>
    <div class="rosai-grid">
      <div class="rosai-label">発生日時</div>
      <div><?= h(date('Y/m/d', strtotime($j['f_date']))) ?><?= $j['f_time'] ? ' ' . h($j['f_time']) : '' ?></div>
      <div class="rosai-label">発生場所</div>
      <div><?= d(trim(($j['f_factory_name'] ?? '') . ' ' . ($j['f_place_text'] ?? ''))) ?></div>
    </div>
  </div>

  <div class="rosai-sec">
    <div class="rosai-sec-title">傷病の状況</div>
    <div class="rosai-grid">
      <div class="rosai-label">傷病の部位</div><div><?= d($j['f_shoubyou_bui']) ?></div>
      <div class="rosai-label">傷病名</div><div><?= d($j['f_shoubyou_mei']) ?></div>
      <div class="rosai-label">休業／死亡の別</div><div><?= d($j['f_kyugyo_kubun']) ?></div>
      <div class="rosai-label">休業見込み日数</div><div><?= $j['f_kyugyo_nissu'] !== null ? h($j['f_kyugyo_nissu']) . '日' : '　' ?></div>
    </div>
  </div>

  <div class="rosai-sec">
    <div class="rosai-sec-title">災害発生状況及び原因</div>
    <div style="border:1px solid #ccc;padding:10px;min-height:80px;white-space:pre-wrap"><?= d($j['f_detail'], '（事故報告の詳細が未入力です）') ?></div>
    <div class="rosai-note">記入の目安（労基署提出時は次の5点が伝わるように書き直してください）：
①どのような場所で　②どのような作業をしているときに　③どのような物又は環境に
④どのような不安全な又は有害な状態があって　⑤どのような災害が発生したか</div>
  </div>

  <div class="rosai-sec">
    <div class="rosai-sec-title">社内確認状況（参考・社内用）</div>
    <div class="rosai-grid">
      <div class="rosai-label">報告者</div><div><?= d($j['reporter_name']) ?></div>
      <div class="rosai-label">対応状況</div><div><?= d($j['f_taiou_status']) ?></div>
      <div class="rosai-label">上長確認</div><div><?= d($j['joucho_name']) ?><?= $j['f_joucho_kakunin_at'] ? '（' . h(date('Y/m/d', strtotime($j['f_joucho_kakunin_at']))) . '）' : '' ?></div>
      <div class="rosai-label">管理確認</div><div><?= d($j['kanri_name']) ?><?= $j['f_kanri_kakunin_at'] ? '（' . h(date('Y/m/d', strtotime($j['f_kanri_kakunin_at']))) . '）' : '' ?></div>
    </div>
  </div>

  <div class="rosai-sec" style="text-align:right;font-size:12px;color:#666">
    下書き作成日：<?= h(date('Y年m月d日')) ?>
  </div>
</div>
<?= html_footer() ?>
