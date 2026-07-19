<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
$user = require_login();

$d       = json_in();
$items   = $d['items'] ?? [];
$discAmt = (float)($d['discount'] ?? 0);
$paidAmt = (float)($d['paid_amount'] ?? 0);
$custId  = $d['customer_id'] ?? null;
$method  = $d['payment_method'] ?? 'cash';

if (empty($items)) json_out(['ok'=>false,'msg'=>'Cart is empty'], 400);

$db  = get_db();
$sym = get_setting('currency_symbol','Rs');

$validItems = []; $subtotal = 0.0; $totalTax = 0.0;
foreach ($items as $it) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id=? AND active=1");
    $stmt->execute([$it['product_id']]);
    $p = $stmt->fetch();
    if (!$p) continue;
    $qty = max(0.01, (float)$it['qty']);
    $line = round($p['sale_price'] * $qty, 2);
    $taxAmt = round($line * ($p['tax_rate']/100), 2);
    $subtotal += $line; $totalTax += $taxAmt;
    $validItems[] = ['product_id'=>$p['id'],'name'=>$p['name'],'qty'=>$qty,'price'=>$p['sale_price'],'cost_price'=>$p['purchase_price'],'total'=>$line];
}
if (empty($validItems)) json_out(['ok'=>false,'msg'=>'No valid products'], 400);

$total = round(max(0, $subtotal - $discAmt + $totalTax), 2);
$changeDue = max(0, round($paidAmt - $total, 2));

$invoiceNo = next_invoice();

$db->beginTransaction();
try {
    $db->prepare("INSERT INTO sales (invoice_no,customer_id,subtotal,discount,tax,total,paid_amount,change_due,payment_method) VALUES (?,?,?,?,?,?,?,?,?)")
       ->execute([$invoiceNo,$custId?:null,$subtotal,$discAmt,$totalTax,$total,$paidAmt,$changeDue,$method]);
    $saleId = (int)$db->lastInsertId();

    $si = $db->prepare("INSERT INTO sale_items (sale_id,product_id,product_name,qty,price,cost_price,total) VALUES (?,?,?,?,?,?,?)");
    $su = $db->prepare("UPDATE products SET stock_qty=stock_qty-? WHERE id=?");
    foreach ($validItems as $vi) {
        $si->execute([$saleId,$vi['product_id'],$vi['name'],$vi['qty'],$vi['price'],$vi['cost_price'],$vi['total']]);
        $su->execute([$vi['qty'],$vi['product_id']]);
    }

    if ($custId && $paidAmt < $total) {
        $db->prepare("UPDATE customers SET due_balance=due_balance+? WHERE id=?")->execute([$total-$paidAmt,$custId]);
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    json_out(['ok'=>false,'msg'=>'Checkout failed: '.$e->getMessage()], 500);
}

$custName = null;
if ($custId) {
    $cr = $db->prepare("SELECT name FROM customers WHERE id=?"); $cr->execute([$custId]);
    $custName = $cr->fetch()['name'] ?? null;
}

json_out([
    'ok'=>true, 'invoice_no'=>$invoiceNo, 'subtotal'=>$subtotal, 'discount'=>$discAmt, 'tax'=>$totalTax,
    'total'=>$total, 'paid_amount'=>$paidAmt, 'change_due'=>$changeDue, 'payment_method'=>$method,
    'customer'=>$custName, 'items'=>array_map(fn($vi)=>['name'=>$vi['name'],'qty'=>$vi['qty'],'total'=>$vi['total']],$validItems),
]);
