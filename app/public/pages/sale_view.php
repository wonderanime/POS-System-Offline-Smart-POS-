<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db(); $sym = get_setting('currency_symbol','Rs');

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT s.*,c.name cust_name FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.id=?");
$stmt->execute([$id]); $sale = $stmt->fetch();
if (!$sale) { header('Location: /sales'); exit; }

$items = $db->prepare("SELECT * FROM sale_items WHERE sale_id=?"); $items->execute([$id]); $items = $items->fetchAll();

$activePage='sales'; $pageTitle='Invoice '.$sale['invoice_no'];
include __DIR__.'/layout_top.php';
?>
<?php if($sale['status']==='voided'): ?>
<div class="alert alert-danger">This sale was voided on <?=htmlspecialchars($sale['voided_at'])?>. Reason: <?=htmlspecialchars($sale['void_reason']??'N/A')?></div>
<?php endif; ?>
<div style="display:flex;gap:8px;margin-bottom:16px" class="no-print">
  <button class="btn btn-primary" onclick="window.print()">🖨 Print Receipt</button>
  <?php if($sale['status']!=='voided'): ?>
  <a class="btn btn-danger" href="/sale-void?id=<?=$id?>">Void Sale</a>
  <?php endif; ?>
  <a class="btn" href="/sales">← Back</a>
</div>
<div id="receipt-root" style="max-width:440px"></div>
<script src="/assets/js/receipt.js"></script>
<script>
document.getElementById('receipt-root').innerHTML = renderReceiptHTML({
    invoice_no: <?=json_encode($sale['invoice_no'])?>,
    date: <?=json_encode($sale['created_at'])?>,
    customer: <?=json_encode($sale['cust_name'])?>,
    shop_name: <?=json_encode(get_setting('shop_name','My Shop'))?>,
    shop_address: <?=json_encode(get_setting('shop_address',''))?>,
    shop_phone: <?=json_encode(get_setting('shop_phone',''))?>,
    logo_url: <?=json_encode(get_setting('shop_logo',''))?>,
    items: <?=json_encode(array_map(fn($it)=>['name'=>$it['product_name'],'qty'=>rtrim(rtrim(number_format($it['qty'],2),'0'),'.'),'total'=>(float)$it['total']],$items))?>,
    subtotal: <?=(float)$sale['subtotal']?>, discount: <?=(float)$sale['discount']?>, tax: <?=(float)$sale['tax']?>,
    total: <?=(float)$sale['total']?>, paid_amount: <?=(float)$sale['paid_amount']?>, change_due: <?=(float)$sale['change_due']?>,
    payment_method: <?=json_encode($sale['payment_method'])?>, currency_symbol: <?=json_encode($sym)?>,
    footer: 'Thank you for your visit!'
});
</script>
<?php include __DIR__.'/layout_bottom.php'; ?>
