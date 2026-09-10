<?php
require_once 'db.php';
require_once 'common.php';

$pdo = get_db();
$id  = $_GET['id'] ?? '';
$nippo = null;
$meisais = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM t_nippo_header WHERE pk_nippo_id = ?");
    $stmt->execute([$id]);
    $nippo = $stmt->fetch();
    if ($nippo && $nippo['fk_tantosha_id'] !== $_SESSION['tantosha_id'] && $_SESSION['kengen'] !== '管理者') {
        header('Location: index.php'); exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM t_nippo_meisai WHERE fk_nippo_id = ? ORDER BY f_created_at");
    $stmt->execute([$id]);
    $meisais = $stmt->fetchAll();
}

$torihikisaki = $pdo->query("SELECT * FROM t_torihikisaki ORDER BY f_torihikisaki_name")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f_date = $_POST['f_date'] ?? '';
    $f_biko = $_POST['f_biko'] ?? '';

    if (!$f_date) $errors[] = '日付を入力してください。';

    $meisai_list = [];
    $homonsaki = $_POST['f_homonsakimei'] ?? [];
    foreach ($homonsaki as $i => $hm) {
        if (trim($hm) === '') continue;
        $meisai_list[] = [
            'fk_torihikisaki_id'  => $_POST['fk_torihikisaki_id'][$i] ?? null,
            'f_homonsakimei'      => $hm,
            'f_saki_tantosha'     => $_POST['f_saki_tantosha'][$i] ?? '',
            'f_taiou_naiyo'       => $_POST['f_taiou_naiyo'][$i] ?? '',
            'f_juchu_mikomikubun' => $_POST['f_juchu_mikomikubun'][$i] ?? '継続フォロー',
            'f_jikai_action'      => $_POST['f_jikai_action'][$i] ?? '',
            'f_jikai_yoteibi'     => $_POST['f_jikai_yoteibi'][$i] ?: null,
            'f_furushi_soba'      => $_POST['f_furushi_soba'][$i] ?: null,
            'f_kaishu_ryo'        => $_POST['f_kaishu_ryo'][$i] ?: null,
            'f_tanka'             => $_POST['f_tanka'][$i] ?: null,
        ];
    }
    if (empty($meisai_list)) $errors[] = '訪問先を1件以上入力してください。';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            if ($id && $nippo) {
                $pdo->prepare("UPDATE t_nippo_header SET f_date=?, f_biko=?, f_updated_at=NOW() WHERE pk_nippo_id=?")
                    ->execute([$f_date, $f_biko, $id]);
                $pdo->prepare("DELETE FROM t_nippo_meisai WHERE fk_nippo_id=?")->execute([$id]);
                $nippo_id = $id;
            } else {
                $nippo_id = generate_uuid();
                $pdo->prepare("INSERT INTO t_nippo_header (pk_nippo_id,fk_tantosha_id,f_date,f_biko,f_created_at,f_updated_at) VALUES (?,?,?,?,NOW(),NOW())")
                    ->execute([$nippo_id, $_SESSION['tantosha_id'], $f_date, $f_biko]);
            }
            $stmt = $pdo->prepare("INSERT INTO t_nippo_meisai (pk_meisai_id,fk_nippo_id,fk_torihikisaki_id,f_homonsakimei,f_saki_tantosha,f_taiou_naiyo,f_juchu_mikomikubun,f_jikai_action,f_jikai_yoteibi,f_furushi_soba,f_kaishu_ryo,f_tanka,f_created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            foreach ($meisai_list as $m) {
                $stmt->execute([generate_uuid(), $nippo_id, $m['fk_torihikisaki_id']?:null, $m['f_homonsakimei'], $m['f_saki_tantosha'], $m['f_taiou_naiyo'], $m['f_juchu_mikomikubun'], $m['f_jikai_action'], $m['f_jikai_yoteibi'], $m['f_furushi_soba'], $m['f_kaishu_ryo'], $m['f_tanka']]);
            }
            $pdo->commit();
            header('Location: index.php?msg=' . urlencode('日報を保存しました'));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
    $f_date_val = $f_date;
    $f_biko_val = $f_biko;
} else {
    $f_date_val = $nippo ? $nippo['f_date'] : date('Y-m-d');
    $f_biko_val = $nippo ? $nippo['f_biko'] : '';
}

