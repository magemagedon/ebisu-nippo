<?php
/**
 * 取引先検索・詳細 API（JSON）
 *
 *  GET ?action=search&q=たかまつ&seg=原料販売&row=か&limit=30
 *      → { items:[{id,code,name,kana,segment,address,tel,map_url,hp_url}], total }
 *  GET ?action=detail&id=xxxx
 *      → { tori:{...}, kyoten:[...], busho:[...], tantosha:[...] }
 *  GET ?action=segments
 *      → { items:[name,...] }
 */
require_once 'db.php';
require_once 'common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo    = get_db();
$action = $_GET['action'] ?? 'search';

function out($data) { echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }

function tori_row($r) {
    return [
        'id'      => $r['pk_torihikisaki_id'],
        'code'    => $r['f_code'],
        'name'    => $r['f_torihikisaki_name'],
        'kana'    => $r['f_torihikisaki_kana'],
        'segment' => $r['f_segment'],
        'address' => $r['f_address'],
        'tel'     => $r['f_tel'],
        'map_url' => map_url($r),
        'hp_url'  => $r['f_hp_url'],
        'kessan_url' => $r['f_kessan_url'],
    ];
}

if ($action === 'segments') {
    $rows = $pdo->query("SELECT f_segment_name FROM t_segment WHERE f_active='有効' ORDER BY f_sort_order,f_segment_name")->fetchAll(PDO::FETCH_COLUMN);
    out(['items' => $rows]);
}

if ($action === 'search') {
    $q     = normalize_kana($_GET['q'] ?? '');
    $raw   = trim((string)($_GET['q'] ?? ''));
    $seg   = trim((string)($_GET['seg'] ?? ''));
    $row   = trim((string)($_GET['row'] ?? ''));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));

    $where = ["t.f_active = '有効'"];
    $bind  = [];

    if ($q !== '') {
        // カナ前方一致 / 社名部分一致 / 外部コード前方一致
        $where[] = "(t.f_torihikisaki_kana LIKE ? OR t.f_torihikisaki_name LIKE ? OR t.f_code LIKE ?)";
        $bind[] = $q . '%';
        $bind[] = '%' . $raw . '%';
        $bind[] = $q . '%';
    }
    if ($seg !== '') {
        $where[] = "t.f_segment = ?";
        $bind[] = $seg;
    }
    $rows = gojuon_rows();
    if ($row !== '' && isset($rows[$row])) {
        $where[] = "t.f_torihikisaki_kana REGEXP ?";
        $bind[] = $rows[$row];
    }

    $sql = "SELECT SQL_CALC_FOUND_ROWS t.* FROM t_torihikisaki t WHERE " . implode(' AND ', $where)
         . " ORDER BY (t.f_torihikisaki_kana IS NULL), t.f_torihikisaki_kana, t.f_torihikisaki_name LIMIT {$limit}";
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    $items = array_map('tori_row', $st->fetchAll());
    $total = (int)$pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    out(['items' => $items, 'total' => $total]);
}

if ($action === 'detail') {
    $id = $_GET['id'] ?? '';
    $st = $pdo->prepare("SELECT * FROM t_torihikisaki WHERE pk_torihikisaki_id = ?");
    $st->execute([$id]);
    $t = $st->fetch();
    if (!$t) out(['error' => 'not found']);

    $st = $pdo->prepare("SELECT pk_kyoten_id id, f_kyoten_name name, f_address address, f_tel tel FROM t_torihikisaki_kyoten WHERE fk_torihikisaki_id=? AND f_active='有効' ORDER BY f_sort_order, f_kyoten_name");
    $st->execute([$id]);
    $kyoten = $st->fetchAll();

    $st = $pdo->prepare("SELECT pk_busho_id id, fk_kyoten_id kyoten_id, f_busho_name name, f_tel tel FROM t_torihikisaki_busho WHERE fk_torihikisaki_id=? AND f_active='有効' ORDER BY f_sort_order, f_busho_name");
    $st->execute([$id]);
    $busho = $st->fetchAll();

    $st = $pdo->prepare("SELECT pk_saki_tantosha_id id, fk_kyoten_id kyoten_id, fk_busho_id busho_id, f_name name, f_yakushoku yakushoku, f_tel tel, f_mobile mobile, f_email email, f_main_flag main_flag FROM t_saki_tantosha WHERE fk_torihikisaki_id=? AND f_active='有効' ORDER BY f_main_flag DESC, f_sort_order, f_name");
    $st->execute([$id]);
    $tantosha = $st->fetchAll();

    // 取引条件（担当交代時の確認用に表示）
    $st = $pdo->prepare("SELECT f_kaishu_frequency, f_payment_site, f_payment_method, f_contract_date, f_biko FROM t_torihiki_joken WHERE fk_torihikisaki_id=? LIMIT 1");
    $st->execute([$id]);
    $joken = $st->fetch() ?: null;

    out(['tori' => tori_row($t), 'kyoten' => $kyoten, 'busho' => $busho, 'tantosha' => $tantosha, 'joken' => $joken]);
}

out(['error' => 'unknown action']);
