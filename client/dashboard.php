<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';
requireRole('client');

$pdo = getDb();
$userId = currentUser()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    requireValidCsrf();
    $reservationId = (int) ($_POST['id'] ?? 0);

    // Kontrola własności danych: klient może anulować WYŁĄCZNIE swoją rezerwację.
    $stmt = $pdo->prepare('SELECT * FROM reservations WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $reservationId, 'user_id' => $userId]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        setFlash('danger', 'Nie znaleziono rezerwacji.');
    } elseif ($reservation['status'] === 'anulowana' || $reservation['status'] === 'zrealizowana') {
        setFlash('danger', 'Tej rezerwacji nie można już anulować.');
    } elseif ($reservation['reservation_date'] < date('Y-m-d')
        || ($reservation['reservation_date'] === date('Y-m-d') && $reservation['start_time'] <= date('H:i:s'))) {
        setFlash('danger', 'Nie można anulować rezerwacji, która już się odbyła lub trwa.');
    } else {
        $pdo->prepare('UPDATE reservations SET status = "anulowana" WHERE id = :id')->execute(['id' => $reservationId]);
        logStatusChange($reservationId, $reservation['status'], 'anulowana', $userId);
        setFlash('success', 'Rezerwacja została anulowana.');
    }
    redirect(basePath('client/dashboard.php'));
}

$stmt = $pdo->prepare(
    "SELECT r.*, s.name AS service_name, s.price, eu.first_name AS emp_first, eu.last_name AS emp_last
     FROM reservations r
     JOIN services s ON s.id = r.service_id
     JOIN employees e ON e.id = r.employee_id
     JOIN users eu ON eu.id = e.user_id
     WHERE r.user_id = :user_id
     ORDER BY r.reservation_date DESC, r.start_time DESC"
);
$stmt->execute(['user_id' => $userId]);
$all = $stmt->fetchAll();

$now = date('Y-m-d H:i:s');
$upcoming = [];
$past = [];
foreach ($all as $r) {
    $reservationDateTime = $r['reservation_date'] . ' ' . $r['start_time'];
    if (in_array($r['status'], ['anulowana', 'zrealizowana'], true) || $reservationDateTime < $now) {
        $past[] = $r;
    } else {
        $upcoming[] = $r;
    }
}

function renderReservationRow(array $r, bool $cancellable): void
{
    ?>
    <tr>
        <td><?= e(formatDate($r['reservation_date'])) ?></td>
        <td><?= e(formatTime($r['start_time'])) ?>-<?= e(formatTime($r['end_time'])) ?></td>
        <td><?= e($r['service_name']) ?></td>
        <td><?= e($r['emp_first'] . ' ' . $r['emp_last']) ?></td>
        <td><?= formatPrice((float) $r['price']) ?></td>
        <td><span class="badge <?= statusBadgeClass($r['status']) ?>"><?= statusLabel($r['status']) ?></span></td>
        <td class="text-end">
            <?php if ($cancellable): ?>
                <form method="post" onsubmit="return confirm('Na pewno anulować tę rezerwację?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Anuluj</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}

$pageTitle = 'Moje rezerwacje';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-4">Moje rezerwacje</h1>
<p><a href="<?= e(basePath('reservation_new.php')) ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nowa rezerwacja</a></p>

<h2 class="h5 mt-4">Nadchodzące</h2>
<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr><th>Data</th><th>Godzina</th><th>Usługa</th><th>Pracownik</th><th>Cena</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($upcoming as $r): renderReservationRow($r, $r['status'] !== 'anulowana'); endforeach; ?>
        <?php if (empty($upcoming)): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">Brak nadchodzących rezerwacji.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<h2 class="h5 mt-4">Zakończone i anulowane</h2>
<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr><th>Data</th><th>Godzina</th><th>Usługa</th><th>Pracownik</th><th>Cena</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($past as $r): renderReservationRow($r, false); endforeach; ?>
        <?php if (empty($past)): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">Brak historii rezerwacji.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
