-- ============================================================
-- 工場メンテナンス管理モジュール（並行オプション／提案書5-4節）
-- テーブル追加スクリプト（既存 t_factory / t_tantosha を参照）
-- ============================================================
SET NAMES utf8mb4;
SET time_zone = '+09:00';

-- 設備マスタ（各工場の設備・機械・刃物等消耗部品）
CREATE TABLE IF NOT EXISTS t_setsubi (
  pk_setsubi_id VARCHAR(36) NOT NULL PRIMARY KEY,
  fk_factory_id VARCHAR(36) NOT NULL,
  f_setsubi_name VARCHAR(200) NOT NULL,
  f_setsubi_kubun ENUM('設備','機械','消耗部品') NOT NULL DEFAULT '設備',
  f_type_no VARCHAR(100),
  f_setti_date DATE,
  f_koukan_shuki_days INT DEFAULT NULL,
  f_biko TEXT,
  f_sort_order INT NOT NULL DEFAULT 0,
  f_active ENUM('有効','無効') NOT NULL DEFAULT '有効',
  f_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (fk_factory_id) REFERENCES t_factory(pk_factory_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 保守・消耗品交換履歴（PDCA：保守計画→実施→期日管理）
CREATE TABLE IF NOT EXISTS t_setsubi_koukan (
  pk_koukan_id VARCHAR(36) NOT NULL PRIMARY KEY,
  fk_setsubi_id VARCHAR(36) NOT NULL,
  f_koukan_date DATE NOT NULL,
  fk_tantosha_id VARCHAR(36) NOT NULL,
  f_naiyo TEXT,
  f_biko TEXT,
  f_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (fk_setsubi_id) REFERENCES t_setsubi(pk_setsubi_id) ON DELETE CASCADE,
  FOREIGN KEY (fk_tantosha_id) REFERENCES t_tantosha(pk_tantosha_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 日次チェック（ヘッダ：PDCA「日次チェック→不具合の発見→対応計画」の起点）
CREATE TABLE IF NOT EXISTS t_nichiji_check (
  pk_check_id VARCHAR(36) NOT NULL PRIMARY KEY,
  fk_factory_id VARCHAR(36) NOT NULL,
  f_check_date DATE NOT NULL,
  fk_tantosha_id VARCHAR(36) NOT NULL,
  f_biko TEXT,
  f_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (fk_factory_id) REFERENCES t_factory(pk_factory_id),
  FOREIGN KEY (fk_tantosha_id) REFERENCES t_tantosha(pk_tantosha_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 日次チェック明細（設備ごとの点検結果）
CREATE TABLE IF NOT EXISTS t_nichiji_check_meisai (
  pk_check_meisai_id VARCHAR(36) NOT NULL PRIMARY KEY,
  fk_check_id VARCHAR(36) NOT NULL,
  fk_setsubi_id VARCHAR(36) NOT NULL,
  f_result ENUM('正常','異常') NOT NULL DEFAULT '正常',
  f_comment TEXT,
  f_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (fk_check_id) REFERENCES t_nichiji_check(pk_check_id) ON DELETE CASCADE,
  FOREIGN KEY (fk_setsubi_id) REFERENCES t_setsubi(pk_setsubi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 不具合・行動計画（日次チェックの異常から連携、または単独登録）
CREATE TABLE IF NOT EXISTS t_fugu (
  pk_fugu_id VARCHAR(36) NOT NULL PRIMARY KEY,
  fk_factory_id VARCHAR(36) NOT NULL,
  fk_setsubi_id VARCHAR(36) DEFAULT NULL,
  fk_check_meisai_id VARCHAR(36) DEFAULT NULL,
  f_title VARCHAR(200) NOT NULL,
  f_detail TEXT,
  f_taiou_keikaku TEXT,
  fk_tantosha_id VARCHAR(36) NOT NULL,
  f_kigen_date DATE,
  f_priority ENUM('高','中','低') NOT NULL DEFAULT '中',
  f_status ENUM('未着手','対応中','完了') NOT NULL DEFAULT '未着手',
  f_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (fk_factory_id) REFERENCES t_factory(pk_factory_id),
  FOREIGN KEY (fk_setsubi_id) REFERENCES t_setsubi(pk_setsubi_id),
  FOREIGN KEY (fk_check_meisai_id) REFERENCES t_nichiji_check_meisai(pk_check_meisai_id),
  FOREIGN KEY (fk_tantosha_id) REFERENCES t_tantosha(pk_tantosha_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 安全パトロール（ヘッダ：計画→実施）
CREATE TABLE IF NOT EXISTS t_patrol (
  pk_patrol_id VARCHAR(36) NOT NULL PRIMARY KEY,
  fk_factory_id VARCHAR(36) NOT NULL,
  f_patrol_date DATE NOT NULL,
  fk_tantosha_id VARCHAR(36) NOT NULL,
  f_status ENUM('計画','実施済') NOT NULL DEFAULT '計画',
  f_biko TEXT,
  f_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (fk_factory_id) REFERENCES t_factory(pk_factory_id),
  FOREIGN KEY (fk_tantosha_id) REFERENCES t_tantosha(pk_tantosha_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 安全パトロール明細（指摘事項・対策）
CREATE TABLE IF NOT EXISTS t_patrol_meisai (
  pk_patrol_meisai_id VARCHAR(36) NOT NULL PRIMARY KEY,
  fk_patrol_id VARCHAR(36) NOT NULL,
  f_check_item VARCHAR(200) NOT NULL,
  f_result ENUM('良','要改善') NOT NULL DEFAULT '良',
  f_shiteki_naiyo TEXT,
  f_taisaku TEXT,
  f_taisaku_kigen DATE,
  f_taisaku_status ENUM('未対応','対応中','完了') NOT NULL DEFAULT '未対応',
  f_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (fk_patrol_id) REFERENCES t_patrol(pk_patrol_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 工程表（生産技術ミーティングで用いる工期データ／メンテ予定との競合可視化に使用）
CREATE TABLE IF NOT EXISTS t_koutei (
  pk_koutei_id VARCHAR(36) NOT NULL PRIMARY KEY,
  fk_factory_id VARCHAR(36) NOT NULL,
  f_koutei_name VARCHAR(200) NOT NULL,
  f_start_date DATE NOT NULL,
  f_end_date DATE NOT NULL,
  f_tanto VARCHAR(100),
  f_biko TEXT,
  f_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (fk_factory_id) REFERENCES t_factory(pk_factory_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- サンプルデータ（動作確認用。既存 t_factory / t_tantosha が
-- 空の場合に備え、工場が無ければ1件だけ追加してから投入する）
-- ============================================================
INSERT INTO t_factory (pk_factory_id, f_factory_name, f_zip, f_address, f_tel, f_tanto_name, f_biko, f_sort_order, f_active, f_created_at)
SELECT 'fac00000-0000-0000-0000-000000000099','観音寺工場','','','','','サンプル工場（データが無い場合のみ追加）',99,'有効',NOW()
WHERE NOT EXISTS (SELECT 1 FROM t_factory);

SET @fid := (SELECT pk_factory_id FROM t_factory ORDER BY f_sort_order,f_factory_name LIMIT 1);
SET @tid := (SELECT pk_tantosha_id FROM t_tantosha ORDER BY f_created_at LIMIT 1);

INSERT INTO t_setsubi (pk_setsubi_id, fk_factory_id, f_setsubi_name, f_setsubi_kubun, f_type_no, f_setti_date, f_koukan_shuki_days, f_biko, f_sort_order, f_active, f_created_at, f_updated_at)
SELECT 'set00001-0000-0000-0000-000000000001', @fid, '破砕機（1号機）','設備','SK-2000','2020-04-01',NULL,'主力破砕ライン',1,'有効',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM t_setsubi);

INSERT INTO t_setsubi (pk_setsubi_id, fk_factory_id, f_setsubi_name, f_setsubi_kubun, f_type_no, f_setti_date, f_koukan_shuki_days, f_biko, f_sort_order, f_active, f_created_at, f_updated_at)
SELECT 'set00002-0000-0000-0000-000000000002', @fid, '破砕刃（1号機用）','消耗部品','BL-450','2026-06-01',90,'定期交換部品',2,'有効',NOW(),NOW()
WHERE (SELECT COUNT(*) FROM t_setsubi) < 2;

INSERT INTO t_koutei (pk_koutei_id, fk_factory_id, f_koutei_name, f_start_date, f_end_date, f_tanto, f_biko, f_created_at)
SELECT 'kot00001-0000-0000-0000-000000000001', @fid, 'RPF製造ライン　定期稼働', DATE_FORMAT(NOW(),'%Y-%m-01'), DATE_FORMAT(NOW(),'%Y-%m-28'), '生産技術課','',NOW()
WHERE NOT EXISTS (SELECT 1 FROM t_koutei);
