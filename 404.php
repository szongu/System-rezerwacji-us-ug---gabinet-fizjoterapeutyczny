<?php
require_once __DIR__ . '/includes/bootstrap.php';
http_response_code(404);
$pageTitle = 'Nie znaleziono strony';
require __DIR__ . '/includes/header.php';
?>
<div class="text-center py-5">
    <i class="bi bi-signpost-2 display-1 text-secondary"></i>
    <h1 class="mt-3">404 - Nie znaleziono strony</h1>
    <p class="text-muted">Strona, której szukasz, nie istnieje.</p>
    <a href="<?= e(basePath('index.php')) ?>" class="btn btn-primary mt-2">Wróć na stronę główną</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
