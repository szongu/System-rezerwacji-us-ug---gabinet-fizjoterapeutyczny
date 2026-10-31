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
