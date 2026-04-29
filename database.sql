-- =============================================
-- Sport Center Hub — Database Schema v2
-- Fixes: users/auth, updated pricing (Surabaya)
-- =============================================

-- ── Users (Admin & Customer) ──
CREATE TABLE IF NOT EXISTS users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(150) NOT NULL,
  email        VARCHAR(150) NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  role         ENUM('admin','customer') DEFAULT 'customer',
  phone        VARCHAR(20),
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Courts ──
CREATE TABLE IF NOT EXISTS courts (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(100) NOT NULL,
  type           ENUM('Padel','Badminton','Tennis') NOT NULL,
  price_per_hour DECIMAL(10,2) NOT NULL,
  description    TEXT,
  status         ENUM('active','inactive') DEFAULT 'active',
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Reservations ──
CREATE TABLE IF NOT EXISTS reservations (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  court_id       INT NOT NULL,
  user_id        INT NULL,
  renter_name    VARCHAR(150) NOT NULL,
  renter_phone   VARCHAR(20),
  booking_date   DATE NOT NULL,
  start_time     TIME NOT NULL,
  end_time       TIME NOT NULL,
  total_price    DECIMAL(12,2),
  status         ENUM('confirmed','cancelled') DEFAULT 'confirmed',
  notes          TEXT,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (court_id) REFERENCES courts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_court_date (court_id, booking_date)
);

-- ── Seed: Users ──
-- All demo passwords below are: "password"
-- Hash = password_hash("password", PASSWORD_BCRYPT)
INSERT IGNORE INTO users (name, email, password, role, phone) VALUES
('Hub Admin',    'admin@sporthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',    NULL),
('John Doe',     'john@example.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '081234567890'),
('Sarah Wilson', 'sarah@example.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '082345678901');


-- ── Seed: Courts (Surabaya Barat pricing) ──
INSERT IGNORE INTO courts (name, type, price_per_hour, description) VALUES
('Padel Court A',     'Padel',    350000, 'Premium indoor padel court with glass walls, air-conditioned'),
('Padel Court B',     'Padel',    300000, 'Standard indoor padel court, great for beginners and regulars'),
('Badminton Court 1', 'Badminton',110000, 'Full-size badminton court with wooden flooring, BWF standard net'),
('Badminton Court 2', 'Badminton',110000, 'Full-size badminton court with sports lighting'),
('Badminton Court 3', 'Badminton',100000, 'Economy badminton court, great value'),
('Tennis Court A',    'Tennis',   250000, 'Hard-court tennis, ITF standard, covered lighting');

-- ── Seed: Reservations ──
INSERT IGNORE INTO reservations (court_id, user_id, renter_name, renter_phone, booking_date, start_time, end_time, total_price, notes) VALUES
(1, 2, 'John Doe',      '081234567890', CURDATE(), '08:00:00', '10:00:00', 700000,  'Regular player'),
(3, 3, 'Sarah Wilson',  '082345678901', CURDATE(), '09:00:00', '10:00:00', 110000,  NULL),
(6, NULL, 'Ahmad Fauzi','083456789012', CURDATE(), '07:00:00', '09:00:00', 500000,  'VIP member'),
(2, NULL, 'Dewi Lestari','084567890123',CURDATE(), '13:00:00', '15:00:00', 600000,  NULL),
(4, NULL, 'Rudi Hartono','085678901234',CURDATE(), '16:00:00', '18:00:00', 220000,  'Tournament practice'),
(1, 2, 'John Doe',      '081234567890', DATE_ADD(CURDATE(),INTERVAL 1 DAY), '10:00:00','12:00:00',700000, NULL),
(5, 3, 'Sarah Wilson',  '082345678901', DATE_ADD(CURDATE(),INTERVAL 2 DAY), '08:00:00','09:00:00',100000, NULL);
