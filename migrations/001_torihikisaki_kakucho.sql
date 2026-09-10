-- =====================================================================
-- 001: 取引先マスター拡張（階層化・カナ検索・外部リンク・自社担当割当）
-- 対象: arsystem_ebisu (MariaDB 10.5)
-- 前提: setup.sql 適用済み。既存テーブルの削除・型変更は行わない。
-- 再実行可: 追加済みのカラム/テーブルはスキップする。
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------
-- 1. t_torihikisaki 拡張
-- ---------------------------------------------------------------
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

CALL add_col_if_missing('t_torihikisaki', 'f_torihikisaki_kana', "VARCHAR(200) DEFAULT NULL COMMENT '取引先名カナ（全角カタカナ・検索用）' AFTER f_torihikisaki_name");
CALL add_col_if_missing('t_torihikisaki', 'f_code',              "VARCHAR(50)  DEFAULT NULL COMMENT '外部コード（販売管理システム等）' AFTER pk_torihikisaki_id");
CALL add_col_if_missing('t_torihikisaki', 'f_segment',           "VARCHAR(100) DEFAULT NULL COMMENT '事業セグメント（燃料化/原料販売 等）'");
CALL add_col_if_missing('t_torihikisaki', 'f_zip',               "VARCHAR(10)  DEFAULT NULL");
CALL add_col_if_missing('t_torihikisaki', 'f_fax',               "VARCHAR(50)  DEFAULT NULL");
CALL add_col_if_missing('t_torihikisaki', 'f_hp_url',            "VARCHAR(300) DEFAULT NULL COMMENT 'ホームページURL'");
CALL add_col_if_missing('t_torihikisaki', 'f_map_url',           "VARCHAR(500) DEFAULT NULL COMMENT '地図URL（空欄時は住所からGoogleマップ検索）'");
CALL add_col_if_missing('t_torihikisaki', 'f_kessan_url',        "VARCHAR(300) DEFAULT NULL COMMENT '決算情報・企業情報URL'");
CALL add_col_if_missing('t_torihikisaki', 'f_active',            "ENUM('有効','無効') NOT NULL DEFAULT '有効'");
CALL add_col_if_missing('t_torihikisaki', 'f_updated_at',        "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

-- 検索用インデックス（存在チェック）
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't_torihikisaki' AND INDEX_NAME = 'idx_tori_kana');
SET @s := IF(@idx = 0, 'ALTER TABLE t_torihikisaki ADD INDEX idx_tori_kana (f_torihikisaki_kana), ADD INDEX idx_tori_code (f_code), ADD INDEX idx_tori_segment (f_segment)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------
-- 2. 支店・事業所
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS t_torihikisaki_kyoten (
  pk_kyoten_id        VARCHAR(36)  NOT NULL,
  fk_torihikisaki_id  VARCHAR(36)  NOT NULL,
  f_kyoten_name       VARCHAR(200) NOT NULL COMMENT '支店・事業所・工場名',
  f_kyoten_kana       VARCHAR(200) DEFAULT NULL,
  f_zip               VARCHAR(10)  DEFAULT NULL,
  f_address           VARCHAR(300) DEFAULT NULL,
  f_tel               VARCHAR(50)  DEFAULT NULL,
  f_fax               VARCHAR(50)  DEFAULT NULL,
  f_map_url           VARCHAR(500) DEFAULT NULL,
  f_biko              TEXT         DEFAULT NULL,
  f_sort_order        INT          NOT NULL DEFAULT 0,
  f_active            ENUM('有効','無効') NOT NULL DEFAULT '有効',
  f_created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pk_kyoten_id),
  KEY idx_kyoten_tori (fk_torihikisaki_id),
  CONSTRAINT fk_kyoten_tori FOREIGN KEY (fk_torihikisaki_id) REFERENCES t_torihikisaki (pk_torihikisaki_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='取引先 支店・事業所';

-- ---------------------------------------------------------------
-- 3. 部署（拠点に紐づく。本社直下の部署は fk_kyoten_id = NULL）
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS t_torihikisaki_busho (
  pk_busho_id         VARCHAR(36)  NOT NULL,
  fk_torihikisaki_id  VARCHAR(36)  NOT NULL,
  fk_kyoten_id        VARCHAR(36)  DEFAULT NULL,
  f_busho_name        VARCHAR(200) NOT NULL COMMENT '購買部・企画部 等',
  f_tel               VARCHAR(50)  DEFAULT NULL,
  f_biko              TEXT         DEFAULT NULL,
  f_sort_order        INT          NOT NULL DEFAULT 0,
  f_active            ENUM('有効','無効') NOT NULL DEFAULT '有効',
  f_created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pk_busho_id),
  KEY idx_busho_tori (fk_torihikisaki_id),
  KEY idx_busho_kyoten (fk_kyoten_id),
  CONSTRAINT fk_busho_tori   FOREIGN KEY (fk_torihikisaki_id) REFERENCES t_torihikisaki (pk_torihikisaki_id) ON DELETE CASCADE,
  CONSTRAINT fk_busho_kyoten FOREIGN KEY (fk_kyoten_id) REFERENCES t_torihikisaki_kyoten (pk_kyoten_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='取引先 部署';

-- ---------------------------------------------------------------
-- 4. 先方担当者（部署・拠点は任意）
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS t_saki_tantosha (
  pk_saki_tantosha_id VARCHAR(36)  NOT NULL,
  fk_torihikisaki_id  VARCHAR(36)  NOT NULL,
  fk_kyoten_id        VARCHAR(36)  DEFAULT NULL,
  fk_busho_id         VARCHAR(36)  DEFAULT NULL,
  f_name              VARCHAR(100) NOT NULL,
  f_kana              VARCHAR(100) DEFAULT NULL,
  f_yakushoku         VARCHAR(100) DEFAULT NULL COMMENT '役職',
  f_tel               VARCHAR(50)  DEFAULT NULL,
  f_mobile            VARCHAR(50)  DEFAULT NULL,
  f_email             VARCHAR(200) DEFAULT NULL,
  f_main_flag         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=主担当（既定表示）',
  f_biko              TEXT         DEFAULT NULL,
  f_sort_order        INT          NOT NULL DEFAULT 0,
  f_active            ENUM('有効','無効') NOT NULL DEFAULT '有効',
  f_created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pk_saki_tantosha_id),
  KEY idx_saki_tori (fk_torihikisaki_id),
  KEY idx_saki_kyoten (fk_kyoten_id),
  KEY idx_saki_busho (fk_busho_id),
  CONSTRAINT fk_saki_tori   FOREIGN KEY (fk_torihikisaki_id) REFERENCES t_torihikisaki (pk_torihikisaki_id) ON DELETE CASCADE,
  CONSTRAINT fk_saki_kyoten FOREIGN KEY (fk_kyoten_id) REFERENCES t_torihikisaki_kyoten (pk_kyoten_id) ON DELETE SET NULL,
  CONSTRAINT fk_saki_busho  FOREIGN KEY (fk_busho_id) REFERENCES t_torihikisaki_busho (pk_busho_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='取引先 先方担当者';

-- 既存の f_tantosha_name を先方担当者テーブルへ移行（未移行分のみ）
INSERT INTO t_saki_tantosha (pk_saki_tantosha_id, fk_torihikisaki_id, f_name, f_main_flag, f_tel)
SELECT UUID(), t.pk_torihikisaki_id, TRIM(t.f_tantosha_name), 1, t.f_tel
FROM t_torihikisaki t
WHERE t.f_tantosha_name IS NOT NULL AND TRIM(t.f_tantosha_name) <> ''
  AND NOT EXISTS (SELECT 1 FROM t_saki_tantosha s WHERE s.fk_torihikisaki_id = t.pk_torihikisaki_id);

-- ---------------------------------------------------------------
-- 5. 自社担当割当（従業員 × 取引先[× 部署]、メイン／サブ）
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS t_tantosha_torihikisaki (
  pk_assign_id        VARCHAR(36) NOT NULL,
  fk_tantosha_id      VARCHAR(36) NOT NULL COMMENT '自社従業員',
  fk_torihikisaki_id  VARCHAR(36) NOT NULL,
  fk_kyoten_id        VARCHAR(36) DEFAULT NULL,
  fk_busho_id         VARCHAR(36) DEFAULT NULL,
  f_role              ENUM('メイン','サブ') NOT NULL DEFAULT 'メイン',
  f_biko              TEXT        DEFAULT NULL,
  f_created_at        DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (pk_assign_id),
  UNIQUE KEY uq_assign (fk_tantosha_id, fk_torihikisaki_id, fk_kyoten_id, fk_busho_id),
  KEY idx_assign_tori (fk_torihikisaki_id),
  CONSTRAINT fk_assign_tantosha FOREIGN KEY (fk_tantosha_id)     REFERENCES t_tantosha (pk_tantosha_id) ON DELETE CASCADE,
  CONSTRAINT fk_assign_tori     FOREIGN KEY (fk_torihikisaki_id) REFERENCES t_torihikisaki (pk_torihikisaki_id) ON DELETE CASCADE,
  CONSTRAINT fk_assign_kyoten   FOREIGN KEY (fk_kyoten_id)       REFERENCES t_torihikisaki_kyoten (pk_kyoten_id) ON DELETE SET NULL,
  CONSTRAINT fk_assign_busho    FOREIGN KEY (fk_busho_id)        REFERENCES t_torihikisaki_busho (pk_busho_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='自社担当者と取引先の割当';

-- ---------------------------------------------------------------
-- 6. 日報明細に拠点・部署・先方担当者の参照を追加（テキスト項目は残す）
-- ---------------------------------------------------------------
CALL add_col_if_missing('t_nippo_meisai', 'fk_kyoten_id',        "VARCHAR(36) DEFAULT NULL AFTER fk_torihikisaki_id");
CALL add_col_if_missing('t_nippo_meisai', 'fk_busho_id',         "VARCHAR(36) DEFAULT NULL AFTER fk_kyoten_id");
CALL add_col_if_missing('t_nippo_meisai', 'fk_saki_tantosha_id', "VARCHAR(36) DEFAULT NULL AFTER fk_busho_id");

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't_nippo_meisai' AND INDEX_NAME = 'idx_meisai_tori');
SET @s := IF(@idx = 0, 'ALTER TABLE t_nippo_meisai ADD INDEX idx_meisai_tori (fk_torihikisaki_id), ADD INDEX idx_meisai_saki (fk_saki_tantosha_id)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------
-- 7. セグメントマスター（絞り込み用。取引先・商品の f_segment / f_category と名称で対応）
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS t_segment (
  pk_segment_id  VARCHAR(36)  NOT NULL,
  f_segment_name VARCHAR(100) NOT NULL,
  f_sort_order   INT          NOT NULL DEFAULT 0,
  f_active       ENUM('有効','無効') NOT NULL DEFAULT '有効',
  PRIMARY KEY (pk_segment_id),
  UNIQUE KEY uq_segment_name (f_segment_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='事業セグメント';

INSERT IGNORE INTO t_segment (pk_segment_id, f_segment_name, f_sort_order) VALUES
  (UUID(), '燃料化', 1),
  (UUID(), '原料販売', 2),
  (UUID(), '古紙', 3),
  (UUID(), 'その他', 9);

DROP PROCEDURE IF EXISTS add_col_if_missing;

-- ---------------------------------------------------------------
-- 8. サンプルデータへのカナ付与（既存3社。本番データはCSV取込で設定）
-- ---------------------------------------------------------------
UPDATE t_torihikisaki SET f_torihikisaki_kana = 'マルマルシギョウ',  f_segment = '古紙'   WHERE pk_torihikisaki_id = 'aaaa0001-0000-0000-0000-000000000001' AND f_torihikisaki_kana IS NULL;
UPDATE t_torihikisaki SET f_torihikisaki_kana = 'サンカクショウジ',  f_segment = '燃料化' WHERE pk_torihikisaki_id = 'aaaa0002-0000-0000-0000-000000000002' AND f_torihikisaki_kana IS NULL;
UPDATE t_torihikisaki SET f_torihikisaki_kana = 'シカクコウギョウ',  f_segment = '原料販売' WHERE pk_torihikisaki_id = 'aaaa0003-0000-0000-0000-000000000003' AND f_torihikisaki_kana IS NULL;
