-- =====================================================================
-- 008: 経営数値集計表（販売管理システムCSVを事業部・指標ごとに集計して
--       「◯期実績一覧表」形式で表示する機能）
-- 対象: arsystem_ebisu (MariaDB 10.5)
-- 前提: 007_jiko_houkoku.sql 適用済み。既存データは削除しない。
-- 再実行可: CREATE TABLE IF NOT EXISTS ／ INSERT IGNORE のみ使用。
-- =====================================================================
SET NAMES utf8mb4;

-- 分類ルール：CSV（伝票データ）の「部門」「商品種別／商品」列を
-- キーワード一致（|区切りでOR）で 事業部・工場（グループ）・指標行 に振り分ける。
-- マスタ管理画面から自由に追加・編集・無効化できる。
CREATE TABLE IF NOT EXISTS t_keiei_bunrui_rule (
  pk_rule_id        VARCHAR(36)  NOT NULL,
  f_jigyobu         VARCHAR(100) NOT NULL COMMENT '事業部（例：再資源化事業部）',
  f_koujou          VARCHAR(100) NOT NULL DEFAULT '' COMMENT '工場・グループ（例：RPF工場／愛媛工場。無ければ空欄）',
  f_shihyo          VARCHAR(100) NOT NULL COMMENT '指標行名（例：RPF売上、処理代、RPF生産量(t)）',
  f_shihyo_type     ENUM('金額','数量') NOT NULL DEFAULT '金額' COMMENT '集計対象（金額列 or 数量列）',
  f_kansan_keisu    DECIMAL(10,4) NOT NULL DEFAULT 1.0000 COMMENT '数量集計時の単位換算係数（例：kg→t なら0.001）',
  f_bumon_keyword   VARCHAR(300) DEFAULT NULL COMMENT '部門列の部分一致キーワード（|区切りでOR、空欄なら条件なし）',
  f_shohin_keyword  VARCHAR(300) DEFAULT NULL COMMENT '商品種別・商品列の部分一致キーワード（|区切りでOR、空欄なら条件なし）',
  f_uriage_shiire   ENUM('売上','仕入','両方') NOT NULL DEFAULT '売上' COMMENT '売上仕入区分での絞り込み',
  f_sort_order      INT NOT NULL DEFAULT 0,
  f_active          ENUM('有効','無効') NOT NULL DEFAULT '有効',
  f_created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pk_rule_id),
  UNIQUE KEY uq_rule (f_jigyobu, f_koujou, f_shihyo),
  KEY idx_rule_sort (f_sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='経営数値：事業部・指標の分類ルール';

-- 取込んだ伝票データ（販売管理システムのCSVエクスポートより。列は集計に必要な範囲のみ保持）
CREATE TABLE IF NOT EXISTS t_keiei_denpyo (
  pk_denpyo_id            VARCHAR(36)  NOT NULL,
  f_denpyo_date           DATE         NOT NULL COMMENT '伝票日付',
  f_yearmonth             CHAR(7)      NOT NULL COMMENT '集計用年月（YYYY-MM）',
  f_bumon                 VARCHAR(100) DEFAULT NULL COMMENT '部門',
  f_gyoshu                VARCHAR(100) DEFAULT NULL COMMENT '業種',
  f_shohin_shubetsu       VARCHAR(100) DEFAULT NULL COMMENT '商品種別',
  f_shohin                VARCHAR(200) DEFAULT NULL COMMENT '商品',
  f_uriage_shiire_kubun   VARCHAR(10)  DEFAULT NULL COMMENT '売上仕入区分（売上／仕入）',
  f_suryo                 DECIMAL(14,3) DEFAULT NULL COMMENT '数量',
  f_tani                  VARCHAR(20)  DEFAULT NULL COMMENT '単位（kg・車 等）',
  f_kingaku               DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '金額',
  f_saki_meisho           VARCHAR(200) DEFAULT NULL COMMENT '集計先・得意先名（参考表示用）',
  f_import_batch          VARCHAR(50)  DEFAULT NULL COMMENT '取込バッチID（取消・再取込用）',
  f_created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (pk_denpyo_id),
  KEY idx_denpyo_ym (f_yearmonth),
  KEY idx_denpyo_bumon (f_bumon),
  KEY idx_denpyo_shohin (f_shohin_shubetsu),
  KEY idx_denpyo_batch (f_import_batch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='経営数値：取込んだ伝票データ（実績は集計時にルールで分類）';

-- 計画（予算）・前年実績（CSVに含まれないため画面から手入力）
CREATE TABLE IF NOT EXISTS t_keiei_keikaku (
  pk_keikaku_id  VARCHAR(36) NOT NULL,
  f_yearmonth    CHAR(7)     NOT NULL COMMENT '対象年月（YYYY-MM）',
  fk_rule_id     VARCHAR(36) NOT NULL,
  f_kubun        ENUM('計画','前年') NOT NULL,
  f_value        DECIMAL(14,2) NOT NULL DEFAULT 0,
  f_updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pk_keikaku_id),
  UNIQUE KEY uq_keikaku (f_yearmonth, fk_rule_id, f_kubun),
  KEY idx_keikaku_rule (fk_rule_id),
  CONSTRAINT fk_keikaku_rule FOREIGN KEY (fk_rule_id) REFERENCES t_keiei_bunrui_rule (pk_rule_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='経営数値：計画・前年実績（手入力）';

-- 初期分類ルール（アップロードいただいたサンプルCSVから推測した仮ルール。
-- マスタ管理画面から自由に追加・修正してください。特に「関西事業部」「ワテック四国工場」は
-- サンプルに該当データが無かったため未登録です）
INSERT IGNORE INTO t_keiei_bunrui_rule
  (pk_rule_id, f_jigyobu, f_koujou, f_shihyo, f_shihyo_type, f_kansan_keisu, f_bumon_keyword, f_shohin_keyword, f_uriage_shiire, f_sort_order, f_active)
VALUES
  (UUID(), '再資源化事業部', 'RPF工場', 'RPF売上',       '金額', 1.0000,  '四国工場',            'ＲＰＦ（四国）',           '売上', 10, '有効'),
  (UUID(), '再資源化事業部', 'RPF工場', '処理代',         '金額', 1.0000,  '四国工場',            '固形燃料用原料引取',       '売上', 20, '有効'),
  (UUID(), '再資源化事業部', 'RPF工場', 'RPF生産量(t)',   '数量', 0.0010,  '四国工場ＲＰＦ製造',  'ＲＰＦ（四国）',           '売上', 30, '有効'),
  (UUID(), '再資源化事業部', 'RPF工場', '処理量(t)',      '数量', 0.0010,  '四国工場',            '固形燃料用原料引取',       '売上', 40, '有効'),
  (UUID(), '再資源化事業部', '愛媛工場', 'RPF売上',       '金額', 1.0000,  NULL,                   'ＲＰＦ（愛媛）|ＲＰＦ（愛媛製紙）', '売上', 50, '有効'),
  (UUID(), '再資源化事業部', '愛媛工場', 'RPF生産量(t)',  '数量', 0.0010,  NULL,                   'ＲＰＦ（愛媛）|ＲＰＦ（愛媛製紙）', '売上', 60, '有効'),
  (UUID(), 'プラスチック循環事業部', '', '売上金額',       '金額', 1.0000,  NULL,                   'ﾌﾟﾗｽﾁｯｸ原料（再生）',       '売上', 70, '有効'),
  (UUID(), 'プラスチック循環事業部', '', '仕入金額',       '金額', 1.0000,  NULL,                   'ﾌﾟﾗｽﾁｯｸ原料（再生）',       '仕入', 80, '有効');
