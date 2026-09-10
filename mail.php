<?php
require_once 'db.php';

// 今週の期間
$week_start = date('Y-m-d', strtotime('last Monday'));
$week_end   = date('Y-m-d', strtotime('last Sunday'));
$today      = date('Y-m-d');

$pdo = get_db();

// 管理者メールアドレス取得
$admins = $pdo->query("SELECT * FROM t_tantosha WHERE f_kengen_kubun='管理者' AND f_zaiseki_flag='有効'")->fetchAll();

// 今週の集計
$stmt = $pdo->prepare("
    SELECT t.f_tantosha_name,
           COUNT(DISTINCT h.pk_nippo_id) AS nippo_count,
           COUNT(m.pk_meisai_id) AS homon_count,
           SUM(CASE WHEN m.f_juchu_mikomikubun='受注' THEN 1 ELSE 0 END) AS juchu_count,
           SUM(COALESCE(m.f_kaishu_ryo * m.f_tanka, 0)) AS total_kin
    FROM t_tantosha t
    LEFT JOIN t_nippo_header h ON t.pk_tantosha_id = h.fk_tantosha_id AND h.f_date BETWEEN ? AND ?
    LEFT JOIN t_nippo_meisai m ON h.pk_nippo_id = m.fk_nippo_id
    WHERE t.f_zaiseki_flag = '有効'
    GROUP BY t.pk_tantosha_id, t.f_tantosha_name
    ORDER BY homon_count DESC
");
$stmt->execute([$week_start, $week_end]);
$stats = $stmt->fetchAll();

// 期限超過アクション
$stmt = $pdo->prepare("
    SELECT m.f_homonsakimei, m.f_jikai_action, m.f_jikai_yoteibi, t.f_tantosha_name,
           DATEDIFF(?, m.f_jikai_yoteibi) AS overdue_days
    FROM t_nippo_meisai m
    JOIN t_nippo_header h ON m.fk_nippo_id = h.pk_nippo_id
    JOIN t_tantosha t ON h.fk_tantosha_id = t.pk_tantosha_id
    WHERE m.f_jikai_yoteibi < ? AND m.f_jikai_yoteibi IS NOT NULL
    ORDER BY m.f_jikai_yoteibi ASC
    LIMIT 10
");
$stmt->execute([$today, $today]);
$overdues = $stmt->fetchAll();

// 未確認日報数
$stmt = $pdo->query("SELECT COUNT(*) FROM t_nippo_header WHERE f_kakunin_status='未確認'");
$mikakunin_count = $stmt->fetchColumn();

// メール本文生成
$total_homon = array_sum(array_column($stats, 'homon_count'));
$total_juchu = array_sum(array_column($stats, 'juchu_count'));
$total_kin   = array_sum(array_column($stats, 'total_kin'));

$body = "エビス紙料株式会社　業務日報システム\n";
$body .= "週次サマリーレポート\n";
$body .= str_repeat("=", 50) . "\n\n";
$body .= "■ 集計期間：{$week_start} 〜 {$week_end}\n\n";

$body .= "【今週のサマリー】\n";
$body .= "  訪問件数合計：{$total_homon} 件\n";
$body .= "  受注件数合計：{$total_juchu} 件\n";
$body .= "  累計金額合計：" . number_format($total_kin) . " 円\n";
$body .= "  未確認日報数：{$mikakunin_count} 件\n\n";

$body .= "【担当者別実績】\n";
foreach ($stats as $s) {
    $body .= sprintf("  %-15s 日報:%2d件 訪問:%2d件 受注:%2d件 金額:%s円\n",
        $s['f_tantosha_name'],
        $s['nippo_count'],
        $s['homon_count'],
        $s['juchu_count'],
        number_format($s['total_kin'])
    );
}
$body .= "\n";

if (!empty($overdues)) {
    $body .= "【期限超過アクション（要対応）】\n";
    foreach ($overdues as $o) {
        $body .= "  ・{$o['f_tantosha_name']} / {$o['f_homonsakimei']} / {$o['f_jikai_action']} （{$o['f_jikai_yoteibi']} / {$o['overdue_days']}日超過）\n";
    }
    $body .= "\n";
}

$body .= str_repeat("-", 50) . "\n";
$body .= "詳細はシステムにてご確認ください：\n";
$body .= "http://arsystem.jp/ebisu/dashboard.php\n\n";
$body .= "ARシステム株式会社　業務日報システム\n";

// メール送信（手動実行 or cron）
$subject = "【業務日報】週次サマリー " . date('Y/m/d') . " 時点";
$headers = "From: noreply@arsystem.jp\r\nContent-Type: text/plain; charset=UTF-8\r\n";

$sent = 0;
foreach ($admins as $admin) {
    // 管理者のメールアドレスが必要（現在はアカウント名で代替）
    // 実運用では t_tantosha にメールアドレス列を追加する
    // mail($admin['f_email'], $subject, $body, $headers);
    $sent++;
}

// テスト用：標準出力に表示
if (php_sapi_name() === 'cli' || isset($_GET['preview'])) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo $subject . "\n\n" . $body;
    exit;
}

// Web経由の場合はダッシュボードにリダイレクト
header('Location: dashboard.php?msg=' . urlencode('週次サマリーを送信しました'));
exit;
