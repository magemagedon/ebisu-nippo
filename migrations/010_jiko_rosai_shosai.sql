-- =====================================================================
-- 010: 事故報告（労災区分）に「労働者死傷病報告（様式第23号）」の下書き作成に
--       必要な項目を追加
-- 対象: arsystem_ebisu (MariaDB 10.5)
-- 前提: 009_jiko_shonin.sql 適用済み。既存データは削除しない。
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

-- 被災者情報（労働者死傷病報告 様式第23号の下書き用。労災区分の場合のみ入力）
CALL add_col_if_missing('t_jiko', 'f_higaisha_name',
  "VARCHAR(100) DEFAULT NULL COMMENT '被災者氏名'");
CALL add_col_if_missing('t_jiko', 'f_higaisha_sei',
  "ENUM('男','女') DEFAULT NULL COMMENT '被災者性別'");
CALL add_col_if_missing('t_jiko', 'f_higaisha_seinengappi',
  "DATE DEFAULT NULL COMMENT '被災者生年月日'");
CALL add_col_if_missing('t_jiko', 'f_higaisha_shokushu',
  "VARCHAR(100) DEFAULT NULL COMMENT '被災者の職種'");
CALL add_col_if_missing('t_jiko', 'f_keiken_kikan',
  "VARCHAR(50) DEFAULT NULL COMMENT '当該業務の経験期間（例：3年2ヶ月）'");
CALL add_col_if_missing('t_jiko', 'f_shoubyou_bui',
  "VARCHAR(100) DEFAULT NULL COMMENT '傷病の部位'");
CALL add_col_if_missing('t_jiko', 'f_shoubyou_mei',
  "VARCHAR(200) DEFAULT NULL COMMENT '傷病名'");
CALL add_col_if_missing('t_jiko', 'f_kyugyo_kubun',
  "ENUM('休業見込み','死亡') DEFAULT NULL COMMENT '休業見込み／死亡の別'");
CALL add_col_if_missing('t_jiko', 'f_kyugyo_nissu',
  "INT DEFAULT NULL COMMENT '休業見込み日数'");

DROP PROCEDURE IF EXISTS add_col_if_missing;
