<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
header('Location: ' . (current_user() ? '/dashboard' : '/login'));
exit;
