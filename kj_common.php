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

// 経過日数（不具合・行動計画：登録日からの経過日数）
function kj_elapsed_days($created_at) {
    if (!$created_at) return 0;
    return (int)floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($created_at)))) / 86400);
}

// 経過日数バッジ（超過アラート：既定30日で赤色注意喚起）
function kj_elapsed_badge($created_at, $threshold = 30) {
    $days = kj_elapsed_days($created_at);
    if ($days >= $threshold) {
        return "<span style='color:#c62828;font-weight:700;font-size:12px'>⚠ 経過{$days}日（{$threshold}日超）</span>";
    }
    $color = $days >= (int)($threshold * 0.7) ? '#e65100' : '#666';
    return "<span style='color:{$color};font-size:12px'>経過{$days}日</span>";
}

// 電話発信リンク（スマホからワンタップ発信。番号未登録なら空文字）
function kj_tel_link($tel, $label = '📞') {
    if (empty($tel)) return '';
    $clean = preg_replace('/[^0-9+]/', '', $tel);
    if ($clean === '') return '';
    return "<a href='tel:{$clean}' class='btn btn-blue btn-sm' style='text-decoration:none'>{$label} " . h($tel) . "</a>";
}

// 不具合・日次チェック異常の通知先を解決する（窓口担当のメール → 工場代表メール の順でフォールバック）
// 戻り値: ['tantosha_id' => 割当担当者ID(nullable), 'email' => 通知先メール(nullable)]
function kj_resolve_notify_target(PDO $pdo, $factory_id, $fallback_tantosha_id = null) {
    $stmt = $pdo->prepare("
        SELECT f.f_daihyo_email, m.pk_tantosha_id, m.f_tantosha_name, m.f_email
        FROM t_factory f
        LEFT JOIN t_tantosha m ON f.fk_madoguchi_tantosha_id = m.pk_tantosha_id AND m.f_zaiseki_flag='有効'
        WHERE f.pk_factory_id = ?
    ");
    $stmt->execute([$factory_id]);
    $row = $stmt->fetch();

    if ($row && $row['pk_tantosha_id']) {
        return ['tantosha_id' => $row['pk_tantosha_id'], 'email' => $row['f_email'] ?: ($row['f_daihyo_email'] ?: null)];
    }
    if ($row && $row['f_daihyo_email']) {
        return ['tantosha_id' => $fallback_tantosha_id, 'email' => $row['f_daihyo_email']];
    }
    return ['tantosha_id' => $fallback_tantosha_id, 'email' => null];
}

// 工場メンテナンス管理モジュール共通のメール送信（宛先未登録時は何もしない。送信可否を返す）
function kj_send_mail($to, $subject, $body) {
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $headers = "From: noreply@arsystem.jp\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $subject_enc = mb_encode_mimeheader($subject, 'UTF-8');
    return @mail($to, $subject_enc, $body, $headers);
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
