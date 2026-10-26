<?php
/** Menu boczne panelu administratora*/
$adminCurrent = basename($_SERVER['SCRIPT_NAME']);
$adminLinks = [
    'dashboard.php'    => ['icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
    'categories.php'   => ['icon' => 'bi-tags', 'label' => 'Kategorie'],
    'services.php'      => ['icon' => 'bi-clipboard2-pulse', 'label' => 'Usługi'],
    'employees.php'     => ['icon' => 'bi-person-badge', 'label' => 'Pracownicy'],
    'users.php'          => ['icon' => 'bi-people', 'label' => 'Użytkownicy'],
    'reservations.php'   => ['icon' => 'bi-calendar-check', 'label' => 'Rezerwacje'],
    'admin_log.php'      => ['icon' => 'bi-clock-history', 'label' => 'Log działań'],
];
?>
<div class="row g-4">
    <div class="col-lg-3">
        <div class="list-group shadow-sm">
            <?php foreach ($adminLinks as $file => $meta): ?>
                <a href="<?= e(basePath('admin/' . $file)) ?>"
                   class="list-group-item list-group-item-action d-flex align-items-center gap-2 <?= $adminCurrent === $file ? 'active' : '' ?>">
                    <i class="bi <?= e($meta['icon']) ?>"></i> <?= e($meta['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-lg-9">
