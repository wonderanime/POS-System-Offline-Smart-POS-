<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db(); $sym = get_setting('currency_symbol','Rs');

$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $supId = $_POST['supplier_id'] ?? null;
    $paid  = (float)($_POST['paid_amount'] ?? 0);
    $prodIds = $_POST['product_id'] ?? []; $qtys = $_POST['qty'] ?? []; $costs = $_POST['cost_price'] ?? [];

    $items=[]; $total=0;
    foreach($prodIds as $i=>$pid) {
        if(!$pid || empty($qtys[$i]) || $qtys[$i]<=0) continue;
        $p = $db->prepare("SELECT * FROM products WHERE id=?"); $p->execute([$pid]); $prod=$p->fetch();
        if(!$prod) continue;
        $qty=(float)$qtys[$i]; $cost=(float)$costs[$i]; $line=round($qty*$cost,2);
        $items[]=['product_id'=>$pid,'name'=>$prod['name'],'qty'=>$qty,'cost_price'=>$cost,'total'=>$line];
        $total+=$line;
    }
    if(empty($items)) { $error='Add at least one product.'; }
    else {
        $invNo = next_invoice('PUR');
        $db->beginTransaction();
        $db->prepare("INSERT INTO purchases (invoice_no,supplier_id,total,paid_amount) VALUES (?,?,?,?)")->execute([$invNo,$supId?:null,$total,$paid]);
        $purId = (int)$db->lastInsertId();
        $pi = $db->prepare("INSERT INTO purchase_items (purchase_id,product_id,product_name,qty,cost_price,total) VALUES (?,?,?,?,?,?)");
        $su = $db->prepare("UPDATE products SET stock_qty=stock_qty+?, purchase_price=? WHERE id=?");
        foreach($items as $it) { $pi->execute([$purId,$it['product_id'],$it['name'],$it['qty'],$it['cost_price'],$it['total']]); $su->execute([$it['qty'],$it['cost_price'],$it['product_id']]); }
        if($supId && $total>$paid) $db->prepare("UPDATE suppliers SET due_balance=due_balance+? WHERE id=?")->execute([$total-$paid,$supId]);
        $db->commit();
        header('Location: /purchases'); exit;
    }
}

$suppliers = $db->query("SELECT * FROM suppliers WHERE active=1 ORDER BY name")->fetchAll();
$products  = $db->query("SELECT id,name,sku,purchase_price FROM products WHERE active=1 ORDER BY name")->fetchAll();

$activePage='purchases'; $pageTitle='Record Purchase';
include __DIR__.'/layout_top.php';
?>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form class="form-card" method="post" style="max-width:860px">
<div class="form-grid">
  <div class="form-group"><label class="form-label">Supplier</label>
    <select class="form-input" name="supplier_id">
      <option value="">— No supplier —</option>
      <?php foreach($suppliers as $s): ?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['name'])?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="form-group"><label class="form-label">Amount Paid Now (<?=$sym?>)</label><input class="form-input" type="number" step="0.01" min="0" name="paid_amount" value="0"></div>
</div>
<div style="margin:18px 0 10px;font-weight:700;font-size:14px">Purchase Items</div>
<table class="dt" id="items-table" style="margin-bottom:10px">
  <thead><tr><th>Product</th><th>Qty</th><th>Cost Price</th><th>Line Total</th><th></th></tr></thead>
  <tbody id="items-body">
    <tr class="item-row">
      <td><select class="form-input" name="product_id[]" style="font-size:12px;padding:8px">
        <option value="">— Select product —</option>
        <?php foreach($products as $p): ?><option value="<?=$p['id']?>" data-cost="<?=$p['purchase_price']?>"><?=htmlspecialchars($p['name'])?></option><?php endforeach; ?>
      </select></td>
      <td><input class="form-input qty-in" type="number" step="0.01" min="0" name="qty[]" value="1" style="width:70px"></td>
      <td><input class="form-input cost-in" type="number" step="0.01" min="0" name="cost_price[]" value="0" style="width:100px"></td>
      <td class="line-total num" style="font-weight:700">—</td>
      <td><button type="button" class="btn btn-xs btn-danger remove-row">Remove</button></td>
    </tr>
  </tbody>
</table>
<button type="button" class="btn btn-sm" id="add-row">+ Add Another Product</button>
<div class="form-actions" style="margin-top:20px"><button class="btn btn-primary" type="submit">Save Purchase</button><a class="btn" href="/purchases">Cancel</a></div>
</form>
<script>
var sym = <?=json_encode($sym)?>;
function updateLine(row) {
  var qty = parseFloat(row.querySelector('.qty-in').value)||0, cost = parseFloat(row.querySelector('.cost-in').value)||0;
  row.querySelector('.line-total').textContent = qty&&cost ? sym+' '+Math.round(qty*cost).toLocaleString() : '—';
}
function wireRow(row) {
  row.querySelector('select').addEventListener('change', function(){ row.querySelector('.cost-in').value = this.options[this.selectedIndex].getAttribute('data-cost')||'0'; updateLine(row); });
  row.querySelector('.qty-in').addEventListener('input', function(){ updateLine(row); });
  row.querySelector('.cost-in').addEventListener('input', function(){ updateLine(row); });
  row.querySelector('.remove-row').addEventListener('click', function(){ if(document.querySelectorAll('.item-row').length>1) row.remove(); });
}
document.querySelectorAll('.item-row').forEach(wireRow);
document.getElementById('add-row').addEventListener('click', function(){
  var tpl = document.querySelector('.item-row').cloneNode(true);
  tpl.querySelector('select').value=''; tpl.querySelector('.qty-in').value='1'; tpl.querySelector('.cost-in').value='0'; tpl.querySelector('.line-total').textContent='—';
  document.getElementById('items-body').appendChild(tpl); wireRow(tpl);
});
</script>
<?php include __DIR__.'/layout_bottom.php'; ?>
