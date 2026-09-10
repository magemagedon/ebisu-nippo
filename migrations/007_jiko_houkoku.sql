-- =====================================================================
-- 007: 事故報告機能の新設（大メニュー追加）
-- 対象: arsystem_ebisu (MariaDB 10.5)
-- 前提: 006_koujou_alert.sql 適用済み。既存データは削除しない。
-- 再実行可: CREATE TABLE IF NOT EXISTS のみ使用。
-- =====================================================================
SET NAMES utf8mb4;

-- 報告フロー：報告 → 上長確認 → 管理確認 → 対応終了／継続 → 経営層報告
CREATE TABLE IF NOT EXISTS t_jiko (
  pk_jiko_id                     VARCHAR(36)  NOT NULL,
  f_date                         DATE         NOT NULL COMMENT '発生日',
  f_time                         VARCHAR(10)  DEFAULT NULL COMMENT '発生時刻（任意・HH:MM）',
  fk_factory_id                  VARCHAR(36)  DEFAULT NULL COMMENT '発生場所（自社工場・拠点の場合）',
  f_place_text                   VARCHAR(200) DEFAULT NULL COMMENT '場所の補足・工場以外の場合の記述',
  f_kubun                        ENUM('労災','交通事故','設備事故','ヒヤリハット','その他') NOT NULL DEFAULT 'その他' COMMENT '事故種別',
  f_title                        VARCHAR(200) NOT NULL COMMENT '件名',
  f_detail                       TEXT         DEFAULT NULL COMMENT '詳細',
  fk_tantosha_id                 VARCHAR(36)  NOT NULL COMMENT '報告者',
  f_taiou_status                 ENUM('継続','終了') NOT NULL DEFAULT '継続' COMMENT '対応状況',
  f_joucho_kakunin_flag          ENUM('未確認','確認済') NOT NULL DEFAULT '未確認' COMMENT '上長確認',
  fk_joucho_kakunin_tantosha_id  VARCHAR(36)  DEFAULT NULL COMMENT '上長確認者',
  f_joucho_kakunin_at            DATETIME     DEFAULT NULL,
  f_kanri_kakunin_flag           ENUM('未確認','確認済') NOT NULL DEFAULT '未確認' COMMENT '管理確認',
  fk_kanri_kakunin_tantosha_id   VARCHAR(36)  DEFAULT NULL COMMENT '管理確認者',
  f_kanri_kakunin_at             DATETIME     DEFAULT NULL,
  f_keiei_houkoku_flag           ENUM('未報告','報告済') NOT NULL DEFAULT '未報告' COMMENT '経営層報告',
  f_keiei_houkoku_at             DATETIME     DEFAULT NULL,
  f_created_at                   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_updated_at                   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pk_jiko_id),
  KEY idx_jiko_date (f_date),
  KEY idx_jiko_factory (fk_factory_id),
  KEY idx_jiko_tantosha (fk_tantosha_id),
  KEY idx_jiko_status (f_taiou_status),
  CONSTRAINT fk_jiko_factory FOREIGN KEY (fk_factory_id) REFERENCES t_factory (pk_factory_id) ON DELETE SET NULL,
  CONSTRAINT fk_jiko_tantosha FOREIGN KEY (fk_tantosha_id) REFERENCES t_tantosha (pk_tantosha_id),
  CONSTRAINT fk_jiko_joucho FOREIGN KEY (fk_joucho_kakunin_tantosha_id) REFERENCES t_tantosha (pk_tantosha_id) ON DELETE SET NULL,
  CONSTRAINT fk_jiko_kanri FOREIGN KEY (fk_kanri_kakunin_tantosha_id) REFERENCES t_tantosha (pk_tantosha_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='事故報告（報告→上長確認→管理確認→対応終了/継続→経営層報告）';
