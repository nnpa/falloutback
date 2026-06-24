-- ======================================================
-- DUMP БАЗЫ ДАННЫХ CALORIE_TRACKER
-- Версия: 1.0
-- Дата: 2026-06-24
-- ======================================================

-- Удаляем базу если существует (ОСТОРОЖНО! Удаляет все данные)
-- DROP DATABASE IF EXISTS `calorie_tracker`;

-- Создаем базу данных
CREATE DATABASE IF NOT EXISTS `calorie_tracker` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Выбираем базу
USE `calorie_tracker`;

-- ======================================================
-- ТАБЛИЦЫ
-- ======================================================

-- ---------- Таблица пользователей ----------
DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `device_key` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `device_key` (`device_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Таблица продуктов ----------
DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `calories_per_100g` INT(11) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Таблица дневника питания ----------
DROP TABLE IF EXISTS `food_intake`;
CREATE TABLE IF NOT EXISTS `food_intake` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `grams` DECIMAL(10,2) NOT NULL,
    `calories` INT(11) NOT NULL,
    `date` DATE NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `product_id` (`product_id`),
    KEY `date` (`date`),
    KEY `idx_user_date` (`user_id`, `date`),
    CONSTRAINT `fk_food_intake_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_food_intake_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Таблица сожженных калорий ----------
DROP TABLE IF EXISTS `calories_burned`;
CREATE TABLE IF NOT EXISTS `calories_burned` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `calories` INT(11) NOT NULL,
    `date` DATE NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_user_date` (`user_id`, `date`),
    KEY `date` (`date`),
    CONSTRAINT `fk_calories_burned_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- ТРИГГЕРЫ
-- ======================================================

-- ---------- Триггер: Автоматический расчет калорий при вставке ----------
DROP TRIGGER IF EXISTS `calculate_calories_before_insert`;
DELIMITER //
CREATE TRIGGER `calculate_calories_before_insert` 
BEFORE INSERT ON `food_intake` 
FOR EACH ROW 
BEGIN
    DECLARE product_calories INT;
    
    -- Получаем калорийность продукта на 100г
    SELECT calories_per_100g INTO product_calories 
    FROM products 
    WHERE id = NEW.product_id;
    
    -- Рассчитываем калории
    SET NEW.calories = ROUND((product_calories / 100) * NEW.grams);
END//
DELIMITER ;

-- ---------- Триггер: Автоматический расчет калорий при обновлении ----------
DROP TRIGGER IF EXISTS `calculate_calories_before_update`;
DELIMITER //
CREATE TRIGGER `calculate_calories_before_update` 
BEFORE UPDATE ON `food_intake` 
FOR EACH ROW 
BEGIN
    DECLARE product_calories INT;
    
    -- Если изменился продукт или граммы
    IF NEW.product_id != OLD.product_id OR NEW.grams != OLD.grams THEN
        -- Получаем калорийность продукта на 100г
        SELECT calories_per_100g INTO product_calories 
        FROM products 
        WHERE id = NEW.product_id;
        
        -- Пересчитываем калории
        SET NEW.calories = ROUND((product_calories / 100) * NEW.grams);
    END IF;
END//
DELIMITER ;

-- ---------- Триггер: Автоматическое обновление updated_at для food_intake ----------
DROP TRIGGER IF EXISTS `update_food_intake_timestamp`;
DELIMITER //
CREATE TRIGGER `update_food_intake_timestamp` 
BEFORE UPDATE ON `food_intake` 
FOR EACH ROW 
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END//
DELIMITER ;

-- ---------- Триггер: Автоматическое обновление updated_at для calories_burned ----------
DROP TRIGGER IF EXISTS `update_calories_burned_timestamp`;
DELIMITER //
CREATE TRIGGER `update_calories_burned_timestamp` 
BEFORE UPDATE ON `calories_burned` 
FOR EACH ROW 
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END//
DELIMITER ;

-- ======================================================
-- ИНДЕКСЫ
-- ======================================================
CREATE INDEX idx_food_intake_date ON food_intake (`date`);
CREATE INDEX idx_food_intake_user_date ON food_intake (`user_id`, `date`);
CREATE INDEX idx_calories_burned_date ON calories_burned (`date`);
CREATE INDEX idx_products_name ON products (`name`);

-- ======================================================
-- ПРОЦЕДУРЫ
-- ======================================================

-- ---------- Процедура: Получить дневную статистику ----------
DROP PROCEDURE IF EXISTS `GetDailyStats`;
DELIMITER //
CREATE PROCEDURE `GetDailyStats`(
    IN p_user_id INT,
    IN p_date DATE
)
BEGIN
    -- Еда за день
    SELECT 
        fi.id,
        p.name as product_name,
        fi.grams,
        fi.calories
    FROM food_intake fi
    JOIN products p ON fi.product_id = p.id
    WHERE fi.user_id = p_user_id AND fi.date = p_date;
    
    -- Общая сумма калорий за день
    SELECT 
        COALESCE(SUM(calories), 0) as total_eaten
    FROM food_intake
    WHERE user_id = p_user_id AND date = p_date;
    
    -- Сожженные калории за день
    SELECT 
        COALESCE(calories, 0) as burned
    FROM calories_burned
    WHERE user_id = p_user_id AND date = p_date;
END//
DELIMITER ;

-- ---------- Процедура: Получить статистику за период ----------
DROP PROCEDURE IF EXISTS `GetPeriodStats`;
DELIMITER //
CREATE PROCEDURE `GetPeriodStats`(
    IN p_user_id INT,
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    SELECT 
        dates.date,
        COALESCE(SUM(fi.calories), 0) as eaten,
        COALESCE(cb.calories, 0) as burned
    FROM (
        SELECT DATE_ADD(p_start_date, INTERVAL seq DAY) as date
        FROM (
            SELECT 
                @seq := @seq + 1 AS seq
            FROM 
                information_schema.columns,
                (SELECT @seq := -1) AS seq_init
            LIMIT DATEDIFF(p_end_date, p_start_date) + 1
        ) AS seq_table
    ) dates
    LEFT JOIN food_intake fi ON fi.date = dates.date AND fi.user_id = p_user_id
    LEFT JOIN calories_burned cb ON cb.date = dates.date AND cb.user_id = p_user_id
    GROUP BY dates.date
    ORDER BY dates.date;
END//
DELIMITER ;

-- ======================================================
-- ПРЕДСТАВЛЕНИЯ
-- ======================================================

-- ---------- Представление: Дневная статистика ----------
CREATE OR REPLACE VIEW `daily_summary` AS
SELECT 
    u.id as user_id,
    u.device_key,
    fi.date,
    COALESCE(SUM(fi.calories), 0) as eaten_calories,
    COALESCE(cb.calories, 0) as burned_calories,
    COALESCE(SUM(fi.calories), 0) - COALESCE(cb.calories, 0) as balance
FROM user u
LEFT JOIN food_intake fi ON u.id = fi.user_id
LEFT JOIN calories_burned cb ON u.id = cb.user_id AND fi.date = cb.date
GROUP BY u.id, u.device_key, fi.date, cb.calories;

-- ---------- Представление: Топ продуктов по калориям ----------
CREATE OR REPLACE VIEW `top_products` AS
SELECT 
    p.name,
    p.calories_per_100g,
    COUNT(fi.id) as times_eaten,
    SUM(fi.grams) as total_grams,
    SUM(fi.calories) as total_calories
FROM products p
LEFT JOIN food_intake fi ON p.id = fi.product_id
GROUP BY p.id, p.name, p.calories_per_100g
ORDER BY total_calories DESC;

-- ======================================================
-- ПРОВЕРКА
-- ======================================================
SELECT '=== ТАБЛИЦЫ ===' as '';
SHOW TABLES;

SELECT '=== ТРИГГЕРЫ ===' as '';
SHOW TRIGGERS;

SELECT '=== БАЗА СОЗДАНА УСПЕШНО ===' as '';

-- ======================================================
-- КОНЕЦ ФАЙЛА
-- ======================================================