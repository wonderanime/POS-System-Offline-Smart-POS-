<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

init_db();
maybe_auto_backup();
