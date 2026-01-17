-- ===== PharmaSys Database Schema =====
-- Run this in phpMyAdmin or: mysql -u root -p < pharmacy_db.sql

CREATE DATABASE IF NOT EXISTS pharmacy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacy;

-- Drop tables if exist (for clean setup)
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS batches;
DROP TABLE IF EXISTS medicines;
DROP TABLE IF EXISTS users;

-- ===== TABLES =====

-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Medicines (master list)
CREATE TABLE medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    generic_name VARCHAR(255),
    dosage VARCHAR(100),
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Batches (inventory units — with expiry & stock)
CREATE TABLE batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    batch_number VARCHAR(100) NOT NULL,
    expiry_date DATE NOT NULL,
    quantity INT NOT NULL COMMENT 'Original quantity received',
    purchase_price DECIMAL(10,2) NOT NULL,
    remaining_stock INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    INDEX idx_medicine_id (medicine_id),
    INDEX idx_expiry (expiry_date, remaining_stock)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sales (header)
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255),
    total DECIMAL(10,2) NOT NULL,
    sold_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sale items (line items)
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    batch_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    INDEX idx_sale_id (sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== SAMPLE DATA =====

-- Sample user (password: admin123)
INSERT INTO users (username, password_hash, full_name, role) VALUES
('admin', '$2y$10$YourHashedPasswordHere', 'Administrator', 'admin');

-- To generate password hash, use in PHP:
-- password_hash('admin123', PASSWORD_DEFAULT)
-- Or use this temporary hash for testing: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

-- Sample medicines
INSERT INTO medicines (name, generic_name, dosage, price) VALUES
('Crocin Advance', 'Paracetamol', '500mg Tablet', 25.00),
('Amoxil Capsules', 'Amoxicillin', '250mg Capsule', 45.50),
('Dolo 650', 'Paracetamol', '650mg Tablet', 30.00),
('Zyrtec Syrup', 'Cetirizine', '5mg/5ml', 85.00),
('Azithral 500', 'Azithromycin', '500mg Tablet', 120.00);

-- Sample batches
INSERT INTO batches (medicine_id, batch_number, expiry_date, quantity, purchase_price, remaining_stock) VALUES
(1, 'B24A1001', DATE_ADD(CURDATE(), INTERVAL 5 DAY), 100, 18.00, 85),
(1, 'B24B2002', DATE_ADD(CURDATE(), INTERVAL 45 DAY), 200, 17.50, 200),
(2, 'AMX2025', DATE_ADD(CURDATE(), INTERVAL 120 DAY), 50, 35.00, 50),
(3, 'D65-771', DATE_ADD(CURDATE(), INTERVAL 25 DAY), 150, 22.00, 150),
(4, 'ZYR-9921', DATE_ADD(CURDATE(), INTERVAL 8 DAY), 30, 65.00, 12),
(5, 'AZT-500', DATE_ADD(CURDATE(), INTERVAL 60 DAY), 80, 90.00, 80);

-- Sample sale
INSERT INTO sales (customer_name, total) VALUES ('Walk-in Customer', 110.00);
INSERT INTO sale_items (sale_id, batch_id, medicine_id, quantity, unit_price) VALUES
(1, 1, 1, 2, 25.00),
(1, 4, 3, 2, 30.00);

-- ===== INDEXES FOR PERFORMANCE =====
CREATE INDEX idx_medicines_name ON medicines(name);
CREATE INDEX idx_batches_stock ON batches(remaining_stock);
CREATE INDEX idx_sales_date ON sales(sold_at);

-- ===== VIEWS FOR REPORTING =====
CREATE VIEW view_low_stock AS
SELECT 
    m.id, m.name, m.dosage, m.price,
    b.batch_number, b.expiry_date, b.remaining_stock,
    DATEDIFF(b.expiry_date, CURDATE()) as days_left
FROM batches b
JOIN medicines m ON b.medicine_id = m.id
WHERE b.remaining_stock <= 10 AND b.remaining_stock > 0
ORDER BY b.remaining_stock ASC;

CREATE VIEW view_daily_sales AS
SELECT 
    DATE(sold_at) as sale_date,
    COUNT(*) as total_sales,
    SUM(total) as total_revenue
FROM sales
GROUP BY DATE(sold_at)
ORDER BY sale_date DESC;