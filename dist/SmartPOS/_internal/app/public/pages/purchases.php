<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db(); $sym = get_setting('currency_symbol','Rs');

[$page,$perPage] = paginate_params();
$total = (int)$db->query("SELECT COUNT(*) c FROM purchases")->fetch()['c'];
$totalPages = max(1,(int)ceil($total/$perPage)); $page=min($page,$totalPages); $offset=($page-1)*$perPage;

$stmt = $db->prepare("SELECT pu.*,s.name sup_name FROM purchases pu LEFT JOIN suppliers s ON s.id=pu.supplier_id ORDER BY pu.created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1,$perPage,PDO::PARAM_INT); $stmt->bindValue(2,$offset,PDO::PARAM_INT); $stmt->execute();
$purchases = $stmt->fetchAll();

$activePage='purchases'; $pageTitle='Purchases';
include __DIR__.'/layout_top.php';
?>
<div class="panel">
  <div class="panel-head">
    <span class="panel-title">Purchase Records</span>
    <a href="/purchases-add" class="btn btn-primary"><?=$ico['plus']?> Record Purchase</a>
  </div>
  <table class="dt">
    <thead><tr><th>Invoice</th><th>Supplier</th><th>Date</th><th class="num">Total</th><th class="num">Paid</th></tr></thead>
    <tbody>
    <?php if(empty($purchases)): ?><tr><td colspan="5" class="empty-row">No purchases recorded yet.</td></tr>
    <?php else: foreach($purchases as $p): ?>
    <tr>
      <td style="font-weight:600"><?=htmlspecialchars($p['invoice_no'])?></td>
      <td class="dim"><?=htmlspecialchars($p['sup_name']??'—')?></td>
      <td class="dim" style="font-size:12px"><?=htmlspecialchars($p['created_at'])?></td>
      <td class="num"><?=$sym?> <?=money($p['total'])?></td>
      <td class="num green"><?=$sym?> <?=money($p['paid_amount'])?></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?=render_pagination('/purchases',$page,$totalPages,$perPage,$total)?>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
