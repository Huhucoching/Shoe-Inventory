-- Shoes Inventory and Sales Management System
-- Import this file in phpMyAdmin or mysql CLI.

CREATE DATABASE IF NOT EXISTS shoes_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shoes_inventory;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS sales_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(30) NOT NULL UNIQUE,
    shoe_name VARCHAR(150) NOT NULL,
    brand VARCHAR(80) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    size VARCHAR(20) NOT NULL,
    color VARCHAR(50) NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    image_path VARCHAR(255) NULL DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    date_added DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE,
    INDEX idx_products_name (shoe_name),
    INDEX idx_products_brand (brand),
    INDEX idx_products_category (category_id),
    INDEX idx_products_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE sales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    processed_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sales_user FOREIGN KEY (processed_by) REFERENCES users(id) ON UPDATE CASCADE,
    INDEX idx_sales_date (created_at)
) ENGINE=InnoDB;

CREATE TABLE sales_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_sales_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sales_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE,
    INDEX idx_sales_items_product (product_id)
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    movement_type ENUM('IN', 'OUT') NOT NULL,
    reason VARCHAR(120) NULL,
    staff_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE,
    CONSTRAINT fk_stock_user FOREIGN KEY (staff_id) REFERENCES users(id) ON UPDATE CASCADE,
    INDEX idx_stock_date (created_at),
    INDEX idx_stock_type (movement_type)
) ENGINE=InnoDB;

INSERT INTO users (full_name, username, email, password_hash, role, is_active)
VALUES
('System Administrator', 'admin', 'admin@local.test', '$2y$10$0Qq7clKhZjuQIBUXFXWZ2e2Gu1BZbvVzuJDZCqPXf8jcupUFqmcqi', 'admin', 1);

INSERT INTO categories (name, description)
VALUES
('Running', 'Performance and running shoes'),
('Casual', 'Everyday casual footwear'),
('Basketball', 'High-top and court shoes'),
('Training', 'Gym and workout footwear');
