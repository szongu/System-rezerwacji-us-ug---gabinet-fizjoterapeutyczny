<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/booking.php';

// Rezerwację może utworzyć tylko zalogowany klient.
requireRole('client');

$pdo = getDb();
$userId = currentUser()['id'];
$errors = [];

// --- Obsługa ostatecznego potwierdzenia (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    requireValidCsrf();

    $serviceId  = (int) ($_POST['service_id'] ?? 0);
    $employeeId = (int) ($_POST['employee_id'] ?? 0);
    $date       = $_POST['date'] ?? '';
    $startTime  = $_POST['start_time'] ?? '';
    $endTime    = $_POST['end_time'] ?? '';
    $comment    = trim($_POST['comment'] ?? '');

    // Krok 1: podstawowa walidacja formatu (dane MOGŁY zostać zmienione ręcznie przez klienta).
    if ($serviceId <= 0 || $employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        || !preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        $errors[] = 'Nieprawidłowe dane rezerwacji. Rozpocznij proces od nowa.';
    }

    if (empty($errors)) {
        $startTime .= ':00';
        $endTime .= ':00';

        // Krok 2: usługa aktywna i pracownik rzeczywiście ją wykonuje (ochrona przed zmianą parametrów w żądaniu).
        $svcStmt = $pdo->prepare('SELECT * FROM services WHERE id = :id AND active = 1');
        $svcStmt->execute(['id' => $serviceId]);
        $service = $svcStmt->fetch();

        $empStmt = $pdo->prepare(
            'SELECT e.* FROM employees e
             JOIN employee_services es ON es.employee_id = e.id
             WHERE e.id = :eid AND es.service_id = :sid AND e.active = 1'
        );
        $empStmt->execute(['eid' => $employeeId, 'sid' => $serviceId]);
        $employee = $empStmt->fetch();

        if (!$service || !$employee) {
            $errors[] = 'Wybrana usługa lub pracownik są niedostępni. Rozpocznij proces od nowa.';
        } elseif ($date < date('Y-m-d') || ($date === date('Y-m-d') && $startTime <= date('H:i:s'))) {
            $errors[] = 'Nie można zarezerwować terminu w przeszłości.';
        } elseif (substr($endTime, 0, 5) !== date('H:i', strtotime($date . ' ' . $startTime) + $service['duration_minutes'] * 60)) {
            // Czas zakończenia musi wynikać dokładnie z czasu trwania usługi - klient nie może go podmienić.
            $errors[] = 'Nieprawidłowy zakres godzinowy dla wybranej usługi.';
        } elseif (!isWithinWorkingHours($pdo, $employeeId, $date, $startTime, $endTime)) {
            $errors[] = 'Wybrany termin wykracza poza godziny pracy pracownika.';
        }
    }

    if (empty($errors)) {
        // Krok 3: ostateczna, autorytatywna kontrola konfliktu TUŻ przed zapisem (w transakcji).
        $pdo->beginTransaction();
        try {
            if (hasBookingConflict($pdo, $employeeId, $date, $startTime, $endTime)) {
                $pdo->rollBack();
                $errors[] = 'Niestety ten termin został już zajęty przez kogoś innego. Wybierz inną godzinę.';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO reservations (user_id, employee_id, service_id, reservation_date, start_time, end_time, status, comment)
                     VALUES (:user_id, :employee_id, :service_id, :date, :start, :end, "oczekujaca", :comment)'
                );
                $insert->execute([
                    'user_id' => $userId, 'employee_id' => $employeeId, 'service_id' => $serviceId,
                    'date' => $date, 'start' => $startTime, 'end' => $endTime,
                    'comment' => $comment !== '' ? $comment : null,
                ]);
                $reservationId = (int) $pdo->lastInsertId();
                $pdo->commit();

                logStatusChange($reservationId, null, 'oczekujaca', $userId);
                setFlash('success', 'Rezerwacja została utworzona i oczekuje na potwierdzenie.');
                redirect(basePath('client/dashboard.php'));
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Blad zapisu rezerwacji: ' . $e->getMessage());
            $errors[] = 'Wystąpił błąd podczas zapisywania rezerwacji. Spróbuj ponownie.';
        }
    }
}

