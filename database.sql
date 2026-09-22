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

-- ------------------------------------------------------------
-- 8. reservations - rezerwacje klientów
-- ------------------------------------------------------------
CREATE TABLE reservations (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED NOT NULL,
    employee_id       INT UNSIGNED NOT NULL,
    service_id        INT UNSIGNED NOT NULL,
    reservation_date  DATE NOT NULL,
    start_time        TIME NOT NULL,
    end_time          TIME NOT NULL,
    status            ENUM('oczekujaca', 'potwierdzona', 'zrealizowana', 'anulowana')
                      NOT NULL DEFAULT 'oczekujaca',
    comment           VARCHAR(500) NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservations_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reservations_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reservations_service
        FOREIGN KEY (service_id) REFERENCES services(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_reservations_time CHECK (start_time < end_time),
    INDEX idx_reservations_employee_date (employee_id, reservation_date),
    INDEX idx_reservations_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ------------------------------------------------------------
-- 9. reservation_status_history - historia zmian statusów (funkcja dodatkowa)
-- ------------------------------------------------------------
CREATE TABLE reservation_status_history (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    old_status     ENUM('oczekujaca', 'potwierdzona', 'zrealizowana', 'anulowana') NULL,
    new_status     ENUM('oczekujaca', 'potwierdzona', 'zrealizowana', 'anulowana') NOT NULL,
    changed_by     INT UNSIGNED NULL,
    changed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_history_reservation
        FOREIGN KEY (reservation_id) REFERENCES reservations(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_history_user
        FOREIGN KEY (changed_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ------------------------------------------------------------
-- 10. admin_log - log operacji administratora (funkcja dodatkowa)
-- ------------------------------------------------------------
CREATE TABLE admin_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT UNSIGNED NULL,
    action      VARCHAR(255) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_adminlog_user
        FOREIGN KEY (admin_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DANE TESTOWE
-- Hasła testowe (jawne, wyłącznie do celów dema) dla wszystkich kont: Test1234!
-- Hash poniżej wygenerowany funkcją password_hash() z algorytmem PASSWORD_DEFAULT (bcrypt)
-- ============================================================

INSERT INTO users (first_name, last_name, email, password_hash, phone, role, active) VALUES
('Anna', 'Kowalska', 'admin@gabinet.pl', '$2y$12$xm5J4GQjpNQUwOzJJo0a0eAZ2nZeuC31CGcfzFV0viDan.Yf2HEVe', '600100100', 'admin', 1),
('Piotr', 'Nowak', 'piotr.nowak@gabinet.pl', '$2y$12$xm5J4GQjpNQUwOzJJo0a0eAZ2nZeuC31CGcfzFV0viDan.Yf2HEVe', '600200200', 'employee', 1),
('Katarzyna', 'Wiśniewska', 'katarzyna.wisniewska@gabinet.pl', '$2y$12$xm5J4GQjpNQUwOzJJo0a0eAZ2nZeuC31CGcfzFV0viDan.Yf2HEVe', '600200300', 'employee', 1),
('Michał', 'Zieliński', 'michal.zielinski@gabinet.pl', '$2y$12$xm5J4GQjpNQUwOzJJo0a0eAZ2nZeuC31CGcfzFV0viDan.Yf2HEVe', '600200400', 'employee', 1),
('Jan', 'Kowalczyk', 'jan.kowalczyk@example.com', '$2y$12$xm5J4GQjpNQUwOzJJo0a0eAZ2nZeuC31CGcfzFV0viDan.Yf2HEVe', '600300100', 'client', 1),
('Maria', 'Lewandowska', 'maria.lewandowska@example.com', '$2y$12$xm5J4GQjpNQUwOzJJo0a0eAZ2nZeuC31CGcfzFV0viDan.Yf2HEVe', '600300200', 'client', 1);

-- id: 1=admin, 2=Piotr(employee), 3=Katarzyna(employee), 4=Michał(employee), 5=Jan(client), 6=Maria(client)

INSERT INTO service_categories (name, description, active) VALUES
('Terapia manualna', 'Techniki manualne redukujące ból i poprawiające zakres ruchu.', 1),
('Rehabilitacja ortopedyczna', 'Usprawnianie po urazach i zabiegach ortopedycznych.', 1),
('Masaż leczniczy', 'Masaże wspomagające regenerację tkanek i mięśni.', 1),
('Fizykoterapia', 'Zabiegi z wykorzystaniem prądów, ultradźwięków i pola magnetycznego.', 1);

INSERT INTO services (category_id, name, description, duration_minutes, price, active) VALUES
(1, 'Terapia manualna kręgosłupa', 'Mobilizacja i manipulacja odcinków kręgosłupa.', 45, 150.00, 1),
(1, 'Terapia punktów spustowych', 'Praca nad przewlekłymi punktami bólowymi mięśni.', 30, 110.00, 1),
(2, 'Rehabilitacja po kontuzji kolana', 'Indywidualny program usprawniania stawu kolanowego.', 60, 180.00, 1),
(2, 'Rehabilitacja po operacji barku', 'Ćwiczenia i terapia manualna po zabiegu.', 60, 180.00, 1),
(3, 'Masaż leczniczy pleców', 'Masaż odcinka szyjnego, piersiowego i lędźwiowego.', 45, 130.00, 1),
(3, 'Masaż relaksacyjny całego ciała', 'Masaż redukujący napięcie mięśniowe.', 60, 160.00, 1),
(4, 'Elektroterapia', 'Zabieg z wykorzystaniem prądów o niskiej i średniej częstotliwości.', 20, 60.00, 1),
(4, 'Ultradźwięki', 'Zabieg fizykalny wspomagający regenerację tkanek.', 20, 60.00, 1);

INSERT INTO employees (user_id, description, active) VALUES
(2, 'Fizjoterapeuta specjalizujący się w terapii manualnej i rehabilitacji ortopedycznej.', 1),
(3, 'Fizjoterapeutka specjalizująca się w masażu leczniczym i fizykoterapii.', 1),
(4, 'Fizjoterapeuta specjalizujący się w rehabilitacji pourazowej.', 1);

-- id: 1=Piotr, 2=Katarzyna, 3=Michał

INSERT INTO employee_services (employee_id, service_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 4),
(2, 5), (2, 6), (2, 7), (2, 8),
(3, 3), (3, 4), (3, 7);

-- Godziny pracy: pon-pt 8:00-16:00 dla Piotra i Michała, wt-sob 10:00-18:00 dla Katarzyny
INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time) VALUES
(1, 1, '08:00:00', '16:00:00'),
(1, 2, '08:00:00', '16:00:00'),
(1, 3, '08:00:00', '16:00:00'),
(1, 4, '08:00:00', '16:00:00'),
(1, 5, '08:00:00', '16:00:00'),
(2, 2, '10:00:00', '18:00:00'),
(2, 3, '10:00:00', '18:00:00'),
(2, 4, '10:00:00', '18:00:00'),
(2, 5, '10:00:00', '18:00:00'),
(2, 6, '10:00:00', '14:00:00'),
(3, 1, '08:00:00', '16:00:00'),
(3, 2, '08:00:00', '16:00:00'),
(3, 3, '08:00:00', '16:00:00'),
(3, 4, '08:00:00', '16:00:00'),
(3, 5, '08:00:00', '16:00:00');

INSERT INTO employee_time_off (employee_id, date_from, date_to, reason) VALUES
(1, '2026-12-23', '2026-12-31', 'Urlop wypoczynkowy');

-- Przykładowe rezerwacje testowe (daty w przyszłości względem uruchomienia projektu)
INSERT INTO reservations (user_id, employee_id, service_id, reservation_date, start_time, end_time, status, comment) VALUES
(5, 1, 1, '2026-10-05', '09:00:00', '09:45:00', 'potwierdzona', 'Ból w odcinku lędźwiowym.'),
(6, 2, 5, '2026-10-06', '11:00:00', '11:45:00', 'oczekujaca', NULL),
(5, 1, 3, '2026-09-20', '10:00:00', '11:00:00', 'zrealizowana', 'Kontrola po kontuzji kolana.'),
(6, 3, 4, '2026-09-18', '08:00:00', '09:00:00', 'anulowana', 'Klient przełożył wizytę.');

INSERT INTO reservation_status_history (reservation_id, old_status, new_status, changed_by) VALUES
(1, NULL, 'oczekujaca', 5),
(1, 'oczekujaca', 'potwierdzona', 2),
(3, NULL, 'oczekujaca', 5),
(3, 'oczekujaca', 'potwierdzona', 1),
(3, 'potwierdzona', 'zrealizowana', 1),
(4, NULL, 'oczekujaca', 6),
(4, 'oczekujaca', 'anulowana', 6);
