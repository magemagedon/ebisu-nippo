-- =====================================================================
-- 002: 部署マスター新設・権限区分に「部門管理者」を追加
-- 対象: arsystem_ebisu (MariaDB 10.5)
-- 前提: 001_torihikisaki_kakucho.sql 適用済み。既存データは削除しない。
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
-- 1. 部署マスター（自社の部署。従業員マスターに紐づける）
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS t_busho (
  pk_busho_id   VARCHAR(36)  NOT NULL,
  f_busho_name  VARCHAR(100) NOT NULL COMMENT '営業部・工務部 等',
  f_biko        TEXT         DEFAULT NULL,
  f_sort_order  INT          NOT NULL DEFAULT 0,
  f_active      ENUM('有効','無効') NOT NULL DEFAULT '有効',
  f_created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pk_busho_id),
  UNIQUE KEY uq_busho_name (f_busho_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='自社 部署マスター';

-- ---------------------------------------------------------------
-- 2. 従業員マスターに部署を紐づけ
-- ---------------------------------------------------------------
CALL add_col_if_missing('t_tantosha', 'fk_busho_id', "VARCHAR(36) DEFAULT NULL COMMENT '所属部署' AFTER f_tantosha_name");

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't_tantosha' AND INDEX_NAME = 'idx_tantosha_busho');
SET @s := IF(@idx = 0, 'ALTER TABLE t_tantosha ADD INDEX idx_tantosha_busho (fk_busho_id)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't_tantosha' AND CONSTRAINT_NAME = 'fk_tantosha_busho');
SET @s := IF(@fk = 0, 'ALTER TABLE t_tantosha ADD CONSTRAINT fk_tantosha_busho FOREIGN KEY (fk_busho_id) REFERENCES t_busho (pk_busho_id) ON DELETE SET NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------
-- 3. 権限区分に「部門管理者」を追加
--    一般：自分の日報のみ／部門管理者：自部門所属者の日報を閲覧・承認／管理者：全部門
-- ---------------------------------------------------------------
ALTER TABLE t_tantosha MODIFY COLUMN f_kengen_kubun ENUM('一般','部門管理者','管理者') NOT NULL DEFAULT '一般';

DROP PROCEDURE IF EXISTS add_col_if_missing;
