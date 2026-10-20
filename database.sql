-- =====================================================
-- MIPANGO Inventory Management System
-- Database Schema (3rd Normal Form)
-- =====================================================

CREATE DATABASE IF NOT EXISTS pharmacy_ims CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacy_ims;

-- ---------------------------------------------------
-- Table: users
-- ---------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: products (medicines)
-- ---------------------------------------------------
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(150) NOT NULL,
    sku VARCHAR(50) NULL UNIQUE,
    barcode VARCHAR(80) NULL UNIQUE,
    category ENUM('wines','whiskeys_spirits') NOT NULL DEFAULT 'whiskeys_spirits',
    size VARCHAR(50) NULL,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    current_stock INT NOT NULL DEFAULT 0,
    description TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: stock_audits
-- ---------------------------------------------------
CREATE TABLE stock_audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_date DATE NOT NULL,
    product_id INT NOT NULL,
    system_qty INT NOT NULL,
    physical_qty INT NOT NULL,
    difference INT NOT NULL,
    reason VARCHAR(255) NULL,
    corrected TINYINT(1) NOT NULL DEFAULT 0,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_date (audit_date),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: customer_credit
-- ---------------------------------------------------
CREATE TABLE customer_credit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL UNIQUE,
    credit_limit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: boxes (independent box module)
-- ---------------------------------------------------
CREATE TABLE boxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    box_name VARCHAR(150) NOT NULL,
    current_quantity INT NOT NULL DEFAULT 0,
    description TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: box_in (history)
