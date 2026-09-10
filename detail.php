<?php
require_once 'db.php';
require_once 'common.php';

$pdo = get_db();
$id  = $_GET['id'] ?? '';
if (!$id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT h.*, t.f_tantosha_name, k.f_tantosha_name AS kakunin_name FROM t_nippo_header h LEFT JOIN t_tantosha t ON h.fk_tantosha_id=t.pk_tantosha_id LEFT JOIN t_tantosha k ON h.f_kakunin_sha_id=k.pk_tantosha_id WHERE h.pk_nippo_id=?");
$stmt->execute([$id]);
$nippo = $stmt->fetch();
if (!$nippo) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT m.*, tr.f_torihikisaki_name FROM t_nippo_meisai m LEFT JOIN t_torihikisaki tr ON m.fk_torihikisaki_id=tr.pk_torihikisaki_id WHERE m.fk_nippo_id=? ORDER BY m.f_created_at");
$stmt->execute([$id]);
$meisais = $stmt->fetchAll();

$msg = '';
$msg_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['kengen'] === '管理者') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve') {
        $pdo->prepare("UPDATE t_nippo_header SET f_kakunin_status='確認済', f_kakunin_sha_id=?, f_kakunin_datetime=NOW(), f_updated_at=NOW() WHERE pk_nippo_id=?")
            ->execute([$_SESSION['tantosha_id'], $id]);
        $msg = '承認しました。';
        $nippo['f_kakunin_status'] = '確認済';
    } elseif ($action === 'reject') {
        $reason = $_POST['reject_reason'] ?? '';
        $pdo->prepare("UPDATE t_nippo_header SET f_kakunin_status='差し戻し', f_kakunin_sha_id=?, f_kakunin_datetime=NOW(), f_biko=CONCAT('[差し戻し] ',?), f_updated_at=NOW() WHERE pk_nippo_id=?")
            ->execute([$_SESSION['tantosha_id'], $reason, $id]);
        $msg = '差し戻しました。';
        $msg_type = 'warning';
        $nippo['f_kakunin_status'] = '差し戻し';
    }
}

