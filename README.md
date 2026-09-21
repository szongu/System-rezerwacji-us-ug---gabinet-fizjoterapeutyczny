# System rezerwacji usług - Gabinet Fizjoterapii

Projekt semestralny z przedmiotu **Tworzenie stron i aplikacji internetowych**
(klasa 5 technikum informatycznego, wrzesień-listopad 2026).

Aplikacja internetowa umożliwiająca klientom rezerwowanie wizyt w gabinecie
fizjoterapii: wybór usługi, specjalisty i wolnego terminu, z pełną obsługą ról
klienta, pracownika i administratora.

## Autorzy

- Imię i nazwisko 1 - [Szymon Marciniak]
- Imię i nazwisko 2 - [Sebastian Zalewski]

## Opis systemu

System pozwala klientom przeglądać ofertę usług gabinetu (terapia manualna,
rehabilitacja ortopedyczna, masaż leczniczy, fizykoterapia), zakładać konto,
logować się i rezerwować wizyty według procesu:

```
usługa -> pracownik -> data -> godzina -> potwierdzenie
```

System pilnuje, aby nie doszło do nałożenia się dwóch wizyt u tego samego
pracownika (kontrola po stronie serwera, niezależna od interfejsu), uwzględnia
godziny pracy poszczególnych fizjoterapeutów oraz ich urlopy/dni wolne.

Trzy role użytkowników:

- **Klient** - zakłada konto, rezerwuje wizyty, przegląda i anuluje własne rezerwacje.
- **Pracownik** (fizjoterapeuta) - widzi swoje wizyty (dziś / przyszłe / historia) i zmienia ich status.
- **Administrator** - zarządza kategoriami, usługami, pracownikami (w tym ich dostępnością), użytkownikami i wszystkimi rezerwacjami; ma dostęp do dashboardu ze statystykami.

## Zastosowane technologie

- PHP 8 (czysty PHP, bez frameworka) + PDO (MySQL/MariaDB, prepared statements)
- HTML5, CSS3
- JavaScript (fetch/AJAX do dynamicznego pobierania wolnych terminów)
- Bootstrap 5 + Bootstrap Icons (CDN) - warstwa wizualna
- MySQL / MariaDB
- Git i GitHub

Frameworki nie zostały użyte celowo, żeby cały kod był w 100% zrozumiały i
możliwy do samodzielnego wyjaśnienia przez obu autorów.

## Struktura repozytorium

```
/database/database.sql      - pełny schemat bazy + dane testowe
/database/erd.md             - opis relacji (ERD w formie tekstowej)
/includes/                   - rdzeń aplikacji (baza, sesje, funkcje, layout)
/assets/css, /assets/js      - style i skrypty własne
/api/                        - punkty końcowe AJAX (dostępne terminy)
/admin/                      - panel administratora
/employee/                   - panel pracownika
/client/                     - panel klienta
index.php, login.php, register.php, services.php, service.php,
reservation_new.php, profile.php - strony ogólnodostępne / klienta
COMMIT_PLAN.md                - proponowany podział pracy na commity wg etapów
```

## Instrukcja uruchomienia (XAMPP / lokalny serwer PHP + MySQL)

1. Sklonuj repozytorium do katalogu serwera (np. `htdocs` w XAMPP).
2. Utwórz bazę danych, np. `gabinet_fizjoterapii` (kodowanie `utf8mb4`).
3. Zaimportuj strukturę i dane testowe:
   ```
   mysql -u root -p gabinet_fizjoterapii < database/database.sql
   ```
   (albo wgraj plik `database/database.sql` przez phpMyAdmin: zakładka *Import*)
4. Skopiuj `includes/config.example.php` jako `includes/config.php` i uzupełnij
   dane dostępowe do swojej bazy (domyślnie: `root` bez hasła, zgodnie z XAMPP).
5. Ustaw w `includes/config.php` wartość `APP_URL` na adres, pod którym
   uruchamiasz projekt, np. `http://localhost/gabinet-fizjoterapii`.
6. Uruchom serwer (Apache przez XAMPP, albo dla szybkiego testu):
   ```
   php -S localhost:8000
   ```
7. Otwórz aplikację w przeglądarce pod adresem z kroku 5/6.

## Konta testowe

Hasło dla wszystkich kont testowych: **Test1234!**

| Rola          | E-mail                              |
|---------------|--------------------------------------|
| Administrator | admin@gabinet.pl                     |
| Pracownik     | piotr.nowak@gabinet.pl               |
| Pracownik     | katarzyna.wisniewska@gabinet.pl      |
| Pracownik     | michal.zielinski@gabinet.pl          |
| Klient        | jan.kowalczyk@example.com            |
| Klient        | maria.lewandowska@example.com        |

Nowego klienta można też założyć przez formularz rejestracji.

## Funkcje dodatkowe (ponad wymagania obowiązkowe)

- AJAX i dynamiczne pobieranie wolnych terminów (`api/available_slots.php`) podczas rezerwacji, bez przeładowania strony.
- Różne godziny pracy poszczególnych pracowników oraz obsługa urlopów/dni wolnych (`employee_availability`, `employee_time_off`).
- Historia zmian statusów rezerwacji (`reservation_status_history`) - kto i kiedy zmienił status wizyty.
- Log działań administratora (`admin_log`, zakładka *Log działań* w panelu administratora).
- Wyszukiwanie i paginacja w panelach administratora (rezerwacje, użytkownicy, log działań).
- Podstawowa ochrona przed nadużyciami logowania (limit prób logowania w obrębie sesji).
- Ochrona CSRF na wszystkich formularzach POST (token w sesji, weryfikowany przy każdym zapisie).

## Bezpieczeństwo

- Hasła: `password_hash()` / `password_verify()` (bcrypt), nigdy jawny tekst.
- Wszystkie zapytania SQL przez `PDO` z parametrami wiązanymi (`prepared statements`) - zero konkatenacji danych użytkownika w SQL.
- Dane wyświetlane w HTML zawsze przechodzą przez `htmlspecialchars()` (funkcja `e()`).
- Kontrola sesji i ról po stronie serwera dla każdej chronionej strony (`requireLogin()`, `requireRole()`) - ukrycie linku w menu to nie jest zabezpieczenie, każda strona sama sprawdza uprawnienia.
- Kontrola własności danych: klient może zobaczyć/anulować wyłącznie własne rezerwacje, pracownik może zmieniać status wyłącznie własnych wizyt (sprawdzane po stronie serwera, nie tylko w interfejsie).
- Konflikt terminów sprawdzany ponownie tuż przed zapisem do bazy, wewnątrz transakcji - nie da się go ominąć modyfikując dane formularza.
- `includes/config.php` (dane dostępowe do bazy) jest w `.gitignore` i nigdy nie trafia do repozytorium.

## Testy

Logika wykrywania konfliktów terminów (`includes/booking.php`) została
zweryfikowana automatycznym skryptem testowym odtwarzającym dokładnie
przykład z dokumentacji projektu (rezerwacja 10:00-11:00 blokuje
9:30-10:30, 10:00-11:00, 10:15-10:45, 10:30-11:30 i 9:30-11:30, a dopuszcza
terminy stykające się dokładnie o 10:00/11:00) oraz przypadki urlopu i
rezerwacji w przeszłości - wszystkie przypadki przechodzą pomyślnie.
