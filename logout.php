<?php
require_once __DIR__ . '/app/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
session_destroy();
header('Location: ' . BASE . '/login');
exit;
