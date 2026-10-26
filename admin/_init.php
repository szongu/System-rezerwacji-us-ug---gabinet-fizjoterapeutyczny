<?php
/** Wspólny start każdej strony panelu administratora */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';
requireRole('admin');
