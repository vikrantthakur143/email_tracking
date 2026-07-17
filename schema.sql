-- Email Open Tracking - Database Schema
-- Run this once to set up the database and table.

CREATE DATABASE IF NOT EXISTS email_tracking
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE email_tracking;

-- One row per email you send out (generated when the email is created/sent)
CREATE TABLE IF NOT EXISTS tracked_emails (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_id   CHAR(36) NOT NULL UNIQUE,      -- UUID embedded in the pixel URL
    recipient     VARCHAR(255) DEFAULT NULL,     -- optional: email address / user id
    subject       VARCHAR(255) DEFAULT NULL,
    campaign      VARCHAR(100) DEFAULT NULL,     -- optional: campaign/batch name
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient),
    INDEX idx_campaign (campaign)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per pixel "hit" (an email can be opened multiple times, on multiple devices)
CREATE TABLE IF NOT EXISTS email_opens (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_id   CHAR(36) NOT NULL,
    ip_address    VARCHAR(45) NOT NULL,          -- IPv4 or IPv6
    user_agent    VARCHAR(512) DEFAULT NULL,
    referer       VARCHAR(512) DEFAULT NULL,
    accept_lang   VARCHAR(255) DEFAULT NULL,     -- Accept-Language header (rough locale signal)
    opened_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tracking_id) REFERENCES tracked_emails(tracking_id)
        ON DELETE CASCADE,
    INDEX idx_tracking_id (tracking_id),
    INDEX idx_opened_at (opened_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
