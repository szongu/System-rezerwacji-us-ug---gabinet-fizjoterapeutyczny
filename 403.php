<?php
/**
 * Strona błędu 403 (brak uprawnień). Może być dołączana przez requireRole() */
if (!function_exists('currentUser')) {
    require_once __DIR__ . '/includes/bootstrap.php';
}
$pageTitle = 'Brak dostępu';
require __DIR__ . '/includes/header.php';
?>
<div class="text-center py-5">
    <i class="bi bi-shield-lock display-1 text-danger"></i>
    <h1 class="mt-3">403 - Brak dostępu</h1>
    <p class="text-muted">Nie masz uprawnień, aby wyświetlić tę stronę.</p>
    <a href="<?= e(basePath('index.php')) ?>" class="btn btn-primary mt-2">Wróć na stronę główną</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
