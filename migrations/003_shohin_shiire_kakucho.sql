-- =====================================================================
-- 003: 商品マスター拡張（商品／サービス／仕入れ区分、セグメント、細分類）
-- 対象: arsystem_ebisu (MariaDB 10.5)
-- 前提: 002_bumon_kengen.sql 適用済み。既存データは削除しない。
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

-- ---------------------------------------------------------------
-- 1. t_product 拡張
--    f_kubun      : 商品／サービス／仕入れ の区分（右から左への転売・お客様からの仕入れに対応）
--    f_segment    : 事業セグメント（t_segment のマスター値と対応）
--    f_subcategory: セグメント配下の細分類（例：原料販売 → 猫砂・綿・原綿の色 等）
--    f_code       : 外部コード（将来のCSV取込・販売管理システム連携用）
-- ---------------------------------------------------------------
CALL add_col_if_missing('t_product', 'f_kubun',       "ENUM('商品','サービス','仕入れ') NOT NULL DEFAULT '商品' AFTER f_product_name");
CALL add_col_if_missing('t_product', 'f_segment',      "VARCHAR(100) DEFAULT NULL AFTER f_category");
CALL add_col_if_missing('t_product', 'f_subcategory',  "VARCHAR(100) DEFAULT NULL AFTER f_segment");
CALL add_col_if_missing('t_product', 'f_code',         "VARCHAR(50)  DEFAULT NULL AFTER pk_product_id");

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't_product' AND INDEX_NAME = 'idx_product_segment');
SET @s := IF(@idx = 0, 'ALTER TABLE t_product ADD INDEX idx_product_segment (f_segment), ADD INDEX idx_product_kubun (f_kubun)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 既存商品は全て「商品」区分のまま（DEFAULTで対応済み）

DROP PROCEDURE IF EXISTS add_col_if_missing;
