-- =====================================================================
-- 004: 相場情報拡張（訪問明細の商品紐づけ・外部市況テーブル新設）
-- 対象: arsystem_ebisu (MariaDB 10.5)
-- 前提: 003_shohin_shiire_kakucho.sql 適用済み。既存データは削除しない。
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
-- 1. 日報明細に商品を紐づけ（任意。主要商品ごとの相場推移集計に使用）
-- ---------------------------------------------------------------
CALL add_col_if_missing('t_nippo_meisai', 'fk_product_id', "VARCHAR(36) DEFAULT NULL AFTER fk_saki_tantosha_id");

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't_nippo_meisai' AND INDEX_NAME = 'idx_meisai_product');
SET @s := IF(@idx = 0, 'ALTER TABLE t_nippo_meisai ADD INDEX idx_meisai_product (fk_product_id)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------
-- 2. 外部市況（古紙専門誌・バージン樹脂／ナフサ・石炭 等。手動入力）
--    社内相場は非公開のため、外部市況は社内相場と別テーブルで管理する。
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS t_gaibu_soba (
  pk_gaibu_soba_id VARCHAR(36)  NOT NULL,
  f_date           DATE         NOT NULL,
  f_item_name      VARCHAR(100) NOT NULL COMMENT '古紙／バージン樹脂／ナフサ／石炭 等',
  f_price          DECIMAL(10,2) NOT NULL,
  f_unit           VARCHAR(20)  NOT NULL DEFAULT '円/kg',
  f_source         VARCHAR(200) DEFAULT NULL COMMENT '出典（専門誌名等）',
  f_biko           TEXT         DEFAULT NULL,
  fk_tantosha_id   VARCHAR(36)  DEFAULT NULL COMMENT '入力者',
  f_created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (pk_gaibu_soba_id),
  KEY idx_gaibu_soba_date (f_date),
  KEY idx_gaibu_soba_item (f_item_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='外部市況（手動入力）';

DROP PROCEDURE IF EXISTS add_col_if_missing;
