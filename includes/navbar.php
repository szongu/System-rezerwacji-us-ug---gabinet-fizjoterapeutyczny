<?php
/** Górne menu nawigacyjne, dostosowane do roli zalogowanego użytkownika. */
$user = currentUser();
$role = currentRole();
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= e(basePath('index.php')) ?>">
            <i class="bi bi-heart-pulse"></i> <?= e(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $currentScript === 'index.php' ? 'active' : '' ?>" href="<?= e(basePath('index.php')) ?>">Strona główna</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentScript === 'services.php' ? 'active' : '' ?>" href="<?= e(basePath('services.php')) ?>">Oferta usług</a>
                </li>

                <?php if ($role === 'client'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentScript === 'reservation_new.php' ? 'active' : '' ?>" href="<?= e(basePath('reservation_new.php')) ?>">Zarezerwuj wizytę</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(basePath('client/dashboard.php')) ?>">Moje rezerwacje</a>
                    </li>
                <?php elseif ($role === 'employee'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(basePath('employee/dashboard.php')) ?>">Panel pracownika</a>
                    </li>
                <?php elseif ($role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(basePath('admin/dashboard.php')) ?>">Panel administratora</a>
                    </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav">
                <?php if ($user): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(basePath('profile.php')) ?>">
                            <i class="bi bi-person-circle"></i> <?= e($user['first_name']) ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(basePath('logout.php')) ?>">Wyloguj</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(basePath('login.php')) ?>">Zaloguj się</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(basePath('register.php')) ?>">Zarejestruj się</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
