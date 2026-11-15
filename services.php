<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = getDb();
$categories = $pdo->query('SELECT id, name FROM service_categories WHERE active = 1 ORDER BY name')->fetchAll();

$categoryFilter = (int) ($_GET['category_id'] ?? 0);

$sql = "SELECT s.*, c.name AS category_name FROM services s
        JOIN service_categories c ON c.id = s.category_id
        WHERE s.active = 1 AND c.active = 1";
$params = [];
if ($categoryFilter > 0) {
    $sql .= ' AND s.category_id = :category_id';
    $params['category_id'] = $categoryFilter;
}
$sql .= ' ORDER BY c.name, s.name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

$pageTitle = 'Oferta usług';
require __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-4">Oferta usług</h1>

<div class="mb-4">
    <div class="btn-group flex-wrap" role="group">
        <a href="<?= e(basePath('services.php')) ?>" class="btn btn-sm <?= $categoryFilter === 0 ? 'btn-primary' : 'btn-outline-primary' ?>">Wszystkie</a>
        <?php foreach ($categories as $cat): ?>
            <a href="<?= e(basePath('services.php?category_id=' . $cat['id'])) ?>"
               class="btn btn-sm <?= $categoryFilter === (int) $cat['id'] ? 'btn-primary' : 'btn-outline-primary' ?>">
                <?= e($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($services as $s): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card card-service">
                <div class="card-body d-flex flex-column">
                    <span class="badge bg-primary-subtle text-primary-emphasis mb-2 align-self-start"><?= e($s['category_name']) ?></span>
                    <h2 class="h5 card-title"><?= e($s['name']) ?></h2>
                    <p class="card-text text-muted small flex-grow-1"><?= e($s['description']) ?></p>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-semibold"><?= formatPrice((float) $s['price']) ?></span>
                        <span class="text-muted small"><i class="bi bi-clock"></i> <?= (int) $s['duration_minutes'] ?> min</span>
                    </div>
                    <a href="<?= e(basePath('service.php?id=' . $s['id'])) ?>" class="btn btn-outline-primary mt-auto">Szczegóły</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($services)): ?>
        <p class="text-muted">Brak usług w tej kategorii.</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
