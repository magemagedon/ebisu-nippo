<?php
function html_header($title = '業務システム') {
    return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title} | エビス紙料</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Kaku Gothic ProN','Meiryo',sans-serif;font-size:14px;background:#F0F4F8;color:#1a1a2e;min-height:100vh}
a{color:#2E75B6;text-decoration:none}
a:hover{text-decoration:underline}

/* ナビ */
.nav{background:#1B3A6B;padding:0 16px;display:flex;align-items:center;justify-content:space-between;height:52px;position:sticky;top:0;z-index:100;flex-wrap:wrap}
.nav-brand{color:#fff;font-size:15px;font-weight:700;letter-spacing:.5px;white-space:nowrap}
.nav-brand span{color:#7FB3E8;font-size:11px;font-weight:400;margin-left:6px}
.nav-menu{display:flex;align-items:center;gap:2px;flex-wrap:wrap}
.nav-link{color:#B8D4F0;font-size:12px;padding:6px 10px;border-radius:4px;transition:background .15s;white-space:nowrap}
.nav-link:hover,.nav-link.active{background:rgba(255,255,255,.12);color:#fff;text-decoration:none}
.nav-user{color:#B8D4F0;font-size:12px;display:flex;align-items:center;gap:8px;white-space:nowrap}
.nav-user strong{color:#fff}
.btn-logout{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff;padding:4px 10px;border-radius:4px;font-size:12px;cursor:pointer}
.btn-logout:hover{background:rgba(255,255,255,.2)}

/* コンテナ */
.container{max-width:1100px;margin:0 auto;padding:16px}
.page-title{font-size:18px;font-weight:700;color:#1B3A6B;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid #2E75B6}

/* カード */
.card{background:#fff;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:16px}
.card-header{background:#1B3A6B;color:#fff;padding:10px 16px;border-radius:8px 8px 0 0;font-weight:600;font-size:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.card-body{padding:16px}

/* ボタン */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:5px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .15s;text-decoration:none;white-space:nowrap}
.btn-primary{background:#1B3A6B;color:#fff}.btn-primary:hover{background:#2a4f8a;color:#fff}
.btn-blue{background:#2E75B6;color:#fff}.btn-blue:hover{background:#1a5a9a;color:#fff}
.btn-success{background:#2e7d32;color:#fff}.btn-success:hover{background:#1b5e20;color:#fff}
.btn-danger{background:#c62828;color:#fff}.btn-danger:hover{background:#8e1010;color:#fff}
.btn-warning{background:#e65100;color:#fff}.btn-warning:hover{background:#bf360c;color:#fff}
.btn-gray{background:#e0e0e0;color:#444}.btn-gray:hover{background:#bdbdbd}
.btn-sm{padding:5px 10px;font-size:12px}
.btn-block{display:flex;width:100%;justify-content:center;padding:12px}

/* 検索エリア */
.search-area{background:#EEF3FA;border:1px solid #C5D3E8;border-radius:6px;padding:12px 14px;margin-bottom:16px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.search-area label{font-size:12px;font-weight:600;color:#1B3A6B;white-space:nowrap}
.search-area input,.search-area select{font-size:13px;padding:6px 10px;border:1px solid #B8CBE0;border-radius:4px;background:#fff;color:#1a1a2e;max-width:100%}
.search-area input:focus,.search-area select:focus{outline:none;border-color:#2E75B6}

/* テーブル（PC） */
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse;min-width:500px}
thead tr{background:#1B3A6B}
thead th{padding:10px 12px;color:#fff;font-weight:600;font-size:13px;text-align:left;white-space:nowrap}
tbody tr{border-bottom:1px solid #E8EEF5;transition:background .1s}
tbody tr:nth-child(even){background:#F7F9FC}
tbody tr:hover{background:#EEF3FA}
tbody td{padding:10px 12px;font-size:13px;vertical-align:middle}

/* カードリスト（スマホ用日報一覧） */
.nippo-card{background:#fff;border:1px solid #E0E8F0;border-radius:8px;padding:14px;margin-bottom:10px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.nippo-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:6px}
.nippo-card-date{font-size:16px;font-weight:700;color:#1B3A6B}
.nippo-card-body{font-size:13px;color:#555;margin-bottom:10px}
.nippo-card-footer{display:flex;gap:8px;justify-content:flex-end}

/* ステータスバッジ */
.badge{display:inline-block;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap}
.badge-success{background:#e6f4ea;color:#1a7a3a;border:1px solid #a8d5b5}
.badge-warning{background:#fff8e1;color:#8a6200;border:1px solid #f0d080}
.badge-danger{background:#fdecea;color:#b71c1c;border:1px solid #f5a8a8}
.badge-info{background:#e3f2fd;color:#0d47a1;border:1px solid #90caf9}

/* フォーム */
.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:12px;font-weight:600;color:#1B3A6B;margin-bottom:5px}
.form-control{width:100%;padding:8px 12px;border:1px solid #C5D3E8;border-radius:5px;font-size:14px;font-family:inherit;color:#1a1a2e;transition:border .15s}
.form-control:focus{outline:none;border-color:#2E75B6;box-shadow:0 0 0 2px rgba(46,117,182,.15)}
textarea.form-control{resize:vertical;min-height:80px}
select.form-control{background:#fff}

/* アラート */
.alert{padding:10px 16px;border-radius:5px;margin-bottom:14px;font-size:13px}
.alert-success{background:#e6f4ea;color:#1a7a3a;border:1px solid #a8d5b5}
.alert-danger{background:#fdecea;color:#b71c1c;border:1px solid #f5a8a8}
.alert-warning{background:#fff8e1;color:#8a6200;border:1px solid #f0d080}

/* 明細 */
.meisai-row{background:#f7f9fc;border:1px solid #e0e8f0;border-radius:6px;padding:12px 14px;margin-bottom:10px;position:relative}
.meisai-row .meisai-num{position:absolute;top:10px;left:10px;background:#2E75B6;color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700}
.meisai-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding-left:28px}
.meisai-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;padding-left:28px}
.del-btn{position:absolute;top:10px;right:10px;background:#fdecea;border:1px solid #f5a8a8;color:#b71c1c;border-radius:4px;padding:2px 8px;font-size:11px;cursor:pointer}
.del-btn:hover{background:#f5a8a8}

/* フッター */
.footer{text-align:center;color:#999;font-size:11px;padding:20px;margin-top:20px}

/* ========== スマホ対応 ========== */
@media (max-width: 600px) {
  .nav{height:auto;padding:8px 12px;gap:6px}
  .nav-brand span{display:none}
  .nav-user strong{display:none}
  .nav-menu{gap:0}
  .nav-link{padding:5px 8px;font-size:11px}

  .container{padding:10px}
  .page-title{font-size:16px;margin-bottom:12px}

  .card-header{font-size:13px;padding:10px 12px}
  .card-body{padding:12px}

  /* 検索エリアをスマホで縦並び */
  .search-area{flex-direction:column;align-items:stretch}
  .search-area input,.search-area select{width:100%}
  .search-area .btn{width:100%;justify-content:center}

  /* テーブルをカード形式に切り替え */
  .pc-only{display:none}
  .sp-only{display:block}

  /* 明細グリッドをスマホで1列 */
  .meisai-grid{grid-template-columns:1fr}
  .meisai-grid-3{grid-template-columns:1fr}

  /* ボタン全幅 */
  .btn-sp-block{display:flex;width:100%;justify-content:center;margin-bottom:8px}

  /* 詳細画面のグリッド */
  .detail-grid{grid-template-columns:1fr 1fr !important}
}

@media (max-width: 400px) {
  .detail-grid{grid-template-columns:1fr !important}
}
</style>
</head>
<body>
HTML;
}

function nav_bar() {
    $name   = $_SESSION['tantosha_name'] ?? '';
    $kengen = $_SESSION['kengen'] ?? '';
    $current = basename($_SERVER['PHP_SELF']);

    // 大メニュー3本（業務システム／工場管理／事故報告）＋各配下のリンク
    $categories = [
        '業務システム' => [
            'oshirase.php' => 'お知らせ', 'dashboard.php' => 'ダッシュボード', 'index.php' => '日報一覧',
            'actions.php' => 'アクション', 'calendar.php' => 'カレンダー', 'history.php' => '訪問履歴',
            'chart.php' => '相場グラフ', 'todo.php' => 'ToDo', 'create.php' => '新規日報',
        ],
        '工場管理' => [
            'kj_dashboard.php' => 'ダッシュボード', 'kj_equipment.php' => '設備マスタ', 'kj_check.php' => '日次チェック',
            'kj_issue.php' => '不具合・行動計画', 'kj_maintenance.php' => '保守・交換スケジュール',
            'kj_patrol.php' => '安全パトロール', 'kj_process.php' => '工程表連動ビュー',
        ],
        '事故報告' => [
            'jiko.php' => '事故報告一覧・登録',
        ],
    ];
    if ($kengen === '管理者') {
        $categories['業務システム']['uriage.php'] = '経営分析';
        $categories['業務システム']['master.php'] = 'マスタ管理';
    }
    $cat_icon = ['業務システム' => '📋', '工場管理' => '🏭', '事故報告' => '🚨'];

    $nav = '
<style>
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:6px;background:none;border:none}
.hamburger span{display:block;width:22px;height:2px;background:#fff;border-radius:2px;transition:all .3s}
.hamburger.open span:nth-child(1){transform:rotate(45deg) translate(5px,5px)}
.hamburger.open span:nth-child(2){opacity:0}
.hamburger.open span:nth-child(3){transform:rotate(-45deg) translate(5px,-5px)}
.sp-menu{display:none;position:fixed;top:52px;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:200}
.sp-menu.open{display:block}
.sp-menu-inner{background:#1B3A6B;padding:8px 0;max-height:calc(100vh - 52px);overflow-y:auto}
.sp-menu-inner a{display:block;color:#B8D4F0;padding:10px 20px 10px 32px;font-size:14px;border-bottom:1px solid rgba(255,255,255,.08);text-decoration:none}
.sp-menu-inner a:hover,.sp-menu-inner a.active{background:rgba(255,255,255,.12);color:#fff}
.sp-menu-inner .sp-cat{padding:12px 20px 6px;color:#7FB3E8;font-size:11px;font-weight:700;letter-spacing:.5px}
.sp-menu-inner .sp-user{padding:12px 20px;color:#7FB3E8;font-size:12px;border-bottom:1px solid rgba(255,255,255,.08)}
.sp-menu-inner .sp-logout{display:block;margin:12px 16px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:4px;padding:8px;font-size:13px;cursor:pointer;text-align:center;width:calc(100% - 32px)}

/* 大メニュー（PC：プルダウンにせず、カテゴリ見出し＋区切り線で常時フラット表示） */
.nav-cat-label{color:#7FB3E8;font-size:10px;font-weight:700;padding:8px 4px 8px 10px;white-space:nowrap;display:inline-flex;align-items:center}
.nav-cat-sep{width:1px;align-self:stretch;background:rgba(255,255,255,.15);margin:6px 2px}
@media(max-width:600px){
  .hamburger{display:flex}
  .nav-menu,.nav-user{display:none}
}
</style>
<nav class="nav">
  <div style="display:flex;align-items:center;gap:12px">
    <div class="nav-brand">エビス紙料<span>業務システム</span></div>
    <div class="nav-menu">
';
    $first_cat = true;
    foreach ($categories as $cat => $items) {
        if (!$first_cat) { $nav .= "<span class='nav-cat-sep'></span>"; }
        $first_cat = false;
        $nav .= "<span class='nav-cat-label'>{$cat_icon[$cat]} {$cat}</span>";
        foreach ($items as $file => $label) {
            $active = ($current === $file) ? ' active' : '';
            $nav .= "<a href='{$file}' class='nav-link{$active}'>{$label}</a>";
        }
    }
    $nav .= '
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <div class="nav-user">
';
    $nav .= "<strong style='color:#fff'>{$name}</strong><span style='color:#B8D4F0'>（{$kengen}）</span>";
    $nav .= '
      <form method="post" action="logout.php" style="display:inline"><button class="btn-logout" type="submit">ログアウト</button></form>
    </div>
    <button class="hamburger" id="hamburger" onclick="toggleMenu()">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- スマホ用メニュー -->
<div class="sp-menu" id="spMenu" onclick="closeMenu()">
  <div class="sp-menu-inner" onclick="event.stopPropagation()">
';
    $nav .= "<div class='sp-user'>{$name}（{$kengen}）</div>";
    foreach ($categories as $cat => $items) {
        $nav .= "<div class='sp-cat'>{$cat_icon[$cat]} {$cat}</div>";
        foreach ($items as $file => $label) {
            $active = ($current === $file) ? ' active' : '';
            $nav .= "<a href='{$file}' class='{$active}'>{$label}</a>";
        }
    }
    $nav .= '
    <form method="post" action="logout.php"><button class="sp-logout" type="submit">ログアウト</button></form>
  </div>
</div>

<script>
function toggleMenu() {
    document.getElementById("hamburger").classList.toggle("open");
    document.getElementById("spMenu").classList.toggle("open");
    document.body.style.overflow = document.getElementById("spMenu").classList.contains("open") ? "hidden" : "";
}
function closeMenu() {
    document.getElementById("hamburger").classList.remove("open");
    document.getElementById("spMenu").classList.remove("open");
    document.body.style.overflow = "";
}
</script>
';
    return $nav;
}

function html_footer() {
    return '<div class="footer">© エビス紙料株式会社　業務システム</div></body></html>';
}

function status_badge($status) {
    $map = ['確認済' => 'badge-success', '未確認' => 'badge-warning', '差し戻し' => 'badge-danger'];
    $cls = $map[$status] ?? 'badge-info';
    return "<span class='badge {$cls}'>{$status}</span>";
}

function juchu_badge($status) {
    $map = ['受注' => 'badge-success', '見込み' => 'badge-info', '継続フォロー' => 'badge-warning', '失注' => 'badge-danger', '情報収集' => 'badge-info'];
    $cls = $map[$status] ?? 'badge-info';
    return "<span class='badge {$cls}'>{$status}</span>";
}

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * 検索用カナ正規化
 * ひらがな→全角カタカナ、半角カナ→全角、全角英数→半角、空白除去、英字は大文字化
 */
function normalize_kana($str) {
    $s = mb_convert_kana(trim((string)$str), 'KVCas', 'UTF-8');
    $s = preg_replace('/[\s　]+/u', '', $s);
    return mb_strtoupper($s, 'UTF-8');
}

/** 五十音 行 → 先頭文字の正規表現（全角カタカナ） */
function gojuon_rows() {
    return [
        'あ' => '^[ァ-オヴ]',
        'か' => '^[カ-ゴヵヶ]',
        'さ' => '^[サ-ゾ]',
        'た' => '^[タ-ド]',
        'な' => '^[ナ-ノ]',
        'は' => '^[ハ-ポ]',
        'ま' => '^[マ-モ]',
        'や' => '^[ャ-ヨ]',
        'ら' => '^[ラ-ロ]',
        'わ' => '^[ヮ-ン]',
        'A'  => '^[A-Z0-9]',
    ];
}

/** 取引先の地図URL（未設定なら住所からGoogleマップ検索） */
function map_url($row) {
    if (!empty($row['f_map_url'])) return $row['f_map_url'];
    $addr = trim((string)($row['f_address'] ?? ''));
    return $addr === '' ? '' : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($addr);
}

/* ===================== 権限（部門管理者）関連 ===================== */

/** 全部門を横断して閲覧・承認できるか（管理者のみ） */
function is_admin() {
    return ($_SESSION['kengen'] ?? '') === '管理者';
}

/** 自部門に限り閲覧・承認できるか */
function is_bumon_kanri() {
    return ($_SESSION['kengen'] ?? '') === '部門管理者';
}

/**
 * ログイン中ユーザーが閲覧・操作できる担当者IDの一覧を返す。
 * - 管理者      : null（全件・制限なし）
 * - 部門管理者  : 自分の所属部署に属する担当者ID一覧（部署未設定の場合は自分のみ）
 * - 一般        : 自分のIDのみ
 */
function visible_tantosha_ids($pdo) {
    static $cache = null;
    if ($cache !== null) return $cache;

    if (is_admin()) return $cache = null;

    $my_id = $_SESSION['tantosha_id'] ?? '';

    if (is_bumon_kanri()) {
        $stmt = $pdo->prepare("SELECT fk_busho_id FROM t_tantosha WHERE pk_tantosha_id = ?");
        $stmt->execute([$my_id]);
        $busho_id = $stmt->fetchColumn();
        if ($busho_id) {
            $stmt2 = $pdo->prepare("SELECT pk_tantosha_id FROM t_tantosha WHERE fk_busho_id = ?");
            $stmt2->execute([$busho_id]);
            $ids = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            if ($ids) return $cache = $ids;
        }
        return $cache = [$my_id];
    }

    return $cache = [$my_id];
}

/** 指定の担当者（日報の作成者等）を、ログイン中ユーザーが閲覧・承認できるか */
function can_manage_tantosha($pdo, $target_tantosha_id) {
    $ids = visible_tantosha_ids($pdo);
    if ($ids === null) return true;
    return in_array($target_tantosha_id, $ids, true);
}

/**
 * 担当者IDで絞り込むWHERE句の断片とパラメータを返す。
 * 管理者の場合は絞り込みなし（空文字・空配列）。
 * 使い方: [$cond, $ps] = visible_tantosha_where($pdo, 'h.fk_tantosha_id'); if($cond) { $where[]=$cond; array_push($params, ...$ps); }
 */
function visible_tantosha_where($pdo, $column) {
    $ids = visible_tantosha_ids($pdo);
    if ($ids === null) return ['', []];
    if (empty($ids)) return ["{$column} = ''", []]; // 該当者なし
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return ["{$column} IN ({$placeholders})", $ids];
}