echo html_header('日報詳細');
echo nav_bar();
?>
<div class="container">

  <!-- ヘッダ操作ボタン -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <div class="page-title" style="margin:0;border:none">日報詳細</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="index.php" class="btn btn-gray btn-sm">← 一覧</a>
      <a href="pdf.php?id=<?= h($id) ?>" class="btn btn-danger btn-sm" target="_blank">📄 PDF出力</a>
      <?php if($nippo['f_kakunin_status'] !== '確認済' && ($nippo['fk_tantosha_id']===$_SESSION['tantosha_id'] || $_SESSION['kengen']==='管理者')): ?>
      <a href="create.php?id=<?= h($id) ?>" class="btn btn-blue btn-sm">編集</a>
      <a href="copy.php?id=<?= h($id) ?>" class="btn btn-gray btn-sm" onclick="return confirm('この日報をコピーして新規作成しますか？')">コピー</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if($msg): ?>
  <div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- 基本情報 -->
  <div class="card">
    <div class="card-header">基本情報</div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px" class="detail-grid">
        <div>
          <div style="font-size:11px;color:#888;margin-bottom:4px">日付</div>
          <div style="font-weight:700;font-size:18px;color:#1B3A6B"><?= h(date('Y/m/d', strtotime($nippo['f_date']))) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:#888;margin-bottom:4px">担当者</div>
          <div style="font-weight:600;font-size:15px"><?= h($nippo['f_tantosha_name']) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:#888;margin-bottom:4px">確認ステータス</div>
          <div><?= status_badge($nippo['f_kakunin_status']) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:#888;margin-bottom:4px">訪問件数</div>
          <div style="font-weight:600"><?= count($meisais) ?> 件</div>
        </div>
      </div>
      <?php if($nippo['f_biko']): ?>
      <div style="margin-top:14px;padding-top:12px;border-top:1px solid #eee">
        <div style="font-size:11px;color:#888;margin-bottom:4px">備考</div>
        <div style="font-size:13px"><?= h($nippo['f_biko']) ?></div>
      </div>
      <?php endif; ?>
      <?php if($nippo['kakunin_name']): ?>
      <div style="margin-top:10px;font-size:11px;color:#888">
        確認者：<?= h($nippo['kakunin_name']) ?> ／ <?= h($nippo['f_kakunin_datetime']) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 明細 -->
  <div class="card">
    <div class="card-header">訪問先明細（<?= count($meisais) ?>件）</div>
    <div class="card-body">
      <?php foreach($meisais as $i => $m): ?>
      <div class="meisai-row" style="margin-bottom:14px">
        <span class="meisai-num"><?= $i+1 ?></span>
        <div style="padding-left:32px">
          <div style="font-size:15px;font-weight:700;color:#1B3A6B;margin-bottom:6px">
            <?= h($m['f_homonsakimei']) ?>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px">
            <?php if($m['f_saki_tantosha']): ?>
            <span style="font-size:12px;color:#666">担当：<?= h($m['f_saki_tantosha']) ?></span>
            <?php endif; ?>
            <?= juchu_badge($m['f_juchu_mikomikubun']) ?>
          </div>
          <?php if($m['f_taiou_naiyo']): ?>
          <div style="margin-bottom:10px">
            <div style="font-size:11px;color:#888;margin-bottom:4px">対応内容</div>
            <div style="background:#f0f4fa;border-left:3px solid #2E75B6;padding:8px 10px;font-size:13px;border-radius:0 4px 4px 0"><?= nl2br(h($m['f_taiou_naiyo'])) ?></div>
          </div>
          <?php endif; ?>
          <?php if($m['f_jikai_action'] || $m['f_jikai_yoteibi']): ?>
          <div style="margin-bottom:8px;font-size:12px">
            <?php if($m['f_jikai_action']): ?>
            <div><span style="color:#888">次回：</span><?= h($m['f_jikai_action']) ?></div>
            <?php endif; ?>
            <?php if($m['f_jikai_yoteibi']): ?>
            <div><span style="color:#888">予定日：</span><?= h(date('Y/m/d', strtotime($m['f_jikai_yoteibi']))) ?></div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if($m['f_furushi_soba'] || $m['f_kaishu_ryo'] || $m['f_tanka']): ?>
          <div style="background:#fff;border:1px solid #e0e8f0;border-radius:5px;padding:8px 10px;font-size:12px;display:grid;grid-template-columns:1fr 1fr;gap:6px">
            <?php if($m['f_furushi_soba']): ?><div><span style="color:#888">相場：</span><?= number_format($m['f_furushi_soba']) ?>円/t</div><?php endif; ?>
            <?php if($m['f_kaishu_ryo']): ?><div><span style="color:#888">回収：</span><?= $m['f_kaishu_ryo'] ?>t</div><?php endif; ?>
            <?php if($m['f_tanka']): ?><div><span style="color:#888">単価：</span><?= number_format($m['f_tanka']) ?>円/t</div><?php endif; ?>
            <?php if($m['f_kaishu_ryo'] && $m['f_tanka']): ?>
            <div style="grid-column:1/-1;border-top:1px solid #e0e8f0;padding-top:6px;margin-top:2px">
              <span style="color:#888">金額：</span><strong style="color:#1B3A6B;font-size:14px"><?= number_format($m['f_kaishu_ryo'] * $m['f_tanka']) ?>円</strong>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 管理者承認エリア -->
  <?php if ($_SESSION['kengen'] === '管理者' && $nippo['f_kakunin_status'] !== '確認済'): ?>
  <div class="card">
    <div class="card-header" style="background:#2e7d32">管理者確認</div>
    <div class="card-body">
      <form method="post" style="margin-bottom:12px">
        <input type="hidden" name="action" value="approve">
        <button type="submit" class="btn btn-success btn-block" onclick="return confirm('この日報を承認しますか？')">✓ 承認する</button>
      </form>
      <form method="post">
        <input type="hidden" name="action" value="reject">
        <div class="form-group">
          <label>差し戻し理由</label>
          <input type="text" name="reject_reason" class="form-control" placeholder="理由を入力してください" required>
        </div>
        <button type="submit" class="btn btn-warning btn-block" onclick="return confirm('差し戻しますか？')">✕ 差し戻す</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php
