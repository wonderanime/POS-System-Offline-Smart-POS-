<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$id]);
}
header('Location: /products'); exit;
