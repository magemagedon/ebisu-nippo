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
    $stmt = $pdo->prepare("SELECT m.*, tr.f_torihikisaki_name FROM t_nippo_meisai m LEFT JOIN t_torihikisaki tr ON m.fk_torihikisaki_id = tr.pk_torihikisaki_id WHERE m.fk_nippo_id = ? ORDER BY m.f_created_at");
    $stmt->execute([$id]);
    $meisais = $stmt->fetchAll();
}

$segments = $pdo->query("SELECT f_segment_name FROM t_segment WHERE f_active='有効' ORDER BY f_sort_order,f_segment_name")->fetchAll(PDO::FETCH_COLUMN);

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
            'fk_kyoten_id'        => $_POST['fk_kyoten_id'][$i] ?? null,
            'fk_busho_id'         => $_POST['fk_busho_id'][$i] ?? null,
            'fk_saki_tantosha_id' => $_POST['fk_saki_tantosha_id'][$i] ?? null,
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
            $stmt = $pdo->prepare("INSERT INTO t_nippo_meisai (pk_meisai_id,fk_nippo_id,fk_torihikisaki_id,fk_kyoten_id,fk_busho_id,fk_saki_tantosha_id,f_homonsakimei,f_saki_tantosha,f_taiou_naiyo,f_juchu_mikomikubun,f_jikai_action,f_jikai_yoteibi,f_furushi_soba,f_kaishu_ryo,f_tanka,f_created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            foreach ($meisai_list as $m) {
                $stmt->execute([generate_uuid(), $nippo_id, $m['fk_torihikisaki_id']?:null, $m['fk_kyoten_id']?:null, $m['fk_busho_id']?:null, $m['fk_saki_tantosha_id']?:null, $m['f_homonsakimei'], $m['f_saki_tantosha'], $m['f_taiou_naiyo'], $m['f_juchu_mikomikubun'], $m['f_jikai_action'], $m['f_jikai_yoteibi'], $m['f_furushi_soba'], $m['f_kaishu_ryo'], $m['f_tanka']]);
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
    // エラー時は入力値で再描画
    $init_meisais = [];
    foreach ($homonsaki as $i => $hm) {
        $init_meisais[] = [
            'fk_torihikisaki_id'  => $_POST['fk_torihikisaki_id'][$i] ?? '',
            'fk_kyoten_id'        => $_POST['fk_kyoten_id'][$i] ?? '',
            'fk_busho_id'         => $_POST['fk_busho_id'][$i] ?? '',
            'fk_saki_tantosha_id' => $_POST['fk_saki_tantosha_id'][$i] ?? '',
            'f_torihikisaki_name' => $_POST['tori_name'][$i] ?? '',
            'f_homonsakimei'      => $hm,
            'f_saki_tantosha'     => $_POST['f_saki_tantosha'][$i] ?? '',
            'f_taiou_naiyo'       => $_POST['f_taiou_naiyo'][$i] ?? '',
            'f_juchu_mikomikubun' => $_POST['f_juchu_mikomikubun'][$i] ?? '継続フォロー',
            'f_jikai_action'      => $_POST['f_jikai_action'][$i] ?? '',
            'f_jikai_yoteibi'     => $_POST['f_jikai_yoteibi'][$i] ?? '',
            'f_furushi_soba'      => $_POST['f_furushi_soba'][$i] ?? '',
            'f_kaishu_ryo'        => $_POST['f_kaishu_ryo'][$i] ?? '',
            'f_tanka'             => $_POST['f_tanka'][$i] ?? '',
        ];
    }
} else {
    $f_date_val = $nippo ? $nippo['f_date'] : date('Y-m-d');
    $f_biko_val = $nippo ? $nippo['f_biko'] : '';
    $init_meisais = $meisais;
}
if (empty($init_meisais)) $init_meisais = [[]];