// --- Odczyt parametrów kroków z GET (proces: usługa -> pracownik -> data -> godzina -> potwierdzenie) ---
$serviceId  = (int) ($_GET['service_id'] ?? 0);
$employeeId = (int) ($_GET['employee_id'] ?? 0);
$date       = $_GET['date'] ?? '';
$startTime  = $_GET['start_time'] ?? '';
$endTime    = $_GET['end_time'] ?? '';

$services = $pdo->query(
    "SELECT s.*, c.name AS category_name FROM services s
     JOIN service_categories c ON c.id = s.category_id
     WHERE s.active = 1 AND c.active = 1 ORDER BY c.name, s.name"
)->fetchAll();

$selectedService = null;
foreach ($services as $s) {
    if ((int) $s['id'] === $serviceId) {
        $selectedService = $s;
        break;
    }
}

$employees = [];
$selectedEmployee = null;
if ($selectedService) {
    $employees = getEmployeesForService($pdo, $serviceId);
    foreach ($employees as $emp) {
        if ((int) $emp['id'] === $employeeId) {
            $selectedEmployee = $emp;
            break;
        }
    }
}

$validDateTime = $selectedEmployee && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    && preg_match('/^\d{2}:\d{2}$/', $startTime) && preg_match('/^\d{2}:\d{2}$/', $endTime);

// Ustal aktualny krok wizarda.
if (!$selectedService) {
    $step = 1;
} elseif (!$selectedEmployee) {
    $step = 2;
} elseif (!$validDateTime) {
    $step = 3;
} else {
    $step = 4;
}

$pageTitle = 'Nowa rezerwacja';
require __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-4">Zarezerwuj wizytę</h1>

