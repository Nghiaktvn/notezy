CREATE TABLE IF NOT EXISTS plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS entitlements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  feature_code VARCHAR(64) NOT NULL,
  limit_value INT NOT NULL,
  UNIQUE KEY uq_entitlement (plan_id, feature_code),
  CONSTRAINT fk_entitlement_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan_id INT NOT NULL,
  status ENUM('active','cancelled','expired') NOT NULL DEFAULT 'active',
  starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at DATETIME NULL,
  KEY idx_subscription_user (user_id, status),
  CONSTRAINT fk_subscription_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS usage_records (
  user_id INT NOT NULL,
  feature_code VARCHAR(64) NOT NULL,
  period_date DATE NOT NULL,
  used_count INT NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, feature_code, period_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO plans (id,code,name) VALUES (1,'FREE','Miễn phí'),(2,'PREMIUM','Premium');
INSERT IGNORE INTO entitlements (plan_id,feature_code,limit_value) VALUES
  (1,'AI_QA',5),(1,'AI_SUMMARY',5),(1,'REALTIME_COLLAB',0),(1,'TRAVEL_PLANNER',0),
  (2,'AI_QA',100),(2,'AI_SUMMARY',100),(2,'REALTIME_COLLAB',1),(2,'TRAVEL_PLANNER',1);

CREATE TABLE IF NOT EXISTS premium_trials (
  user_id INT NOT NULL PRIMARY KEY,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at DATETIME NOT NULL,
  CONSTRAINT fk_premium_trial_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO plans (code, name, active) VALUES
  ('STUDENT', 'Sinh viên', 1),
  ('PERSONAL', 'Cá nhân', 1),
  ('TEAM', 'Nhóm / Lớp học', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), active = VALUES(active);

INSERT INTO entitlements (plan_id, feature_code, limit_value)
SELECT id, 'AI_QA', CASE code WHEN 'STUDENT' THEN 40 WHEN 'PERSONAL' THEN 150 WHEN 'TEAM' THEN 300 END
FROM plans WHERE code IN ('STUDENT', 'PERSONAL', 'TEAM')
ON DUPLICATE KEY UPDATE limit_value = VALUES(limit_value);
INSERT INTO entitlements (plan_id, feature_code, limit_value)
SELECT id, 'AI_SUMMARY', CASE code WHEN 'STUDENT' THEN 40 WHEN 'PERSONAL' THEN 150 WHEN 'TEAM' THEN 300 END
FROM plans WHERE code IN ('STUDENT', 'PERSONAL', 'TEAM')
ON DUPLICATE KEY UPDATE limit_value = VALUES(limit_value);
INSERT INTO entitlements (plan_id, feature_code, limit_value)
SELECT id, 'SCHEDULE_SHARING', 1 FROM plans WHERE code IN ('STUDENT', 'PERSONAL', 'TEAM')
ON DUPLICATE KEY UPDATE limit_value = VALUES(limit_value);