echo html_header($id ? '日報編集' : '新規日報');
echo nav_bar();
?>
<style>
/* 取引先検索 */
.tori-box{position:relative}
.tori-box>label{padding-right:56px}
.tori-tools{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:6px}
.tori-tools select{font-size:12px;padding:5px 8px;border:1px solid #C5D3E8;border-radius:4px;background:#fff}
.gojuon{display:flex;gap:3px;flex-wrap:wrap}
.gojuon button{font-size:12px;padding:4px 7px;border:1px solid #C5D3E8;background:#fff;border-radius:4px;cursor:pointer;color:#1B3A6B;min-width:28px}
.gojuon button.on{background:#1B3A6B;color:#fff;border-color:#1B3A6B}
.tori-input-wrap{position:relative}
.tori-list{position:absolute;left:0;right:0;top:100%;z-index:50;background:#fff;border:1px solid #C5D3E8;border-radius:0 0 6px 6px;box-shadow:0 4px 12px rgba(0,0,0,.12);max-height:280px;overflow-y:auto;display:none}
.tori-list.open{display:block}
.tori-item{padding:8px 12px;cursor:pointer;border-bottom:1px solid #EEF3FA;font-size:13px;display:flex;justify-content:space-between;gap:8px;align-items:center}
.tori-item:hover,.tori-item.active{background:#EEF3FA}
.tori-item .kana{color:#888;font-size:11px}
.tori-item .seg{font-size:10px;background:#e3f2fd;color:#0d47a1;border-radius:8px;padding:1px 7px;white-space:nowrap}
.tori-empty{padding:10px 12px;color:#999;font-size:12px}
.tori-selected{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:#E8F1FB;border:1px solid #B8CBE0;border-radius:5px;padding:7px 10px;margin-top:6px;font-size:13px}
.tori-selected strong{color:#1B3A6B}
.tori-selected .links a{font-size:11px;margin-right:8px}
.tori-selected .clear{margin-left:auto;background:none;border:none;color:#c62828;cursor:pointer;font-size:12px}
.sub-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px}
@media(max-width:600px){.sub-grid{grid-template-columns:1fr}}
.voice-btn{background:#2E75B6;color:#fff;border:none;border-radius:4px;padding:5px 12px;font-size:12px;cursor:pointer;white-space:nowrap;flex-shrink:0}
@keyframes recPulse{0%{box-shadow:0 0 0 0 rgba(198,40,40,.7)}50%{box-shadow:0 0 0 8px rgba(198,40,40,0)}100%{box-shadow:0 0 0 0 rgba(198,40,40,0)}}
button[disabled]{cursor:not-allowed!important}
</style>
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
    <div class="card-body" id="meisaiList"></div>
  </div>

  <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:30px">
    <button type="submit" class="btn btn-primary btn-block">保存する</button>
    <a href="index.php" class="btn btn-gray btn-block">キャンセル</a>
  </div>
  </form>
</div>

<script>
const SEGMENTS = <?= json_encode($segments, JSON_UNESCAPED_UNICODE) ?>;
const INIT_MEISAIS = <?= json_encode($init_meisais, JSON_UNESCAPED_UNICODE) ?>;
const JUCHU = ['受注','見込み','継続フォロー','失注','情報収集'];
const GOJUON = ['あ','か','さ','た','な','は','ま','や','ら','わ','A'];
let meisaiCount = 0;

function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function meisaiHtml(idx, m) {
    m = m || {};
    const selName = m.f_torihikisaki_name || '';
    return `
    <span class="meisai-num"></span>
    <button type="button" class="del-btn" onclick="delMeisai(this)">削除</button>
    <div style="padding-left:28px">
      <div class="form-group tori-box" id="toribox_${idx}">
        <label>取引先を検索 <small style="font-weight:400;color:#888">（カナ・社名・コードの一部を入力／五十音・セグメントで絞り込み）</small></label>
        <div class="tori-tools">
          <select id="seg_${idx}" onchange="toriSearch(${idx})">
            <option value="">全セグメント</option>
            ${SEGMENTS.map(s=>`<option value="${esc(s)}">${esc(s)}</option>`).join('')}
          </select>
          <div class="gojuon" id="gojuon_${idx}">
            ${GOJUON.map(r=>`<button type="button" data-row="${r}" onclick="toriRow(${idx},'${r}',this)">${r}</button>`).join('')}
          </div>
        </div>
        <div class="tori-input-wrap">
          <input type="text" class="form-control" id="toriq_${idx}" placeholder="例：たか　→　高松〇〇株式会社" autocomplete="off"
                 oninput="toriSearchDebounced(${idx})" onfocus="toriSearch(${idx})" onkeydown="toriKey(event,${idx})">
          <div class="tori-list" id="torilist_${idx}"></div>
        </div>
        <input type="hidden" name="fk_torihikisaki_id[]" id="torid_${idx}" value="${esc(m.fk_torihikisaki_id||'')}">
        <input type="hidden" name="tori_name[]" id="toriname_${idx}" value="${esc(selName)}">
        <div class="tori-selected" id="torisel_${idx}" style="display:${m.fk_torihikisaki_id?'flex':'none'}">
          <strong id="toriselname_${idx}">${esc(selName)}</strong>
          <span class="links" id="torilinks_${idx}"></span>
          <button type="button" class="clear" onclick="toriClear(${idx})">✕ 解除</button>
        </div>
        <div class="sub-grid" id="torisub_${idx}" style="display:${m.fk_torihikisaki_id?'grid':'none'}">
          <div><label style="font-size:11px;color:#1B3A6B;font-weight:600">支店・事業所</label>
            <select name="fk_kyoten_id[]" id="kyoten_${idx}" class="form-control" onchange="onKyotenChange(${idx})"><option value="">（本社）</option></select></div>
          <div><label style="font-size:11px;color:#1B3A6B;font-weight:600">部署</label>
            <select name="fk_busho_id[]" id="busho_${idx}" class="form-control" onchange="onBushoChange(${idx})"><option value="">（指定なし）</option></select></div>
          <div><label style="font-size:11px;color:#1B3A6B;font-weight:600">先方担当者</label>
            <select name="fk_saki_tantosha_id[]" id="sakit_${idx}" class="form-control" onchange="onSakiChange(${idx})"><option value="">（指定なし）</option></select></div>
        </div>
      </div>
      <div class="form-group">
        <label>訪問先名 <span style="color:#c62828">*</span></label>
        <input type="text" name="f_homonsakimei[]" class="form-control" id="hmn_${idx}" value="${esc(m.f_homonsakimei||'')}" placeholder="訪問先名を入力（取引先を選ぶと自動入力）">
      </div>
      <div class="form-group">
        <label>先方担当者</label>
        <input type="text" name="f_saki_tantosha[]" class="form-control" id="st_${idx}" value="${esc(m.f_saki_tantosha||'')}">
      </div>
      <div class="form-group">
        <label>受注見込み区分</label>
        <select name="f_juchu_mikomikubun[]" class="form-control">
          ${JUCHU.map(s=>`<option value="${s}" ${(m.f_juchu_mikomikubun||'継続フォロー')===s?'selected':''}>${s}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label>対応内容</label>
        <textarea name="f_taiou_naiyo[]" class="form-control" rows="3" id="taiou_${idx}">${esc(m.f_taiou_naiyo||'')}</textarea>
        <button type="button" class="voice-btn" id="voiceBtn_taiou_${idx}" onclick="startVoice('taiou_${idx}')" style="margin-top:4px">🎤 音声入力</button>
      </div>
      <div class="form-group">
        <label>次回アクション</label>
        <div style="display:flex;gap:6px;align-items:center">
          <input type="text" name="f_jikai_action[]" class="form-control" id="action_${idx}" value="${esc(m.f_jikai_action||'')}">
          <button type="button" class="voice-btn" id="voiceBtn_action_${idx}" onclick="startVoice('action_${idx}')">🎤</button>
        </div>
      </div>
      <div class="form-group">
        <label>次回予定日</label>
        <input type="date" name="f_jikai_yoteibi[]" class="form-control" value="${esc(m.f_jikai_yoteibi||'')}">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
        <div class="form-group" style="margin:0"><label>古紙相場<br><small>（円/t）</small></label><input type="number" name="f_furushi_soba[]" class="form-control" value="${esc(m.f_furushi_soba||'')}" step="100"></div>
        <div class="form-group" style="margin:0"><label>回収量<br><small>（t）</small></label><input type="number" name="f_kaishu_ryo[]" class="form-control" value="${esc(m.f_kaishu_ryo||'')}" step="0.1"></div>
        <div class="form-group" style="margin:0"><label>単価<br><small>（円/t）</small></label><input type="number" name="f_tanka[]" class="form-control" value="${esc(m.f_tanka||'')}" step="100"></div>
      </div>
    </div>`;
}

function addMeisai(m, scroll = true) {
    const idx = meisaiCount++;
    const div = document.createElement('div');
    div.className = 'meisai-row';
    div.id = 'meisai_' + idx;
    div.innerHTML = meisaiHtml(idx, m);
    document.getElementById('meisaiList').appendChild(div);
    renumberMeisai();
    if (m && m.fk_torihikisaki_id) {
        loadToriDetail(idx, m.fk_torihikisaki_id, {kyoten: m.fk_kyoten_id, busho: m.fk_busho_id, saki: m.fk_saki_tantosha_id, keepText: true});
    }
    if (scroll) div.scrollIntoView({behavior:'smooth', block:'start'});
}
function delMeisai(btn) {
    if (document.querySelectorAll('.meisai-row').length <= 1) { alert('訪問先は1件以上必要です。'); return; }
    btn.closest('.meisai-row').remove();
    renumberMeisai();
}
function renumberMeisai() {
    document.querySelectorAll('.meisai-row .meisai-num').forEach((el,i) => el.textContent = i+1);
}

/* ===== 取引先検索 ===== */
const toriState = {};  // idx -> {row, timer, items, active}
function st(idx){ return toriState[idx] || (toriState[idx] = {row:'', timer:null, items:[], active:-1}); }

function toriSearchDebounced(idx) {
    const s = st(idx);
    clearTimeout(s.timer);
    s.timer = setTimeout(() => toriSearch(idx), 180);
}
function toriRow(idx, row, btn) {
    const s = st(idx);
    s.row = (s.row === row) ? '' : row;
    document.querySelectorAll(`#gojuon_${idx} button`).forEach(b => b.classList.toggle('on', b.dataset.row === s.row));
    toriSearch(idx);
}
async function toriSearch(idx) {
    const s = st(idx);
    const q   = document.getElementById(`toriq_${idx}`).value.trim();
    const seg = document.getElementById(`seg_${idx}`).value;
    const list = document.getElementById(`torilist_${idx}`);
    if (q === '' && s.row === '' && seg === '') { list.classList.remove('open'); list.innerHTML=''; return; }
    const url = `api_torihikisaki.php?action=search&q=${encodeURIComponent(q)}&seg=${encodeURIComponent(seg)}&row=${encodeURIComponent(s.row)}&limit=30`;
    let data;
    try { data = await (await fetch(url)).json(); } catch(e) { return; }
    s.items = data.items || []; s.active = -1;
    if (!s.items.length) {
        list.innerHTML = `<div class="tori-empty">該当する取引先がありません</div>`;
    } else {
        list.innerHTML = s.items.map((t,i)=>`
          <div class="tori-item" onmousedown="toriPick(${idx},${i})">
            <div><div>${esc(t.name)}</div><div class="kana">${esc(t.kana||'')}${t.code?'　['+esc(t.code)+']':''}${t.address?'　'+esc(t.address):''}</div></div>
            ${t.segment?`<span class="seg">${esc(t.segment)}</span>`:''}
          </div>`).join('')
          + (data.total > s.items.length ? `<div class="tori-empty">他 ${data.total - s.items.length} 件（さらに絞り込んでください）</div>` : '');
    }
    list.classList.add('open');
}
function toriKey(e, idx) {
    const s = st(idx);
    const list = document.getElementById(`torilist_${idx}`);
    if (!list.classList.contains('open')) return;
    const items = list.querySelectorAll('.tori-item');
    if (e.key === 'ArrowDown') { e.preventDefault(); s.active = Math.min(s.active+1, items.length-1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); s.active = Math.max(s.active-1, 0); }
    else if (e.key === 'Enter') { e.preventDefault(); if (s.active >= 0) toriPick(idx, s.active); return; }
    else if (e.key === 'Escape') { list.classList.remove('open'); return; }
    else return;
    items.forEach((el,i)=>el.classList.toggle('active', i===s.active));
    if (items[s.active]) items[s.active].scrollIntoView({block:'nearest'});
}
function toriPick(idx, i) {
    const t = st(idx).items[i];
    if (!t) return;
    document.getElementById(`torilist_${idx}`).classList.remove('open');
    document.getElementById(`toriq_${idx}`).value = '';
    document.getElementById(`torid_${idx}`).value = t.id;
    document.getElementById(`toriname_${idx}`).value = t.name;
    document.getElementById(`toriselname_${idx}`).textContent = t.name;
    document.getElementById(`torisel_${idx}`).style.display = 'flex';
    document.getElementById(`torisub_${idx}`).style.display = 'grid';
    const hm = document.getElementById(`hmn_${idx}`);
    if (!hm.value) hm.value = t.name;
    loadToriDetail(idx, t.id, {});
}
function toriClear(idx) {
    ['torid','toriname'].forEach(p => document.getElementById(`${p}_${idx}`).value = '');
    document.getElementById(`torisel_${idx}`).style.display = 'none';
    document.getElementById(`torisub_${idx}`).style.display = 'none';
    ['kyoten','busho','sakit'].forEach(p => document.getElementById(`${p}_${idx}`).innerHTML = '<option value=""></option>');
    document.getElementById(`toriq_${idx}`).focus();
}
document.addEventListener('click', e => {
    if (!e.target.closest('.tori-input-wrap')) document.querySelectorAll('.tori-list.open').forEach(l => l.classList.remove('open'));
});

/* ===== 拠点・部署・先方担当者の連動 ===== */
const toriDetail = {}; // idx -> detail
async function loadToriDetail(idx, id, opt) {
    let d;
    try { d = await (await fetch(`api_torihikisaki.php?action=detail&id=${encodeURIComponent(id)}`)).json(); } catch(e) { return; }
    if (d.error) return;
    toriDetail[idx] = d;
    if (opt.keepText) {
        document.getElementById(`toriselname_${idx}`).textContent = d.tori.name;
        document.getElementById(`toriname_${idx}`).value = d.tori.name;
    }
    // リンク
    const links = [];
    if (d.tori.map_url)    links.push(`<a href="${esc(d.tori.map_url)}" target="_blank" rel="noopener">📍 地図</a>`);
    if (d.tori.hp_url)     links.push(`<a href="${esc(d.tori.hp_url)}" target="_blank" rel="noopener">🌐 HP</a>`);
    if (d.tori.kessan_url) links.push(`<a href="${esc(d.tori.kessan_url)}" target="_blank" rel="noopener">📊 企業情報</a>`);
    if (d.tori.tel)        links.push(`<a href="tel:${esc(d.tori.tel)}">☎ ${esc(d.tori.tel)}</a>`);
    if (d.joken)           links.push(`<span title="回収:${esc(d.joken.f_kaishu_frequency||'')} / 支払:${esc(d.joken.f_payment_site||'')} ${esc(d.joken.f_payment_method||'')}" style="font-size:11px;color:#555;cursor:help">📄 取引条件</span>`);
    document.getElementById(`torilinks_${idx}`).innerHTML = links.join('');

    const ky = document.getElementById(`kyoten_${idx}`);
    ky.innerHTML = '<option value="">（本社）</option>' + d.kyoten.map(k=>`<option value="${esc(k.id)}">${esc(k.name)}</option>`).join('');
    ky.value = opt.kyoten || '';
    fillBusho(idx, opt.busho || '');
    fillSaki(idx, opt.saki || '');
    if (!opt.keepText) {
        // 主担当を既定で入れる
        const main = d.tantosha.find(t => t.main_flag == 1) || d.tantosha[0];
        const stEl = document.getElementById(`st_${idx}`);
        if (main && !stEl.value) { stEl.value = main.name; document.getElementById(`sakit_${idx}`).value = main.id; }
    }
}
function fillBusho(idx, val) {
    const d = toriDetail[idx]; if (!d) return;
    const ky = document.getElementById(`kyoten_${idx}`).value;
    const bs = document.getElementById(`busho_${idx}`);
    const list = d.busho.filter(b => !ky ? true : (b.kyoten_id === ky || !b.kyoten_id));
    bs.innerHTML = '<option value="">（指定なし）</option>' + list.map(b=>`<option value="${esc(b.id)}">${esc(b.name)}</option>`).join('');
    bs.value = val || '';
}
function fillSaki(idx, val) {
    const d = toriDetail[idx]; if (!d) return;
    const ky = document.getElementById(`kyoten_${idx}`).value;
    const bs = document.getElementById(`busho_${idx}`).value;
    const sk = document.getElementById(`sakit_${idx}`);
    let list = d.tantosha;
    if (bs) list = list.filter(t => t.busho_id === bs);
    else if (ky) list = list.filter(t => t.kyoten_id === ky || !t.kyoten_id);
    sk.innerHTML = '<option value="">（指定なし）</option>' + list.map(t=>`<option value="${esc(t.id)}">${esc(t.name)}${t.yakushoku?'（'+esc(t.yakushoku)+'）':''}</option>`).join('');
    sk.value = val || '';
}
function onKyotenChange(idx) {
    fillBusho(idx, ''); fillSaki(idx, '');
    const d = toriDetail[idx]; const ky = d.kyoten.find(k => k.id === document.getElementById(`kyoten_${idx}`).value);
    document.getElementById(`hmn_${idx}`).value = ky ? `${d.tori.name}　${ky.name}` : d.tori.name;
}
function onBushoChange(idx) { fillSaki(idx, ''); }
function onSakiChange(idx) {
    const d = toriDetail[idx]; const t = d.tantosha.find(t => t.id === document.getElementById(`sakit_${idx}`).value);
    document.getElementById(`st_${idx}`).value = t ? t.name : '';
}

/* ===== 音声入力 ===== */
function startVoice(targetId) {
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        alert('お使いのブラウザは音声入力に対応していません。iPhoneのSafariまたはChromeをお使いください。');
        return;
    }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    const rec = new SR();
    rec.lang = 'ja-JP'; rec.continuous = false; rec.interimResults = false;
    const btn = document.getElementById('voiceBtn_' + targetId);
    const target = document.getElementById(targetId);
    const orig = btn.textContent;
    const reset = () => { btn.textContent = orig; btn.style.background = '#2E75B6'; btn.style.animation = ''; btn.disabled = false; };
    btn.textContent = '🔴 録音中'; btn.style.background = '#c62828'; btn.style.animation = 'recPulse .8s infinite'; btn.disabled = true;
    rec.onresult = e => { const t = e.results[0][0].transcript; target.value += (target.value && target.tagName === 'TEXTAREA' ? '\n' : '') + t; reset(); };
    rec.onerror  = e => { if (e.error !== 'no-speech' && e.error !== 'aborted') alert('音声認識エラー：' + e.error); reset(); };
    rec.onend    = reset;
    rec.start();
}

/* 初期描画 */
INIT_MEISAIS.forEach(m => addMeisai(m, false));
</script>
<?= html_footer() ?>
