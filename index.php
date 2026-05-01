<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/main_dashboard.php');
} else {
    header('Location: ' . APP_URL . '/auth/login.php');
}
exit;
