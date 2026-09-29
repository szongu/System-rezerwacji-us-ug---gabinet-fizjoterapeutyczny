<?php
require_once __DIR__ . '/includes/bootstrap.php';

$categories = getDb()->query(
    'SELECT id, name, description FROM service_categories WHERE active = 1 ORDER BY name LIMIT 4'
)->fetchAll();

$pageTitle = 'Strona główna';
require __DIR__ . '/includes/header.php';
?>

<div class="hero mb-5">
    <h1 class="display-6 fw-bold mb-3">Zadbaj o swoje zdrowie z Gabinetem Fizjoterapii</h1>
    <p class="lead">Umów wizytę online w kilka kliknięć - wybierz usługę, dogodny termin i specjalistę,
        który się Tobą zajmie. Rejestracja dostępna 24 godziny na dobę.</p>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <a href="<?= e(basePath('services.php')) ?>" class="btn btn-light btn-lg">Zobacz ofertę</a>
        <?php if (currentRole() === 'client' || !isLoggedIn()): ?>
            <a href="<?= e(basePath('reservation_new.php')) ?>" class="btn btn-outline-light btn-lg">Zarezerwuj wizytę</a>
        <?php endif; ?>
    </div>
</div>

<h2 class="h4 mb-3">Nasze kategorie usług</h2>
<div class="row g-3 mb-4">
    <?php foreach ($categories as $cat): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-service">
                <div class="card-body">
                    <h3 class="h6 card-title"><i class="bi bi-clipboard2-pulse text-primary"></i> <?= e($cat['name']) ?></h3>
                    <p class="card-text small text-muted"><?= e($cat['description']) ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($categories)): ?>
        <p class="text-muted">Oferta jest aktualnie przygotowywana.</p>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="p-3 border rounded h-100">
            <i class="bi bi-1-circle-fill text-primary fs-4"></i>
            <h3 class="h6 mt-2">Wybierz usługę</h3>
            <p class="small text-muted mb-0">Przejrzyj naszą ofertę i wybierz zabieg dopasowany do Twoich potrzeb.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="p-3 border rounded h-100">
            <i class="bi bi-2-circle-fill text-primary fs-4"></i>
            <h3 class="h6 mt-2">Wybierz termin</h3>
            <p class="small text-muted mb-0">System pokaże tylko realnie dostępne godziny wybranego specjalisty.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="p-3 border rounded h-100">
            <i class="bi bi-3-circle-fill text-primary fs-4"></i>
            <h3 class="h6 mt-2">Potwierdź rezerwację</h3>
            <p class="small text-muted mb-0">Otrzymasz potwierdzenie, a wizytę zobaczysz w panelu swojego konta.</p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
