-- =====================================================================
-- 006: 工場管理のアラート・通知機能（提案書4.4節）
-- 対象: arsystem_ebisu (MariaDB 10.5)
-- 前提: 005_uriage.sql 適用済み。既存データは削除しない。
-- 再実行可: 追加済みのカラム/テーブルはスキップする。
-- =====================================================================
SET NAMES utf8mb4;

DELIMITER $$
DROP PROCEDURE IF EXISTS add_col_if_missing $$
CREATE PROCEDURE add_col_if_missing(IN p_table VARCHAR(64), IN p_col VARCHAR(64), IN p_def TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_col, '` ', p_def);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END $$
DELIMITER ;

-- ---------------------------------------------------------------
-- 1. 担当者マスターにメール・携帯電話番号を追加
--    （工務担当は個人アドレスを保有。工場一般職はアドレスなしのため空欄可）
-- ---------------------------------------------------------------
CALL add_col_if_missing('t_tantosha', 'f_email', "VARCHAR(200) DEFAULT NULL COMMENT '通知メール送信先（任意）' AFTER f_account_name");
CALL add_col_if_missing('t_tantosha', 'f_tel',   "VARCHAR(50)  DEFAULT NULL COMMENT '携帯電話番号（電話発信ボタン用・任意）' AFTER f_email");

-- ---------------------------------------------------------------
-- 2. 工場・拠点マスターに窓口担当・代表メールを追加
--    （窓口担当は固定。工場は代表アドレス＝工場長使用のみのケースに対応）
-- ---------------------------------------------------------------
CALL add_col_if_missing('t_factory', 'fk_madoguchi_tantosha_id', "VARCHAR(36) DEFAULT NULL COMMENT '窓口担当（異常・不具合発生時の自動アサイン先）' AFTER f_tanto_name");
CALL add_col_if_missing('t_factory', 'f_daihyo_email',           "VARCHAR(200) DEFAULT NULL COMMENT '工場代表メールアドレス（工場長使用・窓口担当未設定時のフォールバック）' AFTER fk_madoguchi_tantosha_id");

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't_factory' AND CONSTRAINT_NAME = 'fk_factory_madoguchi');
SET @s := IF(@fk = 0,
  'ALTER TABLE t_factory ADD CONSTRAINT fk_factory_madoguchi FOREIGN KEY (fk_madoguchi_tantosha_id) REFERENCES t_tantosha (pk_tantosha_id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------
-- 3. 通知送信履歴（超過アラートの重複送信防止用。新規登録時通知は都度送信のためログ対象外）
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS t_kj_notify_log (
  pk_notify_id     VARCHAR(36)  NOT NULL,
  fk_fugu_id       VARCHAR(36)  NOT NULL,
  f_notify_type    VARCHAR(30)  NOT NULL COMMENT '超過アラート 等',
  f_sent_to        VARCHAR(200) DEFAULT NULL,
  f_sent_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (pk_notify_id),
  KEY idx_kj_notify_fugu (fk_fugu_id),
  CONSTRAINT fk_kj_notify_fugu FOREIGN KEY (fk_fugu_id) REFERENCES t_fugu (pk_fugu_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='工場管理：超過アラート送信履歴（重複通知防止）';

DROP PROCEDURE IF EXISTS add_col_if_missing;
