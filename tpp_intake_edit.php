<?php
/** @deprecated Use supplier_purchase_edit.php */
require_once __DIR__ . '/config/config.php';
$q = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ' . APP_URL . '/supplier_purchase_edit.php' . $q, true, 301);
exit;
