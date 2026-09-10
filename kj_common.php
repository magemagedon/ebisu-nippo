<?php
// ============================================================
// 工場メンテナンス管理モジュール（並行オプション／提案書5-4節）
// 共通処理・ヘルパー関数
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common.php';

// 優先度バッジ（不具合・行動計画）
function kj_priority_badge($p) {
    $map = ['高' => 'badge-danger', '中' => 'badge-warning', '低' => 'badge-success'];
    $cls = $map[$p] ?? 'badge-info';
    return "<span class='badge {$cls}'>" . h($p) . "</span>";
}

// 対応ステータスバッジ（不具合・パトロール対策　共通）
function kj_status_badge($s) {
    $map = ['未着手' => 'badge-warning', '未対応' => 'badge-warning', '対応中' => 'badge-info', '完了' => 'badge-success'];
    $cls = $map[$s] ?? 'badge-info';
    return "<span class='badge {$cls}'>" . h($s) . "</span>";
}

// 点検・パトロール結果バッジ
function kj_result_badge($r) {
    $map = ['正常' => 'badge-success', '異常' => 'badge-danger', '良' => 'badge-success', '要改善' => 'badge-danger'];
    $cls = $map[$r] ?? 'badge-info';
    return "<span class='badge {$cls}'>" . h($r) . "</span>";
}

// パトロール実施ステータスバッジ
function kj_patrol_status_badge($s) {
    $map = ['計画' => 'badge-info', '実施済' => 'badge-success'];
    $cls = $map[$s] ?? 'badge-info';
    return "<span class='badge {$cls}'>" . h($s) . "</span>";
}

// 残日数のラベルと色を返す（todo.php/actions.php同様のロジックを共通化）
function kj_days_label($days_left) {
    if ($days_left === null) return ['期日未設定', '#888'];
    $dl = (int)$days_left;
    if ($dl < 0)   return [abs($dl) . '日超過', '#c62828'];
    if ($dl === 0) return ['本日', '#e65100'];
    if ($dl <= 7)  return [$dl . '日後', '#1565c0'];
    return [$dl . '日後', '#2e7d32'];
}

// 設備の次回交換・保守予定日を計算（直近の交換履歴 → なければ設置日 を起点に周期日数を加算）
function kj_next_koukan_date(PDO $pdo, array $setsubi) {
    if (empty($setsubi['f_koukan_shuki_days'])) return null;
    $stmt = $pdo->prepare("SELECT f_koukan_date FROM t_setsubi_koukan WHERE fk_setsubi_id = ? ORDER BY f_koukan_date DESC LIMIT 1");
    $stmt->execute([$setsubi['pk_setsubi_id']]);
    $last = $stmt->fetchColumn();
    $base = $last ?: ($setsubi['f_setti_date'] ?? null);
    if (!$base) return null;
    return date('Y-m-d', strtotime($base . ' +' . (int)$setsubi['f_koukan_shuki_days'] . ' days'));
}

// 有効な工場・拠点一覧
function kj_factories(PDO $pdo) {
    return $pdo->query("SELECT * FROM t_factory WHERE f_active='有効' ORDER BY f_sort_order,f_factory_name")->fetchAll();
}

// 工場メンテナンス管理モジュールのサブナビ（各画面上部に表示）
function kj_subnav($current) {
    $items = [
        'kj_dashboard.php'   => '📊 ダッシュボード',
        'kj_equipment.php'   => '⚙️ 設備マスタ',
        'kj_check.php'       => '✅ 日次チェック',
        'kj_issue.php'       => '🛠 不具合・行動計画',
        'kj_maintenance.php' => '🔧 保守・交換スケジュール',
        'kj_patrol.php'      => '🦺 安全パトロール',
        'kj_process.php'     => '🗓 工程表連動ビュー',
    ];
    $html = '<div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">';
    foreach ($items as $file => $label) {
        $active = ($current === $file) ? 'btn-primary' : 'btn-gray';
        $html .= "<a href='{$file}' class='btn {$active} btn-sm'>{$label}</a>";
    }
    $html .= '</div>';
    return $html;
}
