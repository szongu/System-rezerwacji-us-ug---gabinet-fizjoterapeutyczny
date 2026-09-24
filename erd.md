# Diagram ERD - System rezerwacji usług (Gabinet Fizjoterapii)

users (id PK)
  ├─< employees (user_id FK, 1:1 z users)
  │     ├─< employee_services >─┐              (N:M pracownik <-> usługa)
  │     ├─< employee_availability            (1:N, godziny pracy wg dnia tygodnia)
  │     └─< employee_time_off                (1:N, urlopy / dni wolne)
  └─< reservations (user_id FK)              (1:N, klient ma wiele rezerwacji)

service_categories (id PK)
  └─< services (category_id FK)              (1:N, kategoria ma wiele usług)
        └─< employee_services >─┘            (N:M, patrz wyżej)
        └─< reservations (service_id FK)     (1:N)

employees (id PK) ──< reservations (employee_id FK)   (1:N)

reservations (id PK)
  └─< reservation_status_history (reservation_id FK)  (1:N, log zmian statusu)

users (id PK) ──< admin_log (admin_id FK)              (1:N, log działań administratora)
users (id PK) ──< reservation_status_history (changed_by FK)
```

## Kardynalności

| users -> employees | 1:1 (pracownik jest użytkownikiem z rolą employee) |
| users -> reservations | 1:N (klient) |
| service_categories -> services | 1:N |
| employees <-> services | N:M (przez employee_services) |
| employees -> employee_availability | 1:N |
| employees -> employee_time_off | 1:N |
| employees -> reservations | 1:N |
| services -> reservations | 1:N |
| reservations -> reservation_status_history | 1:N |
| users -> admin_log | 1:N |

## Klucze obce i integralność

- `employees.user_id` -> `users.id` (UNIQUE, 1:1, ON DELETE CASCADE)
- `services.category_id` -> `service_categories.id` (ON DELETE RESTRICT - nie można
  usunąć kategorii, dopóki są w niej usługi; kategorię się dezaktywuje, nie usuwa)
- `employee_services.employee_id/service_id` -> klucz złożony (PK), ON DELETE CASCADE
- `employee_availability.employee_id` -> `employees.id`, ON DELETE CASCADE
- `reservations.user_id/employee_id/service_id` -> ON DELETE RESTRICT (dane
  rezerwacji muszą zostać zachowane nawet jeśli konto/usługę dezaktywowano -
  dlatego w aplikacji stosujemy dezaktywację `active = 0` zamiast fizycznego
  usuwania rekordów)
- CHECK `start_time < end_time` na `employee_availability` i `reservations`
- CHECK `duration_minutes > 0`, `price >= 0` na `services`
- UNIQUE `users.email`, UNIQUE `service_categories.name`, UNIQUE `employees.user_id`