<div class="step-indicator">
    <span class="step <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">1. Usługa</span>
    <span class="step <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">2. Pracownik</span>
    <span class="step <?= $step === 3 ? 'active' : ($step > 3 ? 'done' : '') ?>">3. Data i godzina</span>
    <span class="step <?= $step === 4 ? 'active' : '' ?>">4. Potwierdzenie</span>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<?php if ($step === 1): ?>
    <div class="row g-3">
        <?php foreach ($services as $s): ?>
            <div class="col-md-6 col-lg-4">
                <a class="card card-service text-decoration-none text-dark" href="<?= e(basePath('reservation_new.php?service_id=' . $s['id'])) ?>">
                    <div class="card-body">
                        <span class="badge bg-primary-subtle text-primary-emphasis mb-2"><?= e($s['category_name']) ?></span>
                        <h2 class="h6"><?= e($s['name']) ?></h2>
                        <p class="small text-muted mb-1"><?= (int) $s['duration_minutes'] ?> min - <?= formatPrice((float) $s['price']) ?></p>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($step === 2): ?>
    <p><a href="<?= e(basePath('reservation_new.php')) ?>">&laquo; Zmień usługę</a></p>
    <h2 class="h5 mb-3">Usługa: <?= e($selectedService['name']) ?></h2>
    <?php if (empty($employees)): ?>
        <p class="text-muted">Brak dostępnych pracowników dla tej usługi. Wybierz inną usługę.</p>
    <?php endif; ?>
    <div class="row g-3">
        <?php foreach ($employees as $emp): ?>
            <div class="col-md-6 col-lg-4">
                <a class="card card-service text-decoration-none text-dark"
                   href="<?= e(basePath('reservation_new.php?service_id=' . $serviceId . '&employee_id=' . $emp['id'])) ?>">
                    <div class="card-body">
                        <h2 class="h6"><i class="bi bi-person-badge text-primary"></i> <?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></h2>
                        <p class="small text-muted mb-0"><?= e($emp['description']) ?></p>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($step === 3): ?>
    <p>
        <a href="<?= e(basePath('reservation_new.php?service_id=' . $serviceId)) ?>">&laquo; Zmień pracownika</a>
    </p>
    <h2 class="h5 mb-3">
        <?= e($selectedService['name']) ?> - <?= e($selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name']) ?>
    </h2>

    <div class="mb-3" style="max-width: 260px;">
        <label class="form-label">Wybierz datę</label>
        <input type="date" id="datePicker" class="form-control" min="<?= date('Y-m-d') ?>"
               value="<?= e($date !== '' ? $date : date('Y-m-d')) ?>">
    </div>

    <div id="slotsContainer">
        <p class="text-muted">Ładowanie dostępnych godzin...</p>
    </div>

    <script>
        (function () {
            const serviceId = <?= (int) $serviceId ?>;
            const employeeId = <?= (int) $employeeId ?>;
            const baseUrl = <?= json_encode(basePath('reservation_new.php')) ?>;
            const apiUrl = <?= json_encode(basePath('api/available_slots.php')) ?>;
            const datePicker = document.getElementById('datePicker');
            const container = document.getElementById('slotsContainer');

            function loadSlots(date) {
                container.innerHTML = '<p class="text-muted">Ładowanie dostępnych godzin...</p>';
                fetch(apiUrl + '?employee_id=' + employeeId + '&service_id=' + serviceId + '&date=' + date)
                    .then(r => r.json())
                    .then(data => {
                        if (data.error) {
                            container.innerHTML = '<p class="text-danger">' + data.error + '</p>';
                            return;
                        }
                        if (!data.slots || data.slots.length === 0) {
                            container.innerHTML = '<p class="text-muted">Brak wolnych terminów tego dnia. Wybierz inną datę.</p>';
                            return;
                        }
                        const wrap = document.createElement('div');
                        wrap.className = 'd-flex flex-wrap gap-2';
                        data.slots.forEach(function (slot) {
                            const a = document.createElement('a');
                            a.className = 'btn btn-outline-primary slot-btn';
                            a.textContent = slot.start;
                            a.href = baseUrl + '?service_id=' + serviceId + '&employee_id=' + employeeId
                                + '&date=' + date + '&start_time=' + slot.start + '&end_time=' + slot.end;
                            wrap.appendChild(a);
                        });
                        container.innerHTML = '';
                        container.appendChild(wrap);
                    })
                    .catch(() => {
                        container.innerHTML = '<p class="text-danger">Nie udało się pobrać dostępnych terminów.</p>';
                    });
            }

            datePicker.addEventListener('change', function () {
                loadSlots(this.value);
            });

            loadSlots(datePicker.value);
        })();
    </script>

<?php elseif ($step === 4): ?>
    <p>
        <a href="<?= e(basePath('reservation_new.php?service_id=' . $serviceId . '&employee_id=' . $employeeId)) ?>">&laquo; Zmień termin</a>
    </p>
    <div class="card" style="max-width: 560px;">
        <div class="card-header">Potwierdź rezerwację</div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-5">Usługa</dt><dd class="col-7"><?= e($selectedService['name']) ?></dd>
                <dt class="col-5">Pracownik</dt><dd class="col-7"><?= e($selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name']) ?></dd>
                <dt class="col-5">Data</dt><dd class="col-7"><?= e(formatDate($date)) ?></dd>
                <dt class="col-5">Godzina</dt><dd class="col-7"><?= e($startTime) ?> - <?= e($endTime) ?></dd>
                <dt class="col-5">Cena</dt><dd class="col-7"><?= formatPrice((float) $selectedService['price']) ?></dd>
            </dl>
            <form method="post" class="mt-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="service_id" value="<?= (int) $serviceId ?>">
                <input type="hidden" name="employee_id" value="<?= (int) $employeeId ?>">
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <input type="hidden" name="start_time" value="<?= e($startTime) ?>">
                <input type="hidden" name="end_time" value="<?= e($endTime) ?>">
                <div class="mb-3">
                    <label class="form-label">Komentarz dla specjalisty (opcjonalnie)</label>
                    <textarea name="comment" class="form-control" rows="3" maxlength="500"></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100">Potwierdź rezerwację</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
