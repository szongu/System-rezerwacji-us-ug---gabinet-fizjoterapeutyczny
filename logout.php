<?php
require_once __DIR__ . '/includes/bootstrap.php';
logoutUser();
redirect(basePath('index.php'));
