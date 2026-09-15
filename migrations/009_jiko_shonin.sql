-- =====================================================================
-- 009: 事故報告に厳密な承認フロー（差し戻し・順序強制・メール通知）を追加
-- 対象: arsystem_ebisu (MariaDB 10.5)
-- 前提: 008_keiei_suchi.sql 適用済み。既存データは削除しない。
-- 再実行可: 追加済みのカラムはスキップする。
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

-- 承認ステータス：申請中 → 上長確認済 → 管理確認済（各段階で差し戻し可）
CALL add_col_if_missing('t_jiko', 'f_status',
  "ENUM('申請中','上長確認済','管理確認済','差し戻し') NOT NULL DEFAULT '申請中' COMMENT '承認ステータス' AFTER f_detail");

-- 差し戻し情報
CALL add_col_if_missing('t_jiko', 'f_sashimodoshi_stage',
  "VARCHAR(10) DEFAULT NULL COMMENT '差し戻された段階（上長／管理）'");
CALL add_col_if_missing('t_jiko', 'f_sashimodoshi_riyu',
  "TEXT DEFAULT NULL COMMENT '差し戻し理由'");
CALL add_col_if_missing('t_jiko', 'fk_sashimodoshi_tantosha_id',
  "VARCHAR(36) DEFAULT NULL COMMENT '差し戻した人'");
CALL add_col_if_missing('t_jiko', 'f_sashimodoshi_at',
  "DATETIME DEFAULT NULL COMMENT '差し戻し日時'");
CALL add_col_if_missing('t_jiko', 'f_saishinsei_at',
  "DATETIME DEFAULT NULL COMMENT '差し戻し後の再申請日時'");

DROP PROCEDURE IF EXISTS add_col_if_missing;

-- 既存データのf_statusを、既存の確認フラグから復元（初回のみ有効。以降は無害な再実行）
UPDATE t_jiko SET f_status = '管理確認済'
  WHERE f_kanri_kakunin_flag = '確認済' AND f_status = '申請中';
UPDATE t_jiko SET f_status = '上長確認済'
  WHERE f_joucho_kakunin_flag = '確認済' AND f_kanri_kakunin_flag <> '確認済' AND f_status = '申請中';
