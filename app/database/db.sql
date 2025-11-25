
-- --------------------------------------------------
-- 1) Base table creation (creates table only if it does not exist)
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(100) NULL,
  `last_name` VARCHAR(100) NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `adminuser` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'admin',
  `is_super` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(128) NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `admin_user_id` INT UNSIGNED NULL,
  `payload` LONGTEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(512) NULL,
  `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign keys for sessions.user_id and sessions.admin_user_id if missing
SET @fk_cnt := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'sessions' AND CONSTRAINT_NAME = 'fk_sessions_user');
SET @sql = IF(@fk_cnt = 0,
    'ALTER TABLE `sessions` ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_cnt2 := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'sessions' AND CONSTRAINT_NAME = 'fk_sessions_adminuser');
SET @sql2 = IF(@fk_cnt2 = 0,
    'ALTER TABLE `sessions` ADD CONSTRAINT `fk_sessions_adminuser` FOREIGN KEY (`admin_user_id`) REFERENCES `adminuser`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;
  
-- --------------------------------------------------
-- 2) Category and Products
-- Categories are stored as an adjacency list (parent_id) so categories can be nested
-- Products are stored in `products` and linked to categories via `product_category` (many-to-many)
-- --------------------------------------------------

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `description` TEXT NULL,
  `parent_id` INT UNSIGNED NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_categories_slug` (`slug`),
  KEY `idx_categories_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku` VARCHAR(100) NULL,
  `title` VARCHAR(191) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `description` LONGTEXT NULL,
  `long_description` LONGTEXT NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stock` INT NOT NULL DEFAULT 0,
  -- images: stored as JSON string or comma-separated list; using LONGTEXT for broad compatibility
  `images` LONGTEXT NULL,
  `primary_category_id` INT UNSIGNED NULL,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_products_slug` (`slug`),
  UNIQUE KEY `ux_products_sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Add FK for products.primary_category_id referencing categories(id) if missing
SET @fk_cnt6 := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND CONSTRAINT_NAME = 'fk_products_primary_category');
SET @sql6 = IF(@fk_cnt6 = 0,
  'ALTER TABLE `products` ADD CONSTRAINT `fk_products_primary_category` FOREIGN KEY (`primary_category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt6 FROM @sql6; EXECUTE stmt6; DEALLOCATE PREPARE stmt6;
 
-- Add foreign keys (idempotent approach would be used by sync script).
-- We attempt to add FKs but avoid erroring when they already exist using information_schema in the sync flow.
-- Add foreign key for categories.parent_id if missing (idempotent)
SET @fk_cnt3 := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND CONSTRAINT_NAME = 'fk_categories_parent');
SET @sql3 = IF(@fk_cnt3 = 0,
  'ALTER TABLE `categories` ADD CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;


-- ---------order tables---------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orders_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Add foreign keys for orders and order_items if missing
SET @fk_cnt4 := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND CONSTRAINT_NAME = 'fk_orders_user');
SET @sql4 = IF(@fk_cnt4 = 0,
    'ALTER TABLE `orders` ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt4 FROM @sql4; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;  

SET @fk_cnt5 := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND CONSTRAINT_NAME = 'fk_order_items_order');
SET @sql5 = IF(@fk_cnt5 = 0,
    'ALTER TABLE `order_items` ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1');  
PREPARE stmt5 FROM @sql5; EXECUTE stmt5; DEALLOCATE PREPARE stmt5;

-- Ensure orders table has shipping columns (idempotent)
SET @col_cnt_ship_first := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_first_name');
SET @sql_ship_first = IF(@col_cnt_ship_first = 0,
  'ALTER TABLE `orders` ADD COLUMN `shipping_first_name` VARCHAR(191) NULL AFTER `status`',
  'SELECT 1');
PREPARE stmt_ship_first FROM @sql_ship_first; EXECUTE stmt_ship_first; DEALLOCATE PREPARE stmt_ship_first;

SET @col_cnt_ship_last := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_last_name');
SET @sql_ship_last = IF(@col_cnt_ship_last = 0,
  'ALTER TABLE `orders` ADD COLUMN `shipping_last_name` VARCHAR(191) NULL AFTER `shipping_first_name`',
  'SELECT 1');
PREPARE stmt_ship_last FROM @sql_ship_last; EXECUTE stmt_ship_last; DEALLOCATE PREPARE stmt_ship_last;

SET @col_cnt_ship_phone := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_phone');
SET @sql_ship_phone = IF(@col_cnt_ship_phone = 0,
  'ALTER TABLE `orders` ADD COLUMN `shipping_phone` VARCHAR(50) NULL AFTER `shipping_last_name`',
  'SELECT 1');
PREPARE stmt_ship_phone FROM @sql_ship_phone; EXECUTE stmt_ship_phone; DEALLOCATE PREPARE stmt_ship_phone;

SET @col_cnt_ship_addr := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_address');
SET @sql_ship_addr = IF(@col_cnt_ship_addr = 0,
  'ALTER TABLE `orders` ADD COLUMN `shipping_address` LONGTEXT NULL AFTER `shipping_phone`',
  'SELECT 1');
PREPARE stmt_ship_addr FROM @sql_ship_addr; EXECUTE stmt_ship_addr; DEALLOCATE PREPARE stmt_ship_addr;

-- Ensure new product columns exist (idempotent): `is_featured`, `long_description`
SET @col_cnt_is_featured := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'is_featured');
SET @sql_is_featured = IF(@col_cnt_is_featured = 0,
  'ALTER TABLE `products` ADD COLUMN `is_featured` TINYINT(1) NOT NULL DEFAULT 0 AFTER `primary_category_id`',
  'SELECT 1');
PREPARE stmt_is_featured FROM @sql_is_featured; EXECUTE stmt_is_featured; DEALLOCATE PREPARE stmt_is_featured;

SET @col_cnt_long_desc := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'long_description');
SET @sql_long_desc = IF(@col_cnt_long_desc = 0,
  'ALTER TABLE `products` ADD COLUMN `long_description` LONGTEXT NULL AFTER `description`',
  'SELECT 1');
PREPARE stmt_long_desc FROM @sql_long_desc; EXECUTE stmt_long_desc; DEALLOCATE PREPARE stmt_long_desc;

-- --------------------------------------------------
-- 3) Carts table (stores per-user or per-session cart rows)
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `carts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `session_id` VARCHAR(128) NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_carts_user` (`user_id`),
  KEY `idx_carts_session` (`session_id`),
  KEY `idx_carts_product` (`product_id`),
  UNIQUE KEY `ux_carts_user_product` (`user_id`,`product_id`),
  UNIQUE KEY `ux_carts_session_product` (`session_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FK: carts.user_id -> users.id (idempotent)
SET @fk_cnt_carts_user := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'carts' AND CONSTRAINT_NAME = 'fk_carts_user');
SET @sql_carts_user = IF(@fk_cnt_carts_user = 0,
  'ALTER TABLE `carts` ADD CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt_carts_user FROM @sql_carts_user; EXECUTE stmt_carts_user; DEALLOCATE PREPARE stmt_carts_user;
