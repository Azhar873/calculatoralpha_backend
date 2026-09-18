-- Database Schema for Calchub

CREATE DATABASE IF NOT EXISTS calchub_db;
USE calchub_db;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    api_token VARCHAR(255) UNIQUE,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    icon VARCHAR(255),
    color VARCHAR(255),
    description TEXT,
    meta_title TEXT,
    meta_description TEXT,
    meta_keywords TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Calculators Table
CREATE TABLE IF NOT EXISTS calculators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    icon VARCHAR(255),
    description TEXT,
    meta JSON, -- Stores formula data, related tools, etc.
    language VARCHAR(50) DEFAULT 'English',
    heading VARCHAR(255),
    meta_title TEXT,
    meta_description TEXT,
    no_index BOOLEAN DEFAULT FALSE,
    mathjax BOOLEAN DEFAULT FALSE,
    sitemap_index BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    seo_points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Search Logs Table
CREATE TABLE IF NOT EXISTS search_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query VARCHAR(255) NOT NULL,
    results_count INT DEFAULT 0,
    user_id INT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Contact Inquiries Table
CREATE TABLE IF NOT EXISTS contact_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    status ENUM('pending', 'read', 'replied') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Settings Table
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(255) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Pages Table
CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    heading VARCHAR(255),
    banner_image VARCHAR(255),
    description TEXT,
    meta_title TEXT,
    meta_description TEXT,
    show_in_header TINYINT(1) NOT NULL DEFAULT 0,
    footer_text TEXT,
    footer_section VARCHAR(50) DEFAULT NULL,
    page_type VARCHAR(50) DEFAULT 'default',
    contact_email VARCHAR(255) DEFAULT NULL,
    contact_phone VARCHAR(100) DEFAULT NULL,
    contact_address TEXT DEFAULT NULL,
    contact_intro TEXT DEFAULT NULL,
    contact_form_title VARCHAR(255) DEFAULT NULL,
    contact_submit_label VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sitemap URLs Table
CREATE TABLE IF NOT EXISTS sitemap (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url_type ENUM('home', 'calculator', 'category', 'page') NOT NULL,
    source_id INT DEFAULT NULL,
    slug VARCHAR(255) DEFAULT NULL,
    url VARCHAR(500) NOT NULL,
    lastmod DATE NOT NULL,
    changefreq VARCHAR(20) NOT NULL DEFAULT 'monthly',
    priority DECIMAL(2,1) NOT NULL DEFAULT 0.8,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sitemap_source (url_type, source_id),
    KEY idx_sitemap_url (url)
);
