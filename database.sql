CREATE DATABASE IF NOT EXISTS medizininformatik
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE medizininformatik;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','listener') NOT NULL DEFAULT 'listener',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
