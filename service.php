<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/booking.php';

$pdo = getDb();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT s.*, c.name AS category_name FROM services s
     JOIN service_categories c ON c.id = s.category_id
     WHERE s.id = :id AND s.active = 1 AND c.active = 1"
);
$stmt->execute(['id' => $id]);
$service = $stmt->fetch();

if (!$service) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$employees = getEmployeesForService($pdo, $id);

$pageTitle = $service['name'];
require __DIR__ . '/includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(basePath('services.php')) ?>">Oferta usług</a></li>
        <li class="breadcrumb-item active"><?= e($service['name']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <div class="col-lg-8">
        <span class="badge bg-primary-subtle text-primary-emphasis mb-2"><?= e($service['category_name']) ?></span>
        <h1 class="h3"><?= e($service['name']) ?></h1>
        <p><?= nl2br(e($service['description'])) ?></p>
        <ul class="list-unstyled">
            <li><i class="bi bi-clock text-primary"></i> Czas trwania: <?= (int) $service['duration_minutes'] ?> min</li>
            <li><i class="bi bi-cash-coin text-primary"></i> Cena: <?= formatPrice((float) $service['price']) ?></li>
        </ul>

        <h2 class="h5 mt-4">Specjaliści wykonujący tę usługę</h2>
        <?php if (empty($employees)): ?>
            <p class="text-muted">Obecnie brak przypisanych specjalistów.</p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($employees as $emp): ?>
                    <li class="list-group-item">
                        <strong><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></strong>
                        <div class="small text-muted"><?= e($emp['description']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <p class="fs-4 fw-bold mb-1"><?= formatPrice((float) $service['price']) ?></p>
                <p class="text-muted mb-3"><?= (int) $service['duration_minutes'] ?> minut</p>
                <a href="<?= e(basePath('reservation_new.php?service_id=' . $service['id'])) ?>" class="btn btn-primary w-100">Zarezerwuj wizytę</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
