<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
$user = require_admin(); $db = get_db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)($_POST['id'] ?? 0);
    $p = $db->prepare("SELECT * FROM products WHERE id=?"); $p->execute([$id]); $prod=$p->fetch();
    if ($prod) {
        // Soft delete: keep the row (sale_items reference it) but hide it and zero its stock
        $db->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$id]);
        audit('PRODUCT_DELETED','product',$id,"Deleted product: {$prod['name']}",['active'=>1],['active'=>0],$user['id']);
    }
}
header('Location: /inventory'); exit;
