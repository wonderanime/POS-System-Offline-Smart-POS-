<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("UPDATE products SET active=0 WHERE id IN ($placeholders)")->execute($ids);
        audit('PRODUCTS_BULK_DELETED', 'Bulk deleted '.count($ids).' product(s)');
    }
}
header('Location: /products'); exit;