-- ---------------------------------------------------
CREATE TABLE box_in (
    id INT AUTO_INCREMENT PRIMARY KEY,
    box_id INT NOT NULL,
    quantity INT NOT NULL,
    remark VARCHAR(255) NULL,
    transaction_date DATE NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (box_id) REFERENCES boxes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: box_out (history)
-- ---------------------------------------------------
CREATE TABLE box_out (
    id INT AUTO_INCREMENT PRIMARY KEY,
    box_id INT NOT NULL,
    quantity INT NOT NULL,
    reason VARCHAR(255) NULL,
    transaction_date DATE NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (box_id) REFERENCES boxes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: stock_in (medicines history)
-- ---------------------------------------------------
CREATE TABLE stock_in (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    remark VARCHAR(255) NULL,
    transaction_date DATE NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: stock_out (medicines history)
-- ---------------------------------------------------
CREATE TABLE stock_out (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    reason VARCHAR(255) NULL,
    transaction_date DATE NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: sales
-- ---------------------------------------------------
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    remaining_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('paid','partial','unpaid') NOT NULL DEFAULT 'unpaid',
    sale_date DATE NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: cash_declarations
-- ---------------------------------------------------
CREATE TABLE cash_declarations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    declaration_date DATE NOT NULL,
    expected_cash DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    declared_cash DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    difference DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    admin_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_date (user_id, declaration_date),
    INDEX idx_date (declaration_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: payments (debt payment history)
-- ---------------------------------------------------
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: expenses
-- ---------------------------------------------------
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category ENUM('rent','utilities','salaries','transport','maintenance','supplies','other') NOT NULL DEFAULT 'other',
    amount DECIMAL(12,2) NOT NULL,
    expense_date DATE NOT NULL,
    notes TEXT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Table: period_locks
-- ---------------------------------------------------
CREATE TABLE period_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lock_from DATE NOT NULL,
    lock_to DATE NOT NULL,
    reason VARCHAR(255) NULL,
    locked_by INT NULL,
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_range (lock_from, lock_to)
) ENGINE=InnoDB;

-- =====================================================
-- Sample login credentials
-- Username: admin   | Password: admin123  (role: admin)
-- Username: staff   | Password: staff123  (role: staff)
-- =====================================================
INSERT INTO users (full_name, username, password, role, status) VALUES
('System Administrator', 'admin', '$2y$10$JQEsYyRwrbC99IEmQQcxu.yuVSda3IPa4PGGBrQtC9pl5Dx5V0L7q', 'admin', 'active'),
('LOUNGE Staff', 'staff', '$2y$10$IncqcaP76jslPqtbm0CNa.RLcS.yS5a99aEHcMR6ceBxAip4QyfBi', 'staff', 'active');

-- Login credentials:
--   admin / admin123
--   staff / staff123
-- These are valid bcrypt hashes compatible with PHP's password_verify().

INSERT INTO products (product_name, category, size, cost_price, selling_price, current_stock, status) VALUES

-- ============ BEERS ============
('Caster Lite',               'beers', '6-pack',  34000.00, 0.00, 6, 'active'),
('Kilimanjaro Ndogo',         'beers', 'small',   34000.00, 0.00, 6, 'active'),
('Kilimanjaro Kubwa',         'beers', 'large',   36000.00, 0.00, 4, 'active'),
('Safari Ndogo',              'beers', 'small',   34000.00, 0.00, 6, 'active'),
('Safari Kubwa',              'beers', 'large',   36000.00, 0.00, 4, 'active'),
('S. Lite',                   'beers', 'bottle',  41000.00, 0.00,10, 'active'),
('S. Lemon',                  'beers', 'bottle',  33000.00, 0.00, 6, 'active'),
('S. Lager',                  'beers', 'bottle',  40000.00, 0.00,10, 'active'),
('S. Lager Kubwa',            'beers', 'large',   36000.00, 0.00, 6, 'active'),
('Safari Cane',               'beers', 'box',     56000.00, 0.00, 1, 'active'),
('Caster Cane',               'beers', 'box',     56000.00, 0.00, 4, 'active'),
('Kil Lite Box',              'beers', 'box',     47000.00, 0.00, 2, 'active'),
('Flying Fish',               'beers', 'bottle',  47000.00, 0.00, 4, 'active'),
('Flying Fish Box',           'beers', 'box',     47000.00, 0.00, 3, 'active'),
('Savana',                    'beers', 'bottle',  80000.00, 0.00, 2, 'active'),
('Savana Box',                'beers', 'box',     80000.00, 0.00, 2, 'active'),
('Brutal',                    'beers', 'box',     92000.00, 0.00, 2, 'active'),
('Smoth Ice',                 'beers', 'box',     88000.00, 0.00, 1, 'active'),
('Heineken',                  'beers', 'box',     47000.00, 0.00, 5, 'active'),
('Windhoek',                  'beers', 'box',     76000.00, 0.00, 5, 'active'),
('Desperado',                 'beers', 'box',     76000.00, 0.00, 5, 'active'),
('Azam',                      'beers', 'box',     18000.00, 0.00, 5, 'active'),
('Bavaria',                   'beers', 'box',     75000.00, 0.00, 2, 'active'),
('Baltika',                   'beers', 'bottle',  90000.00, 0.00, 1, 'active'),
('Castle Lite',               'beers', 'bottle',  29500.00, 0.00,15, 'active'),
('Castle Cane',               'beers', 'box',     47000.00, 0.00,10, 'active'),
('East African Serengeti Lite','beers','bottle',  38000.00, 0.00,10, 'active'),
('Serengeti Lager',           'beers', 'bottle',  31000.00, 0.00,10, 'active'),
('Serengeti Lemon',           'beers', 'bottle',  33000.00, 0.00,15, 'active'),
('Kilimanjaro N',             'beers', 'small',   31000.00, 0.00,10, 'active'),
('Safari N',                  'beers', 'small',   31000.00, 0.00,10, 'active'),
('Safari Cans',               'beers', 'can',     48000.00, 0.00, 5, 'active'),
('Corona',                    'beers', 'bottle',  85000.00, 0.00, 2, 'active'),
('Bruto',                     'beers', 'box',     80000.00, 0.00, 3, 'active'),

-- ============ ENERGY DRINKS ============
('Red Bull Box',              'energy', 'box',    85000.00, 0.00, 3, 'active'),
('Red Bull 5-Pack',           'energy', '5-pack', 21250.00, 0.00, 5, 'active'),

-- ============ SOFT DRINKS ============
('Malta',                     'soft_drinks', 'bottle', 45000.00, 0.00, 3, 'active'),
('Pepsi',                     'soft_drinks', 'bottle', 12500.00, 0.00,10, 'active'),
('Coca-Cola',                 'soft_drinks', 'bottle', 13500.00, 0.00,10, 'active'),
('Soda',                      'soft_drinks', 'bottle', 14000.00, 0.00,10, 'active'),
('Image',                     'soft_drinks', 'bottle',  5000.00, 0.00,10, 'active'),

-- ============ WATER ============
('Maji Caton',                'water', 'bottle',  2500.00, 0.00,30, 'active'),
('Maji',                      'water', 'bottle',  5000.00, 0.00,30, 'active'),

-- ============ WHISKEYS & SPIRITS ============
('Konyagi Kubwa',             'whiskeys_spirits', '750ml', 12000.00, 0.00, 5, 'active'),
('Konyagi Kati',              'whiskeys_spirits', 'medium', 9000.00, 0.00, 5, 'active'),
('Konyagi Ndogo',             'whiskeys_spirits', '200ml',  4000.00, 0.00,10, 'active'),
('K Vant Kubwa',              'whiskeys_spirits', '750ml', 11000.00, 0.00, 5, 'active'),
('K Vant Ndogo',              'whiskeys_spirits', '250ml',  3500.00, 0.00,10, 'active'),
('Hanson Choice Kubwa',       'whiskeys_spirits', '750ml', 13000.00, 0.00, 5, 'active'),
('Hanson Choice Ndogo',       'whiskeys_spirits', '200ml',  4000.00, 0.00,10, 'active'),
('Captain Morgan Kubwa',      'whiskeys_spirits', '750ml', 25000.00, 0.00, 2, 'active'),
('Captain Morgan Ndogo',      'whiskeys_spirits', '200ml',  5000.00, 0.00, 2, 'active'),
('Black & White Kubwa',       'whiskeys_spirits', '750ml', 26000.00, 0.00, 2, 'active'),
('Black & White Ndogo',       'whiskeys_spirits', '200ml', 12000.00, 0.00, 2, 'active'),
('Jack Daniel''s 750ml',      'whiskeys_spirits', '750ml', 75000.00, 0.00, 2, 'active'),
('Jack Daniel''s 375ml',      'whiskeys_spirits', '375ml', 45000.00, 0.00, 2, 'active'),
('Jack Daniel''s 1L',         'whiskeys_spirits', '1L',   105000.00, 0.00, 2, 'active'),
('Grants Kubwa',              'whiskeys_spirits', '750ml', 34000.00, 0.00, 5, 'active'),
('Grants Kati',               'whiskeys_spirits', '375ml', 17000.00, 0.00, 3, 'active'),
('Grants Ndogo',              'whiskeys_spirits', '200ml', 12000.00, 0.00, 2, 'active'),
('Absolute Kubwa',            'whiskeys_spirits', '750ml', 48000.00, 0.00, 2, 'active'),
('Absolute Kati',             'whiskeys_spirits', '375ml', 35000.00, 0.00, 2, 'active'),
('Tequila',                   'whiskeys_spirits', 'bottle',48000.00, 0.00, 1, 'active'),
('Olmeca Tequila',            'whiskeys_spirits', 'bottle',65000.00, 0.00, 1, 'active'),
('Red Label Kubwa',           'whiskeys_spirits', '750ml', 38000.00, 0.00, 2, 'active'),
('Red Label Kati',            'whiskeys_spirits', '375ml', 17000.00, 0.00, 2, 'active'),
('Red Label Ndogo',           'whiskeys_spirits', '200ml', 16000.00, 0.00, 2, 'active'),
('Black Label',               'whiskeys_spirits', '750ml', 85000.00, 0.00, 1, 'active'),
('Smirnoff Vodka Kubwa',      'whiskeys_spirits', '750ml', 38000.00, 0.00, 2, 'active'),
('Smirnoff Vodka Ndogo',      'whiskeys_spirits', '200ml', 13000.00, 0.00, 2, 'active'),
('Jägermeister 750ml',        'whiskeys_spirits', '750ml', 43000.00, 0.00, 2, 'active'),
('Jägermeister 350ml',        'whiskeys_spirits', '350ml', 33000.00, 0.00, 2, 'active'),
('Jameson Kubwa',             'whiskeys_spirits', '750ml', 60000.00, 0.00, 3, 'active'),
('Jameson Kati',              'whiskeys_spirits', '375ml', 45000.00, 0.00, 2, 'active'),
('Hennessy V.S.O.P',          'whiskeys_spirits', '750ml',195000.00, 0.00, 2, 'active'),
('Hennessy VS',               'whiskeys_spirits', '750ml',110000.00, 0.00, 2, 'active'),
('Hennessy Ndogo',            'whiskeys_spirits', '200ml', 60000.00, 0.00, 2, 'active'),
('J. Master Kubwa',           'whiskeys_spirits', 'large', 55000.00, 0.00, 1, 'active'),
('J. Master Kati',            'whiskeys_spirits', 'medium',45000.00, 0.00, 1, 'active'),
('J. Master Ndogo',           'whiskeys_spirits', 'small', 20000.00, 0.00, 1, 'active'),
('Magic Moment Kubwa',        'whiskeys_spirits', 'large', 22000.00, 0.00, 5, 'active'),
('Magic Moment Ndogo',        'whiskeys_spirits', 'small', 13000.00, 0.00, 5, 'active'),
('Zanzi Kubwa',               'whiskeys_spirits', 'large', 26000.00, 0.00, 3, 'active'),
('Zanzi Ndogo',               'whiskeys_spirits', 'small',  7000.00, 0.00,10, 'active'),
('TZEE Kubwa',                'whiskeys_spirits', 'large', 14000.00, 0.00, 4, 'active'),
('TZEE Ndogo',                'whiskeys_spirits', 'small',  7000.00, 0.00,10, 'active'),
('Amalula Kubwa',             'whiskeys_spirits', 'large', 30000.00, 0.00, 2, 'active'),
('Amalula Kati',              'whiskeys_spirits', 'medium',17000.00, 0.00, 2, 'active'),

-- ============ WINES ============
('Four Cousin',               'wines', 'bottle', 16000.00, 0.00, 5, 'active'),
('Drostdy Hof Kubwa',         'wines', '750ml',  16000.00, 0.00, 5, 'active'),
('Drostdy Hof Ndogo',         'wines', '375ml',   8000.00, 0.00, 5, 'active'),
('Robertson',                 'wines', 'bottle', 20000.00, 0.00, 5, 'active'),
('Pearly Bay',                'wines', 'bottle', 16000.00, 0.00, 5, 'active'),
('St Anna',                   'wines', 'bottle', 14000.00, 0.00, 4, 'active');


-- Sample boxes
INSERT INTO boxes (box_name, current_quantity, description, status) VALUES
('Small Carton Box', 50, 'Used for packing small medicine orders', 'active'),
('Medium Carton Box', 30, 'Used for medium-sized deliveries', 'active');

-- Sample expenses
INSERT INTO expenses (title, category, amount, expense_date, notes, user_id) VALUES
('Shop Rent - June', 'rent', 250000.00, '2026-06-01', 'Monthly rent payment', 1),
('Electricity Bill', 'utilities', 45000.00, '2026-06-05', 'TANESCO bill for May', 1),
('Staff Salary - June', 'salaries', 300000.00, '2026-06-28', 'Salary for lounge staff', 1),
('Delivery Transport', 'transport', 15000.00, '2026-06-10', 'Boda boda delivery to customer', 2);
