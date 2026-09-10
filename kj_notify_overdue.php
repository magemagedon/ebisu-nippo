<?php
// ============================================================
// 工場メンテナンス管理：超過アラート（要件4.4-2）
// 未対応のまま30日を超えた不具合・行動計画について、窓口担当（未設定時は
// 工場代表メール）へ注意喚起メールを送信する。
// 同一案件への重複送信を避けるため、直近7日以内に送信済みならスキップする。
// 実行方法：Webからの手動実行（管理者ログイン要）、または cron による定期実行
//   （例：毎日9:00に  php /home/.../ebisu/kj_notify_overdue.php  を実行）
// ============================================================
require_once __DIR__ . '/kj_common.php';
$pdo = get_db();

$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli && ($_SESSION['kengen'] ?? '') !== '管理者') { header('Location: kj_dashboard.php'); exit; }

$threshold_days = 30;
$resend_after_days = 7;

$stmt = $pdo->query("
    SELECT g.*, f.f_factory_name, t.f_tel AS tantosha_tel
    FROM t_fugu g
    JOIN t_factory f ON g.fk_factory_id = f.pk_factory_id
    JOIN t_tantosha t ON g.fk_tantosha_id = t.pk_tantosha_id
    WHERE g.f_status != '完了'
");
$open_issues = $stmt->fetchAll();

$log_check = $pdo->prepare("SELECT MAX(f_sent_at) FROM t_kj_notify_log WHERE fk_fugu_id=? AND f_notify_type='超過アラート'");
$log_ins    = $pdo->prepare("INSERT INTO t_kj_notify_log (pk_notify_id,fk_fugu_id,f_notify_type,f_sent_to,f_sent_at) VALUES (?,?,'超過アラート',?,NOW())");

$sent = 0; $skipped = 0; $checked = 0; $report = [];
foreach ($open_issues as $g) {
    $elapsed = kj_elapsed_days($g['f_created_at']);
    if ($elapsed < $threshold_days) continue;
    $checked++;

    $log_check->execute([$g['pk_fugu_id']]);
    $last_sent = $log_check->fetchColumn();
    if ($last_sent && (strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($last_sent)))) < $resend_after_days) {
        $skipped++;
        continue;
    }

    $notify = kj_resolve_notify_target($pdo, $g['fk_factory_id'], $g['fk_tantosha_id']);
    $to = $notify['email'];
    $body = "【要対応】{$g['f_factory_name']}の不具合・行動計画が{$elapsed}日間、未対応のまま経過しています。\n\n" .
        "タイトル：{$g['f_title']}\n優先度：{$g['f_priority']}\nステータス：{$g['f_status']}\n" .
        "登録日：" . date('Y/m/d', strtotime($g['f_created_at'])) . "\n" .
        ($g['f_kigen_date'] ? "期限：" . date('Y/m/d', strtotime($g['f_kigen_date'])) . "\n" : "") .
        "\n放置・連絡漏れがないか確認し、対応状況をシステムに記録してください。\nhttp://arsystem.jp/ebisu/kj_issue.php\n";

    if (empty($to)) {
        $report[] = "未送信（窓口担当・工場代表とも通知先メール未登録）：{$g['f_factory_name']} / {$g['f_title']}（経過{$elapsed}日）";
    } elseif (kj_send_mail($to, "【工場管理】超過アラート：{$g['f_title']}（経過{$elapsed}日）", $body)) {
        $sent++;
        $log_ins->execute([generate_uuid(), $g['pk_fugu_id'], $to]);
        $report[] = "送信：{$g['f_factory_name']} / {$g['f_title']}（経過{$elapsed}日／宛先：{$to}）";
    } else {
        $report[] = "送信失敗（サーバーのメール送信設定を確認してください）：{$g['f_factory_name']} / {$g['f_title']}（経過{$elapsed}日／宛先：{$to}）";
    }
}

$summary = "工場メンテナンス管理　超過アラート実行結果\n" . str_repeat('=', 40) . "\n" .
    "対象（経過{$threshold_days}日以上）：{$checked} 件／送信：{$sent} 件／直近{$resend_after_days}日以内に送信済みでスキップ：{$skipped} 件\n\n" .
    implode("\n", $report) . "\n";

if ($is_cli || isset($_GET['preview'])) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo $summary;
    exit;
}

header('Location: kj_dashboard.php?msg=' . urlencode("超過アラートを実行しました（送信 {$sent} 件）"));
exit;
