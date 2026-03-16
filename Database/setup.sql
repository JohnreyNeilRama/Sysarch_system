-- Create database in XAMPP MySQL
-- Run this in phpMyAdmin (http://localhost/phpmyadmin)

CREATE DATABASE IF NOT EXISTS ccs_sitin;

USE ccs_sitin;

-- Create students table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(50) NOT NULL UNIQUE,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    course VARCHAR(100) NOT NULL,
    year_level VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    profile_picture VARCHAR(255) DEFAULT 'default.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- If table already exists, add profile_picture column:
-- ALTER TABLE students ADD COLUMN profile_picture VARCHAR(255) DEFAULT 'default.png';

-- Create announcements table
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_name VARCHAR(100) NOT NULL,
    announcement_date DATE NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample announcements
INSERT INTO announcements (admin_name, announcement_date, message) VALUES 
('CCS Admin', '2026-02-11', 'Important Announcement: We are excited to announce the launch of our new website. Explore our latest products and services now.'),
('CCS Admin', '2024-05-08', 'Welcome to the CCS Sit-in Monitoring System! Please remember to log your sit-in sessions regularly.');
