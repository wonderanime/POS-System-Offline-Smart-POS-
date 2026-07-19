<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
$user = require_admin(); $db = get_db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $names = $db->prepare("SELECT name FROM products WHERE id IN ($placeholders)");
        $names->execute($ids);
        $nameList = implode(', ', $names->fetchAll(PDO::FETCH_COLUMN));

        $db->prepare("UPDATE products SET active=0 WHERE id IN ($placeholders)")->execute($ids);
        audit('PRODUCT_BULK_DELETED','product',0,"Bulk deleted ".count($ids)." products: $nameList",null,null,$user['id']);
    }
}
header('Location: /inventory'); exit;
