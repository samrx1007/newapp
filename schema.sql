CREATE DATABASE IF NOT EXISTS login_system;
USE login_system;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bilty_number VARCHAR(50) NOT NULL,
    bilty_date DATE,
    importer VARCHAR(100),
    exporter VARCHAR(100),
    sb_number VARCHAR(50),
    sb_date DATE,
    amount DECIMAL(12,2) DEFAULT 0,
    other VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS direct_bilty (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    entry_date DATE,
    bilty_number VARCHAR(50) NOT NULL,
    truck_number VARCHAR(50),
    from_location VARCHAR(100),
    to_location VARCHAR(100),
    bilty_freight DECIMAL(12,2) DEFAULT 0,
    advance DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS advances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bilty_number VARCHAR(50) NOT NULL,
    adv_date DATE,
    adv_office VARCHAR(100),
    advance_amount DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
