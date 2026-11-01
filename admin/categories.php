<?php
require_once __DIR__ . '/_init.php';
$pdo = getDb();
$adminId = currentUser()['id'];

$errors = [];
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$formValues = ['name' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Podaj nazwę kategorii (maks. 100 znaków).';
        }

        if (empty($errors)) {
            $dupCheck = $pdo->prepare('SELECT COUNT(*) FROM service_categories WHERE name = :name AND id != :id');
            $dupCheck->execute(['name' => $name, 'id' => $id ?? 0]);
            if ((int) $dupCheck->fetchColumn() > 0) {
                $errors[] = 'Kategoria o tej nazwie już istnieje.';
            }
        }

        if (empty($errors)) {
            if ($id) {
                $stmt = $pdo->prepare('UPDATE service_categories SET name = :name, description = :description WHERE id = :id');
                $stmt->execute(['name' => $name, 'description' => $description, 'id' => $id]);
                logAdminAction($adminId, "Edytowano kategorię #$id ($name)");
                setFlash('success', 'Kategoria została zaktualizowana.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO service_categories (name, description, active) VALUES (:name, :description, 1)');
                $stmt->execute(['name' => $name, 'description' => $description]);
                logAdminAction($adminId, 'Dodano kategorię: ' . $name);
                setFlash('success', 'Kategoria została dodana.');
            }
            redirect(basePath('admin/categories.php'));
        }

        $formValues = ['name' => $name, 'description' => $description];
        $editId = $id;
    }

    if ($action === 'toggle') {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare('UPDATE service_categories SET active = NOT active WHERE id = :id');
        $stmt->execute(['id' => $id]);
        logAdminAction($adminId, "Zmieniono status aktywności kategorii #$id");
        setFlash('success', 'Status kategorii został zmieniony.');
        redirect(basePath('admin/categories.php'));
    }

    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        try {
            $stmt = $pdo->prepare('DELETE FROM service_categories WHERE id = :id');
            $stmt->execute(['id' => $id]);
            logAdminAction($adminId, "Usunięto kategorię #$id");
            setFlash('success', 'Kategoria została usunięta.');
        } catch (PDOException $e) {
            setFlash('danger', 'Nie można usunąć kategorii, ponieważ są w niej usługi. Możesz ją dezaktywować.');
        }
        redirect(basePath('admin/categories.php'));
    }
}

if ($editId && empty($errors)) {
    $stmt = $pdo->prepare('SELECT * FROM service_categories WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $row = $stmt->fetch();
    if ($row) {
        $formValues = ['name' => $row['name'], 'description' => $row['description']];
    } else {
        $editId = null;
    }
}

$categories = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id) AS services_count
     FROM service_categories c ORDER BY c.name'
)->fetchAll();

$pageTitle = 'Kategorie usług';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<h1 class="h3 mb-4">Kategorie usług</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header"><?= $editId ? 'Edytuj kategorię' : 'Dodaj nową kategorię' ?></div>
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <?php if ($editId): ?><input type="hidden" name="id" value="<?= (int) $editId ?>"><?php endif; ?>
            <div class="col-md-4">
                <label class="form-label">Nazwa</label>
                <input type="text" name="name" class="form-control" required maxlength="100"
                       value="<?= e($formValues['name']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Opis</label>
                <input type="text" name="description" class="form-control" maxlength="255"
                       value="<?= e($formValues['description']) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><?= $editId ? 'Zapisz' : 'Dodaj' ?></button>
                <?php if ($editId): ?>
                    <a href="<?= e(basePath('admin/categories.php')) ?>" class="btn btn-outline-secondary">Anuluj</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr><th>Nazwa</th><th>Opis</th><th>Liczba usług</th><th>Status</th><th class="text-end">Akcje</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
            <tr>
                <td><?= e($cat['name']) ?></td>
                <td class="text-muted small"><?= e($cat['description']) ?></td>
                <td><?= (int) $cat['services_count'] ?></td>
                <td>
                    <span class="badge <?= $cat['active'] ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $cat['active'] ? 'Aktywna' : 'Nieaktywna' ?>
                    </span>
                </td>
                <td class="text-end">
                    <a href="<?= e(basePath('admin/categories.php?edit=' . $cat['id'])) ?>" class="btn btn-sm btn-outline-primary">Edytuj</a>
                    <form method="post" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <?= $cat['active'] ? 'Dezaktywuj' : 'Aktywuj' ?>
                        </button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Na pewno usunąć tę kategorię?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" <?= $cat['services_count'] > 0 ? 'disabled title="Kategoria zawiera usługi"' : '' ?>>Usuń</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($categories)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">Brak kategorii.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_nav_end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