// ========== ファイル添付 ==========
$stmt_att = $pdo->prepare("SELECT a.*, t.f_tantosha_name FROM t_attachment a JOIN t_tantosha t ON a.fk_tantosha_id=t.pk_tantosha_id WHERE a.fk_nippo_id=? ORDER BY a.f_created_at ASC");
$stmt_att->execute([$id]);
$attachments = $stmt_att->fetchAll();
?>
<div class="card">
  <div class="card-header">添付ファイル（<?= count($attachments) ?>件）</div>
  <div class="card-body">
    <?php foreach($attachments as $a): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e8eef5">
      <div style="font-size:13px">
        📎 <?= h($a['f_original_name']) ?>
        <span style="font-size:11px;color:#888;margin-left:8px">
          <?= h($a['f_tantosha_name']) ?> ／
          <?= h(date('Y/m/d H:i', strtotime($a['f_created_at']))) ?> ／
          <?= number_format($a['f_filesize']/1024, 1) ?>KB
        </span>
      </div>
      <a href="download.php?id=<?= h($a['pk_attachment_id']) ?>" class="btn btn-gray btn-sm">ダウンロード</a>
    </div>
    <?php endforeach; ?>
    <form method="post" action="upload.php" enctype="multipart/form-data" style="margin-top:12px">
      <input type="hidden" name="nippo_id" value="<?= h($id) ?>">
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="file" name="f_file" class="form-control" style="flex:1" accept=".jpg,.jpeg,.png,.gif,.pdf,.xlsx,.xls,.docx,.doc,.csv,.txt">
        <button type="submit" class="btn btn-primary btn-sm">📎 アップロード</button>
      </div>
      <div style="font-size:11px;color:#888;margin-top:4px">対応形式：JPG・PNG・PDF・Excel・Word・CSV・TXT（10MB以下）</div>
    </form>
  </div>
</div>

<?php
// ========== コメント ==========
$stmt_cmt = $pdo->prepare("SELECT c.*, t.f_tantosha_name FROM t_comment c JOIN t_tantosha t ON c.fk_tantosha_id=t.pk_tantosha_id WHERE c.fk_nippo_id=? ORDER BY c.f_created_at ASC");
$stmt_cmt->execute([$id]);
$comments = $stmt_cmt->fetchAll();
?>
<div class="card" id="comments">
  <div class="card-header">コメント（<?= count($comments) ?>件）</div>
  <div class="card-body">
    <?php foreach($comments as $c): ?>
    <?php $is_me = ($c['fk_tantosha_id'] === $_SESSION['tantosha_id']); ?>
    <div style="display:flex;gap:10px;margin-bottom:14px;<?= $is_me ? 'flex-direction:row-reverse' : '' ?>">
      <div style="width:34px;height:34px;border-radius:50%;background:#1B3A6B;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0">
        <?= h(mb_substr($c['f_tantosha_name'],0,1)) ?>
      </div>
      <div style="max-width:75%">
        <div style="font-size:11px;color:#888;margin-bottom:4px;<?= $is_me ? 'text-align:right' : '' ?>">
          <?= h($c['f_tantosha_name']) ?> ／ <?= h(date('Y/m/d H:i', strtotime($c['f_created_at']))) ?>
        </div>
        <div style="background:<?= $is_me ? '#EEF3FA' : '#f7f9fc' ?>;border:1px solid <?= $is_me ? '#C5D3E8' : '#e0e8f0' ?>;border-radius:<?= $is_me ? '12px 0 12px 12px' : '0 12px 12px 12px' ?>;padding:10px 14px;font-size:13px;line-height:1.6;white-space:pre-wrap"><?= h($c['f_body']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
    <form method="post" action="comment.php" style="margin-top:14px;border-top:1px solid #e8eef5;padding-top:14px">
      <input type="hidden" name="nippo_id" value="<?= h($id) ?>">
      <div class="form-group">
        <label>コメントを追加</label>
        <textarea name="f_body" class="form-control" rows="2" placeholder="コメントを入力してください" required></textarea>
      </div>
      <div style="text-align:right">
        <button type="submit" class="btn btn-primary btn-sm">送信</button>
      </div>
    </form>
  </div>
</div>

</div>
<?= html_footer() ?>