echo html_header($id ? '日報編集' : '新規日報');
echo nav_bar();
?>
<div class="container">
  <div class="page-title"><?= $id ? '日報編集' : '新規日報作成' ?></div>

  <?php foreach($errors as $e): ?>
  <div class="alert alert-danger"><?= h($e) ?></div>
  <?php endforeach; ?>

  <form method="post" id="nippoForm">
  <div class="card">
    <div class="card-header">基本情報</div>
    <div class="card-body">
      <div class="form-group">
        <label>日付 <span style="color:#c62828">*</span></label>
        <input type="date" name="f_date" class="form-control" value="<?= h($f_date_val) ?>" required>
      </div>
      <div class="form-group">
        <label>備考</label>
        <input type="text" name="f_biko" class="form-control" value="<?= h($f_biko_val) ?>" placeholder="特記事項があれば入力">
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      訪問先明細
      <button type="button" onclick="addMeisai()" class="btn btn-blue btn-sm">＋ 追加</button>
    </div>
    <div class="card-body" id="meisaiList">
    <?php
    $init_meisais = !empty($meisais) ? $meisais : [[]];
    foreach($init_meisais as $idx => $m):
    ?>
      <div class="meisai-row" id="meisai_<?= $idx ?>">
        <span class="meisai-num"><?= $idx+1 ?></span>
        <?php if($idx > 0 || !empty($meisais)): ?>
        <button type="button" class="del-btn" onclick="delMeisai(this)">削除</button>
        <?php endif; ?>
        <div style="padding-left:28px">
          <div class="form-group">
            <label>取引先から選択</label>
            <select name="fk_torihikisaki_id[]" class="form-control" onchange="fillHomonsaki(this,<?= $idx ?>)">
              <option value="">-- 選択 --</option>
              <?php foreach($torihikisaki as $t): ?>
              <option value="<?= h($t['pk_torihikisaki_id']) ?>" <?= ($m['fk_torihikisaki_id']??'')===$t['pk_torihikisaki_id']?'selected':'' ?>>
                <?= h($t['f_torihikisaki_name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>訪問先名 <span style="color:#c62828">*</span></label>
            <input type="text" name="f_homonsakimei[]" class="form-control" value="<?= h($m['f_homonsakimei']??'') ?>" placeholder="訪問先名を入力" id="hmn_<?= $idx ?>">
          </div>
          <div class="form-group">
            <label>先方担当者</label>
            <input type="text" name="f_saki_tantosha[]" class="form-control" value="<?= h($m['f_saki_tantosha']??'') ?>" id="st_<?= $idx ?>">
          </div>
          <div class="form-group">
            <label>受注見込み区分</label>
            <select name="f_juchu_mikomikubun[]" class="form-control">
              <?php foreach(['受注','見込み','継続フォロー','失注','情報収集'] as $s): ?>
              <option value="<?= $s ?>" <?= ($m['f_juchu_mikomikubun']??'継続フォロー')===$s?'selected':'' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>対応内容</label>
            <textarea name="f_taiou_naiyo[]" class="form-control" rows="3" id="taiou_<?= $idx ?>"><?= h($m['f_taiou_naiyo']??'') ?></textarea>
<button type="button" id="voiceBtn_taiou_<?= $idx ?>" onclick="startVoice('taiou_'+<?= $idx ?>)" style="margin-top:4px;background:#2E75B6;color:#fff;border:none;border-radius:4px;padding:5px 12px;font-size:12px;cursor:pointer">🎤 音声入力</button>
          </div>
          <div class="form-group">
            <label>次回アクション</label>
            <div style="display:flex;gap:6px;align-items:center"><input type="text" name="f_jikai_action[]" class="form-control" id="action_<?= $idx ?>" value="<?= h($m['f_jikai_action']??'')?>" ><button type="button" onclick="startVoice('action_'+<?= $idx ?>)" style="background:#2E75B6;color:#fff;border:none;border-radius:4px;padding:5px 10px;font-size:12px;cursor:pointer;white-space:nowrap;flex-shrink:0">🎤</button></div>
          </div>
          <div class="form-group">
            <label>次回予定日</label>
            <input type="date" name="f_jikai_yoteibi[]" class="form-control" value="<?= h($m['f_jikai_yoteibi']??'') ?>">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
            <div class="form-group" style="margin:0">
              <label>古紙相場<br><small>（円/t）</small></label>
              <input type="number" name="f_furushi_soba[]" class="form-control" value="<?= h($m['f_furushi_soba']??'') ?>" step="100">
            </div>
            <div class="form-group" style="margin:0">
              <label>回収量<br><small>（t）</small></label>
              <input type="number" name="f_kaishu_ryo[]" class="form-control" value="<?= h($m['f_kaishu_ryo']??'') ?>" step="0.1">
            </div>
            <div class="form-group" style="margin:0">
              <label>単価<br><small>（円/t）</small></label>
              <input type="number" name="f_tanka[]" class="form-control" value="<?= h($m['f_tanka']??'') ?>" step="100">
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:30px">
    <button type="submit" class="btn btn-primary btn-block">保存する</button>
    <a href="index.php" class="btn btn-gray btn-block">キャンセル</a>
  </div>
  </form>
</div>

<style>
@keyframes recPulse {
    0%   { box-shadow: 0 0 0 0 rgba(198,40,40,0.7); transform: scale(1); }
    50%  { box-shadow: 0 0 0 8px rgba(198,40,40,0); transform: scale(1.05); }
    100% { box-shadow: 0 0 0 0 rgba(198,40,40,0); transform: scale(1); }
}
.rec-dot {
    display:inline-block;width:8px;height:8px;background:#fff;
    border-radius:50%;margin-right:4px;animation:recPulse 0.8s infinite;
}
button[disabled] { cursor:not-allowed !important; }
</style>
<script>
const torihikisaki = <?= json_encode(array_column($torihikisaki, null, 'pk_torihikisaki_id'), JSON_UNESCAPED_UNICODE) ?>;
let meisaiCount = <?= count($init_meisais) ?>;

function addMeisai() {
    const idx = meisaiCount++;
    const div = document.createElement('div');
    div.className = 'meisai-row';
    div.id = 'meisai_' + idx;
    div.innerHTML = `
    <span class="meisai-num">${document.querySelectorAll('.meisai-row').length + 1}</span>
    <button type="button" class="del-btn" onclick="delMeisai(this)">削除</button>
    <div style="padding-left:28px">
      <div class="form-group"><label>取引先から選択</label>
        <select name="fk_torihikisaki_id[]" class="form-control" onchange="fillHomonsaki(this,${idx})">
          <option value="">-- 選択 --</option>
          ${Object.values(torihikisaki).map(t=>`<option value="${t.pk_torihikisaki_id}">${t.f_torihikisaki_name}</option>`).join('')}
        </select>
      </div>
      <div class="form-group"><label>訪問先名 <span style="color:#c62828">*</span></label>
        <input type="text" name="f_homonsakimei[]" class="form-control" id="hmn_${idx}" placeholder="訪問先名を入力">
      </div>
      <div class="form-group"><label>先方担当者</label>
        <input type="text" name="f_saki_tantosha[]" class="form-control" id="st_${idx}">
      </div>
      <div class="form-group"><label>受注見込み区分</label>
        <select name="f_juchu_mikomikubun[]" class="form-control">
          <option>受注</option><option>見込み</option><option selected>継続フォロー</option><option>失注</option><option>情報収集</option>
        </select>
      </div>
      <div class="form-group"><label>対応内容</label>
        <textarea name="f_taiou_naiyo[]" class="form-control" rows="3" id="taiou_<?= $idx ?>"></textarea>
<button type="button" id="voiceBtn_taiou_<?= $idx ?>" onclick="startVoice('taiou_'+<?= $idx ?>)" style="margin-top:4px;background:#2E75B6;color:#fff;border:none;border-radius:4px;padding:5px 12px;font-size:12px;cursor:pointer">🎤 音声入力</button>
      </div>
      <div class="form-group"><label>次回アクション</label>
        <div style="display:flex;gap:6px;align-items:center"><input type="text" name="f_jikai_action[]" class="form-control" id="action_<?= $idx ?>" value="<?= h($m['f_jikai_action']??'')?>" ><button type="button" onclick="startVoice('action_'+<?= $idx ?>)" style="background:#2E75B6;color:#fff;border:none;border-radius:4px;padding:5px 10px;font-size:12px;cursor:pointer;white-space:nowrap;flex-shrink:0">🎤</button></div>
      </div>
      <div class="form-group"><label>次回予定日</label>
        <input type="date" name="f_jikai_yoteibi[]" class="form-control">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
        <div class="form-group" style="margin:0"><label>古紙相場<br><small>（円/t）</small></label><input type="number" name="f_furushi_soba[]" class="form-control" step="100"></div>
        <div class="form-group" style="margin:0"><label>回収量<br><small>（t）</small></label><input type="number" name="f_kaishu_ryo[]" class="form-control" step="0.1"></div>
        <div class="form-group" style="margin:0"><label>単価<br><small>（円/t）</small></label><input type="number" name="f_tanka[]" class="form-control" step="100"></div>
      </div>
    </div>`;
    document.getElementById('meisaiList').appendChild(div);
    renumberMeisai();
    div.scrollIntoView({behavior:'smooth', block:'start'});
}

function delMeisai(btn) {
    btn.closest('.meisai-row').remove();
    renumberMeisai();
}

function renumberMeisai() {
    document.querySelectorAll('.meisai-row .meisai-num').forEach((el,i) => el.textContent = i+1);
}

function fillHomonsaki(sel, idx) {
    const t = torihikisaki[sel.value];
    if (!t) return;
    const hm = document.querySelector(`#meisai_${idx} input[name="f_homonsakimei[]"]`);
    const st = document.querySelector(`#meisai_${idx} input[name="f_saki_tantosha[]"]`);
    if (hm && !hm.value) hm.value = t.f_torihikisaki_name;
    if (st && !st.value) st.value = t.f_tantosha_name || '';
}
</script>
<?= html_footer() ?>
<style>
@keyframes recPulse {
    0%   { box-shadow: 0 0 0 0 rgba(198,40,40,0.7); transform: scale(1); }
    50%  { box-shadow: 0 0 0 8px rgba(198,40,40,0); transform: scale(1.05); }
    100% { box-shadow: 0 0 0 0 rgba(198,40,40,0); transform: scale(1); }
}
.rec-dot {
    display:inline-block;width:8px;height:8px;background:#fff;
    border-radius:50%;margin-right:4px;animation:recPulse 0.8s infinite;
}
button[disabled] { cursor:not-allowed !important; }
</style>
<script>
// 音声入力
function startVoice(targetId) {
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        alert('お使いのブラウザは音声入力に対応していません。iPhoneのSafariをお使いください。');
        return;
    }
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const recognition = new SpeechRecognition();
    recognition.lang = 'ja-JP';
    recognition.continuous = false;
    recognition.interimResults = false;

    const btn = document.getElementById('voiceBtn_' + targetId);
    const target = document.getElementById(targetId);
    btn.textContent = '🔴 録音中...';
    btn.style.background = '#c62828';
    btn.disabled = true;

    recognition.start();

    recognition.onresult = function(e) {
        const transcript = e.results[0][0].transcript;
        target.value += (target.value ? '\n' : '') + transcript;
        btn.textContent = '🎤 音声入力';
        btn.style.background = '#2E75B6';
        btn.disabled = false;
    };

    recognition.onerror = function(e) {
        alert('音声認識エラー：' + e.error);
        btn.textContent = '🎤 音声入力';
        btn.style.background = '#2E75B6';
        btn.disabled = false;
    };

    recognition.onend = function() {
        btn.textContent = '🎤 音声入力';
        btn.style.background = '#2E75B6';
        btn.disabled = false;
    };
}
</script>
