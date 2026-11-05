<?php
require_once __DIR__ . '/_init.php';
$pdo = getDb();
$adminId = currentUser()['id'];

$employeeId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT e.*, u.first_name, u.last_name, u.email, u.phone
                        FROM employees e JOIN users u ON u.id = e.user_id WHERE e.id = :id');
$stmt->execute(['id' => $employeeId]);
$employee = $stmt->fetch();

if (!$employee) {
    setFlash('danger', 'Nie znaleziono pracownika.');
    redirect(basePath('admin/employees.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $active    = isset($_POST['active']) ? 1 : 0;

        if ($firstName === '' || $lastName === '') {
            $errors[] = 'Podaj imię i nazwisko.';
        }
        if (!isValidPhone($phone)) {
            $errors[] = 'Podaj poprawny numer telefonu.';
        }

        if (empty($errors)) {
            $pdo->prepare('UPDATE users SET first_name = :fn, last_name = :ln, phone = :phone WHERE id = :uid')
                ->execute(['fn' => $firstName, 'ln' => $lastName, 'phone' => $phone, 'uid' => $employee['user_id']]);
            $pdo->prepare('UPDATE employees SET description = :desc, active = :active WHERE id = :id')
                ->execute(['desc' => $description, 'active' => $active, 'id' => $employeeId]);
            logAdminAction($adminId, "Zaktualizowano dane pracownika #$employeeId");
            setFlash('success', 'Dane pracownika zostały zapisane.');
            redirect(basePath('admin/employee_edit.php?id=' . $employeeId));
        }
    }

    if ($action === 'update_services') {
        $selected = array_map('intval', $_POST['service_ids'] ?? []);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM employee_services WHERE employee_id = :id')->execute(['id' => $employeeId]);
            $insert = $pdo->prepare('INSERT INTO employee_services (employee_id, service_id) VALUES (:eid, :sid)');
            foreach ($selected as $sid) {
                $insert->execute(['eid' => $employeeId, 'sid' => $sid]);
            }
            $pdo->commit();
            logAdminAction($adminId, "Zaktualizowano listę usług pracownika #$employeeId");
            setFlash('success', 'Przypisane usługi zostały zaktualizowane.');
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', 'Nie udało się zapisać przypisanych usług.');
        }
        redirect(basePath('admin/employee_edit.php?id=' . $employeeId));
    }

    if ($action === 'add_availability') {
        $day = (int) ($_POST['day_of_week'] ?? 0);
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';

        if ($day < 1 || $day > 7) {
            $errors[] = 'Wybierz dzień tygodnia.';
        } elseif (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
            $errors[] = 'Podaj poprawny zakres godzin (godzina początku musi być wcześniejsza niż końca).';
        } else {
            $pdo->prepare('INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time) VALUES (:eid, :day, :start, :end)')
                ->execute(['eid' => $employeeId, 'day' => $day, 'start' => $start, 'end' => $end]);
            logAdminAction($adminId, "Dodano dostępność pracownika #$employeeId (" . dayName($day) . " $start-$end)");
            setFlash('success', 'Dodano godziny pracy.');
            redirect(basePath('admin/employee_edit.php?id=' . $employeeId));
        }
    }

    if ($action === 'delete_availability') {
        $id = (int) ($_POST['availability_id'] ?? 0);
        $pdo->prepare('DELETE FROM employee_availability WHERE id = :id AND employee_id = :eid')
            ->execute(['id' => $id, 'eid' => $employeeId]);
        setFlash('success', 'Usunięto zakres godzin pracy.');
        redirect(basePath('admin/employee_edit.php?id=' . $employeeId));
    }

    if ($action === 'add_timeoff') {
        $from = $_POST['date_from'] ?? '';
        $to = $_POST['date_to'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        if (!strtotime($from) || !strtotime($to) || $from > $to) {
            $errors[] = 'Podaj poprawny zakres dat urlopu.';
        } else {
            $pdo->prepare('INSERT INTO employee_time_off (employee_id, date_from, date_to, reason) VALUES (:eid, :from, :to, :reason)')
                ->execute(['eid' => $employeeId, 'from' => $from, 'to' => $to, 'reason' => $reason]);
            logAdminAction($adminId, "Dodano urlop pracownika #$employeeId ($from - $to)");
            setFlash('success', 'Dodano urlop / dzień wolny.');
            redirect(basePath('admin/employee_edit.php?id=' . $employeeId));
        }
    }

    if ($action === 'delete_timeoff') {
        $id = (int) ($_POST['timeoff_id'] ?? 0);
        $pdo->prepare('DELETE FROM employee_time_off WHERE id = :id AND employee_id = :eid')
            ->execute(['id' => $id, 'eid' => $employeeId]);
        setFlash('success', 'Usunięto wpis urlopowy.');
        redirect(basePath('admin/employee_edit.php?id=' . $employeeId));
    }
}

// Odśwież dane pracownika po ewentualnych zmianach.
$stmt->execute(['id' => $employeeId]);
$employee = $stmt->fetch();

$allServices = $pdo->query('SELECT id, name FROM services WHERE active = 1 ORDER BY name')->fetchAll();

$assignedStmt = $pdo->prepare('SELECT service_id FROM employee_services WHERE employee_id = :id');
$assignedStmt->execute(['id' => $employeeId]);
$assignedIds = array_map('intval', array_column($assignedStmt->fetchAll(), 'service_id'));

$availabilityStmt = $pdo->prepare('SELECT * FROM employee_availability WHERE employee_id = :id ORDER BY day_of_week, start_time');
$availabilityStmt->execute(['id' => $employeeId]);
$availability = $availabilityStmt->fetchAll();

$timeOffStmt = $pdo->prepare('SELECT * FROM employee_time_off WHERE employee_id = :id ORDER BY date_from DESC');
$timeOffStmt->execute(['id' => $employeeId]);
$timeOff = $timeOffStmt->fetchAll();

$pageTitle = 'Zarządzanie pracownikiem';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<a href="<?= e(basePath('admin/employees.php')) ?>" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Wróć do listy</a>
<h1 class="h3 mb-4"><?= e($employee['first_name'] . ' ' . $employee['last_name']) ?></h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Dane podstawowe</div>
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_info">
            <div class="col-md-3">
                <label class="form-label">Imię</label>
                <input type="text" name="first_name" class="form-control" required value="<?= e($employee['first_name']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Nazwisko</label>
                <input type="text" name="last_name" class="form-control" required value="<?= e($employee['last_name']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">E-mail</label>
                <input type="text" class="form-control" value="<?= e($employee['email']) ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label">Telefon</label>
                <input type="text" name="phone" class="form-control" required value="<?= e($employee['phone']) ?>">
            </div>
            <div class="col-md-9">
                <label class="form-label">Opis / specjalizacja</label>
                <input type="text" name="description" class="form-control" value="<?= e($employee['description']) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-center">
                <div class="form-check mt-4">
                    <input type="checkbox" name="active" class="form-check-input" id="activeCheck" <?= $employee['active'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activeCheck">Aktywny</label>
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Zapisz</button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">Przypisane usługi</div>
    <div class="card-body">
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_services">
            <div class="row">
                <?php foreach ($allServices as $svc): ?>
                    <div class="col-md-4 form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="service_ids[]" value="<?= (int) $svc['id'] ?>"
                               id="svc<?= (int) $svc['id'] ?>" <?= in_array((int) $svc['id'], $assignedIds, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="svc<?= (int) $svc['id'] ?>"><?= e($svc['name']) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary mt-2">Zapisz przypisane usługi</button>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Godziny pracy</div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead><tr><th>Dzień</th><th>Od</th><th>Do</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($availability as $a): ?>
                        <tr>
                            <td><?= e(dayName((int) $a['day_of_week'])) ?></td>
                            <td><?= e(formatTime($a['start_time'])) ?></td>
                            <td><?= e(formatTime($a['end_time'])) ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Usunąć ten zakres godzin?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_availability">
                                    <input type="hidden" name="availability_id" value="<?= (int) $a['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">&times;</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($availability)): ?>
                        <tr><td colspan="4" class="text-muted text-center">Brak zdefiniowanych godzin pracy.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <form method="post" class="row g-2 align-items-end">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_availability">
                    <div class="col-4">
                        <label class="form-label small">Dzień</label>
                        <select name="day_of_week" class="form-select form-select-sm">
                            <?php for ($d = 1; $d <= 7; $d++): ?>
                                <option value="<?= $d ?>"><?= e(dayName($d)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label small">Od</label>
                        <input type="time" name="start_time" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-3">
                        <label class="form-label small">Do</label>
                        <input type="time" name="end_time" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Dodaj</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Urlopy i dni wolne</div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead><tr><th>Od</th><th>Do</th><th>Powód</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($timeOff as $t): ?>
                        <tr>
                            <td><?= e(formatDate($t['date_from'])) ?></td>
                            <td><?= e(formatDate($t['date_to'])) ?></td>
                            <td class="small text-muted"><?= e($t['reason']) ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Usunąć ten wpis?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_timeoff">
                                    <input type="hidden" name="timeoff_id" value="<?= (int) $t['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">&times;</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($timeOff)): ?>
                        <tr><td colspan="4" class="text-muted text-center">Brak zaplanowanych urlopów.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <form method="post" class="row g-2 align-items-end">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_timeoff">
                    <div class="col-4">
                        <label class="form-label small">Od</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Do</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Powód</label>
                        <input type="text" name="reason" class="form-control form-control-sm" maxlength="255">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-primary">Dodaj</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_nav_end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
