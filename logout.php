<?php
/**
 * Logout Handler
 */
require_once __DIR__ . '/config/auth.php';

logout_user();
header("Location: login.php?logged_out=1");
exit;
