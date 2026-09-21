-- ============================================================
-- System rezerwacji usług - Gabinet Fizjoterapii
-- Plik odtwarzający kompletną strukturę bazy danych + dane testowe
-- Silnik: MySQL / MariaDB, kodowanie: utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Baza danych
-- ------------------------------------------------------------
-- CREATE DATABASE IF NOT EXISTS gabinet_fizjoterapii CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
-- USE gabinet_fizjoterapii;

DROP TABLE IF EXISTS reservation_status_history;
DROP TABLE IF EXISTS admin_log;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS employee_time_off;
DROP TABLE IF EXISTS employee_availability;
DROP TABLE IF EXISTS employee_services;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS service_categories;
DROP TABLE IF EXISTS users;

-- ------------------------------------------------------------
-- 1. users - klienci, pracownicy i administratorzy
-- ------------------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name    VARCHAR(60)  NOT NULL,
    last_name     VARCHAR(60)  NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone         VARCHAR(20)  NOT NULL,
    role          ENUM('client', 'employee', 'admin') NOT NULL DEFAULT 'client',
    active        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_email UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ------------------------------------------------------------
-- 2. service_categories - kategorie usług (np. Terapia manualna)
-- ------------------------------------------------------------
CREATE TABLE service_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT NULL,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uq_category_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ------------------------------------------------------------
-- 3. services - konkretne usługi w ramach kategorii
-- ------------------------------------------------------------
CREATE TABLE services (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id      INT UNSIGNED NOT NULL,
    name             VARCHAR(150) NOT NULL,
    description      TEXT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    price            DECIMAL(8,2) NOT NULL,
    active           TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT fk_services_category
        FOREIGN KEY (category_id) REFERENCES service_categories(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_services_duration CHECK (duration_minutes > 0),
    CONSTRAINT chk_services_price CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ------------------------------------------------------------
-- 4. employees - dane pracownika (fizjoterapeuty), 1:1 z users
-- ------------------------------------------------------------
CREATE TABLE employees (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    description TEXT NULL,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uq_employees_user UNIQUE (user_id),
    CONSTRAINT fk_employees_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ------------------------------------------------------------
-- 5. employee_services - relacja N:M pracownik <-> usługa
-- ------------------------------------------------------------
CREATE TABLE employee_services (
    employee_id INT UNSIGNED NOT NULL,
    service_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (employee_id, service_id),
    CONSTRAINT fk_es_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_es_service
        FOREIGN KEY (service_id) REFERENCES services(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ------------------------------------------------------------
-- 6. employee_availability - cykliczne godziny pracy (wg dnia tygodnia)
--    day_of_week: 1=poniedziałek ... 7=niedziela (zgodnie z ISO-8601 / PHP date('N'))
-- ------------------------------------------------------------
CREATE TABLE employee_availability (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL,
    CONSTRAINT fk_availability_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_availability_day CHECK (day_of_week BETWEEN 1 AND 7),
    CONSTRAINT chk_availability_time CHECK (start_time < end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ------------------------------------------------------------
-- 7. employee_time_off - urlopy / dni wolne (funkcja dodatkowa)
-- ------------------------------------------------------------
CREATE TABLE employee_time_off (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    date_from   DATE NOT NULL,
    date_to     DATE NOT NULL,
    reason      VARCHAR(255) NULL,
    CONSTRAINT fk_timeoff_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_timeoff_dates CHECK (date_from <= date_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_
