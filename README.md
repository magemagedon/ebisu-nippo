# エビス紙料 業務日報システム / 工場管理システム

PHP + MariaDB で構築した業務日報システム（Phase 1）および工場管理（メンテナンス）プロトタイプ。

## セットアップ

1. `db.sample.php` を `db.php` にコピーし、DB接続情報を設定する（`db.php` はコミット対象外）
2. `composer install`（mPDF 依存）
3. `setup.sql` → `setup_kojo.sql` → `migrations/*.sql` の順に適用

## 反映（Xserver）

```
ssh xsv "cd ~/arsystem.jp/public_html/ebisu && git pull"
```
