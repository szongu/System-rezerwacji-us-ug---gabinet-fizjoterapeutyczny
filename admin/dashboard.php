<?php
require_once __DIR__ . '/_init.php';

$pdo = getDb();

$stats = [
    'clients'    => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn(),
    'employees'  => (int) $pdo->query("SELECT COUNT(*) FROM employees WHERE active = 1")->fetchColumn(),
    'services'   => (int) $pdo->query("SELECT COUNT(*) FROM services WHERE active = 1")->fetchColumn(),
    'reservations_total' => (int) $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn(),
];

$todayStmt = $pdo->prepare(
    "SELECT r.*, s.name AS service_name, u.first_name AS client_first, u.last_name AS client_last,
            eu.first_name AS emp_first, eu.last_name AS emp_last
     FROM reservations r
     JOIN services s ON s.id = r.service_id
     JOIN users u ON u.id = r.user_id
     JOIN employees e ON e.id = r.employee_id
     JOIN users eu ON eu.id = e.user_id
     WHERE r.reservation_date = :today AND r.status != 'anulowana'
     ORDER BY r.start_time"
);
$todayStmt->execute(['today' => date('Y-m-d')]);
$todayReservations = $todayStmt->fetchAll();

$pageTitle = 'Dashboard administratora';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<h1 class="h3 mb-4">Dashboard</h1>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="fs-3 fw-bold text-primary"><?= $stats['clients'] ?></div>
            <div class="text-muted small">Klienci</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="fs-3 fw-bold text-primary"><?= $stats['employees'] ?></div>
            <div class="text-muted small">Aktywni pracownicy</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="fs-3 fw-bold text-primary"><?= $stats['services'] ?></div>
            <div class="text-muted small">Aktywne usługi</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card text-center h-100"><div class="card-body">
            <div class="fs-3 fw-bold text-primary"><?= $stats['reservations_total'] ?></div>
            <div class="text-muted small">Wszystkie rezerwacje</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card text-center h-100 border-primary"><div class="card-body">
            <div class="fs-3 fw-bold text-primary"><?= count($todayReservations) ?></div>
            <div class="text-muted small">Rezerwacje na dziś</div>
        </div></div>
    </div>
</div>

<h2 class="h5 mb-3">Wizyty na dziś (<?= e(formatDate(date('Y-m-d'))) ?>)</h2>
<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>Godzina</th><th>Klient</th><th>Usługa</th><th>Pracownik</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($todayReservations as $r): ?>
                <tr>
                    <td><?= e(formatTime($r['start_time'])) ?>-<?= e(formatTime($r['end_time'])) ?></td>
                    <td><?= e($r['client_first'] . ' ' . $r['client_last']) ?></td>
                    <td><?= e($r['service_name']) ?></td>
                    <td><?= e($r['emp_first'] . ' ' . $r['emp_last']) ?></td>
                    <td><span class="badge <?= statusBadgeClass($r['status']) ?>"><?= statusLabel($r['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($todayReservations)): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">Brak wizyt zaplanowanych na dziś.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_nav_end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
