CREATE DATABASE IF NOT EXISTS upcycle;
USE upcycle;

CREATE TABLE IF NOT EXISTS users (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    oauth_provider VARCHAR(20) NULL,
    oauth_id VARCHAR(255) NULL,
    profile_picture VARCHAR(500) NULL,
    UNIQUE INDEX idx_oauth (oauth_provider, oauth_id)
);