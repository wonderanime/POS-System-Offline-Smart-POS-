<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db(); $sym = get_setting('currency_symbol','Rs');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $db->prepare("SELECT s.*,c.name cust_name FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.id=?");
$stmt->execute([$id]); $sale = $stmt->fetch();
if (!$sale) { header('Location: /sales'); exit; }
if ($sale['status']==='voided') { header('Location: /sale-view?id='.$id); exit; }

$items = $db->prepare("SELECT * FROM sale_items WHERE sale_id=?"); $items->execute([$id]); $items = $items->fetchAll();

$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $reason = trim($_POST['reason'] ?? '');
    if (!$reason) { $error='Please enter a reason for voiding this sale.'; }
    else {
        $db->beginTransaction();
        $db->prepare("UPDATE sales SET status='voided', void_reason=?, voided_at=datetime('now','localtime') WHERE id=?")
           ->execute([$reason, $id]);

        $su = $db->prepare("UPDATE products SET stock_qty=stock_qty+? WHERE id=?");
        foreach($items as $it) $su->execute([$it['qty'], $it['product_id']]);

        if ($sale['customer_id'] && $sale['paid_amount'] < $sale['total']) {
            $owed = $sale['total'] - $sale['paid_amount'];
            $db->prepare("UPDATE customers SET due_balance=MAX(0,due_balance-?) WHERE id=?")->execute([$owed, $sale['customer_id']]);
        }

        audit('SALE_VOIDED', "Voided invoice {$sale['invoice_no']} ($sym ".money($sale['total']).") — Reason: $reason. Stock restored for ".count($items)." item(s).");
        $db->commit();
        header('Location: /sale-view?id='.$id); exit;
    }
}

$activePage='sales'; $pageTitle='Void Sale';
include __DIR__.'/layout_top.php';
?>
<div class="alert alert-danger">
  ⚠️ You are about to <strong>void</strong> invoice <strong><?=htmlspecialchars($sale['invoice_no'])?></strong> (<?=$sym?> <?=money($sale['total'])?>). This will:
  <ul style="margin:8px 0 0 20px;font-size:13px">
    <li>Remove this sale from all profit and revenue reports</li>
    <li>Restore stock for all <?=count($items)?> item(s)</li>
    <?php if($sale['customer_id'] && $sale['paid_amount']<$sale['total']): ?><li>Reduce <?=htmlspecialchars($sale['cust_name'])?>'s due balance</li><?php endif; ?>
    <li>Write a permanent entry in the audit log</li>
  </ul>
</div>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>

<div class="panel" style="max-width:640px;margin-bottom:18px">
  <div class="panel-head"><span class="panel-title">Sale Summary</span></div>
  <div class="panel-body">
    <table class="dt">
      <thead><tr><th>Product</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Total</th></tr></thead>
      <tbody><?php foreach($items as $it): ?>
      <tr><td><?=htmlspecialchars($it['product_name'])?></td><td class="num"><?=$it['qty']?></td><td class="num"><?=$sym?> <?=money($it['price'])?></td><td class="num"><?=$sym?> <?=money($it['total'])?></td></tr>
      <?php endforeach; ?></tbody>
    </table>
    <div style="text-align:right;margin-top:10px;font-size:15px;font-weight:800">Total: <?=$sym?> <?=money($sale['total'])?></div>
  </div>
</div>

<form class="form-card" method="post" style="max-width:640px">
  <div class="form-group full">
    <label class="form-label">Reason for voiding *</label>
    <textarea class="form-input" name="reason" rows="3" required placeholder="e.g. Wrong items entered, customer cancelled, duplicate entry..."></textarea>
  </div>
  <div class="form-actions">
    <button class="btn btn-danger" type="submit" data-confirm="Confirm void — this is permanent.">Void This Sale</button>
    <a class="btn" href="/sale-view?id=<?=$id?>">Cancel</a>
  </div>
</form>
<?php include __DIR__.'/layout_bottom.php'; ?>
