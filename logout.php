<?php
// Fixed: use the new includes folder
require_once __DIR__ . '/includes/auth.php';

start_session();
check_csrf(); // optional – protects logout from CSRF
logout();

// Redirect to login page (relative path)
header('Location: login.php');
exit;