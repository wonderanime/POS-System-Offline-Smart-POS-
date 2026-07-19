<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db();

$id = (int)($_GET['id'] ?? 0);
$s = $db->prepare("SELECT p.*,c.name cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=?");
$s->execute([$id]); $product = $s->fetch();
if (!$product) { header('Location: /stock'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $changeQty = abs((float)($_POST['change_qty'] ?? 0));
    $direction = $_POST['direction'] ?? 'add';
    $reason = trim($_POST['reason'] ?? '');
    if ($changeQty<=0) { $error='Enter a quantity greater than zero.'; }
    elseif (!$reason) { $error='Please enter a reason.'; }
    else {
        $signed = $direction==='remove' ? -$changeQty : $changeQty;
        $db->beginTransaction();
        $db->prepare("UPDATE products SET stock_qty=MAX(0,stock_qty+?) WHERE id=?")->execute([$signed,$product['id']]);
        $db->prepare("INSERT INTO stock_adjustments (product_id,change_qty,reason) VALUES (?,?,?)")->execute([$product['id'],$signed,$reason]);
        $db->commit();
        header('Location: /stock'); exit;
    }
}

$history = $db->prepare("SELECT * FROM stock_adjustments WHERE product_id=? ORDER BY created_at DESC LIMIT 10");
$history->execute([$id]); $history = $history->fetchAll();

$activePage='stock'; $pageTitle='Stock Adjustment';
include __DIR__.'/layout_top.php';
?>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start">
  <form class="form-card" method="post">
    <div style="margin-bottom:16px">
      <div style="font-size:16px;font-weight:700"><?=htmlspecialchars($product['name'])?></div>
      <div style="font-size:12px;color:var(--text2)"><?=htmlspecialchars($product['cat_name']??'')?></div>
    </div>
    <div class="metric-card" style="margin-bottom:16px">
      <span class="metric-label">Current Stock</span>
      <span class="metric-val"><?=rtrim(rtrim(number_format($product['stock_qty'],2),'0'),'.')?> <?=htmlspecialchars($product['unit'])?></span>
    </div>
    <div class="form-grid">
      <div class="form-group full"><label class="form-label">Direction</label>
        <select class="form-input" name="direction">
          <option value="add">Add Stock</option>
          <option value="remove">Remove Stock (damaged/lost/correction)</option>
        </select>
      </div>
      <div class="form-group full"><label class="form-label">Quantity</label><input class="form-input" type="number" step="0.01" min="0.01" name="change_qty" required></div>
      <div class="form-group full"><label class="form-label">Reason *</label><input class="form-input" name="reason" required placeholder="e.g. Received stock, damaged, count correction"></div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Save Adjustment</button><a class="btn" href="/stock">Cancel</a></div>
    </div>
  </form>
  <div class="panel">
    <div class="panel-head"><span class="panel-title">Recent Adjustments</span></div>
    <table class="dt">
      <thead><tr><th>Date</th><th>Change</th><th>Reason</th></tr></thead>
      <tbody>
      <?php if(empty($history)): ?><tr><td colspan="3" class="empty-row">No adjustments yet.</td></tr>
      <?php else: foreach($history as $h): ?>
      <tr>
        <td style="font-size:11px;color:var(--text2)"><?=htmlspecialchars($h['created_at'])?></td>
        <td style="font-weight:700;color:<?=$h['change_qty']>0?'var(--green)':'var(--red)'?>"><?=$h['change_qty']>0?'+':''?><?=number_format($h['change_qty'],2)?></td>
        <td style="font-size:12px"><?=htmlspecialchars($h['reason']??'—')?></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
