-- =====================================================================
-- 005: 売上実績連携（販売管理システムからのCSV取込用テーブル）
-- 対象: arsystem_ebisu (MariaDB 10.5)
-- 前提: 004_soba_kakucho.sql 適用済み。既存データは削除しない。
-- 再実行可: CREATE TABLE IF NOT EXISTS のみ使用。
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS t_uriage (
  pk_uriage_id        VARCHAR(36)  NOT NULL,
  f_date               DATE         NOT NULL COMMENT '取引日',
  fk_torihikisaki_id    VARCHAR(36)  DEFAULT NULL COMMENT '取引先マスターと一致した場合のみ',
  f_torihikisaki_name   VARCHAR(200) DEFAULT NULL COMMENT 'CSV上の取引先名（未マッチでも保持）',
  fk_product_id         VARCHAR(36)  DEFAULT NULL,
  f_product_name        VARCHAR(200) DEFAULT NULL COMMENT 'CSV上の商品名',
  f_segment             VARCHAR(100) DEFAULT NULL COMMENT '商品または取引先から補完したセグメント',
  fk_busho_id           VARCHAR(36)  DEFAULT NULL COMMENT '部門マスターと一致した場合のみ',
  f_busho_name          VARCHAR(100) DEFAULT NULL COMMENT 'CSV上の部門名',
  f_kingaku              DECIMAL(12,2) NOT NULL COMMENT '金額',
  f_suryo                DECIMAL(12,2) DEFAULT NULL COMMENT '数量（任意）',
  f_biko                 TEXT         DEFAULT NULL,
  fk_tantosha_id         VARCHAR(36)  DEFAULT NULL COMMENT '取込実行者',
  f_import_batch         VARCHAR(50)  DEFAULT NULL COMMENT '取込バッチID（取消・再取込用）',
  f_created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (pk_uriage_id),
  KEY idx_uriage_date (f_date),
  KEY idx_uriage_tori (fk_torihikisaki_id),
  KEY idx_uriage_busho (fk_busho_id),
  KEY idx_uriage_segment (f_segment),
  KEY idx_uriage_batch (f_import_batch),
  CONSTRAINT fk_uriage_tori  FOREIGN KEY (fk_torihikisaki_id) REFERENCES t_torihikisaki (pk_torihikisaki_id) ON DELETE SET NULL,
  CONSTRAINT fk_uriage_prod  FOREIGN KEY (fk_product_id)      REFERENCES t_product (pk_product_id) ON DELETE SET NULL,
  CONSTRAINT fk_uriage_busho FOREIGN KEY (fk_busho_id)        REFERENCES t_busho (pk_busho_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='売上実績（販売管理システムからのCSV取込）';
