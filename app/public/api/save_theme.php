<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login();
$d = json_in();
set_setting('theme', $d['theme'] ?? 'dark');
json_out(['ok' => true]);
