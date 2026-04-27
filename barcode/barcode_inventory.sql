-- ─────────────────────────────────────────────
--  Barcode Inventory Database
--  Import this file via phpMyAdmin or run:
--  mysql -u root -p < barcode_inventory.sql
-- ─────────────────────────────────────────────

CREATE DATABASE IF NOT EXISTS `barcode_inventory`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `barcode_inventory`;

CREATE TABLE IF NOT EXISTS `items` (
  `id`                INT          NOT NULL AUTO_INCREMENT,
  `barcode_number`    VARCHAR(255) NOT NULL,
  `barcode_image`     VARCHAR(512)     DEFAULT NULL,
  `name`              VARCHAR(255) NOT NULL,
  `photo`             VARCHAR(512)     DEFAULT NULL,
  `drive_photo_link`  VARCHAR(1024)    DEFAULT NULL,
  `color`             VARCHAR(64)      DEFAULT NULL,
  `size`              VARCHAR(64)      DEFAULT NULL,
  `quantity`          INT              DEFAULT 0,
  `created_at`        DATETIME         DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
