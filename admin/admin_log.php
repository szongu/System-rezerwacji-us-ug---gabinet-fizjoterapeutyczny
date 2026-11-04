<?php
require_once __DIR__ . '/_init.php';
$pdo = getDb();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$total = (int) $pdo->query('SELECT COUNT(*) FROM admin_log')->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT al.*, u.first_name, u.last_name FROM admin_log al
     LEFT JOIN users u ON u.id = al.admin_id
     ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute();
$logs = $stmt->fetchAll();

$pageTitle = 'Log działań administratora';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<h1 class="h3 mb-4">Log działań administratora</h1>
<p class="text-muted small">Funkcja dodatkowa: rejestr operacji wykonanych z poziomu panelu administratora, ułatwiający audyt zmian.</p>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr><th>Data</th><th>Administrator</th><th>Akcja</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td class="small text-nowrap"><?= e(formatDateTime($log['created_at'])) ?></td>
                <td><?= e(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: 'usunięty użytkownik') ?></td>
                <td><?= e($log['action']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
            <tr><td colspan="3" class="text-center text-muted py-3">Brak zarejestrowanych działań.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <nav>
        <ul class="pagination pagination-sm">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a></li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/_nav_end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
