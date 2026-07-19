<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db(); $sym = get_setting('currency_symbol','Rs');

[$page,$perPage] = paginate_params();
$total = (int)$db->query("SELECT COUNT(*) c FROM customers")->fetch()['c'];
$totalPages = max(1,(int)ceil($total/$perPage)); $page=min($page,$totalPages); $offset=($page-1)*$perPage;
$stmt = $db->prepare("SELECT * FROM customers ORDER BY name LIMIT ? OFFSET ?");
$stmt->bindValue(1,$perPage,PDO::PARAM_INT); $stmt->bindValue(2,$offset,PDO::PARAM_INT); $stmt->execute();
$customers = $stmt->fetchAll();

$activePage='customers'; $pageTitle='Customers';
include __DIR__.'/layout_top.php';
?>
<div class="panel">
  <div class="panel-head">
    <span class="panel-title">All Customers</span>
    <a href="/customers-add" class="btn btn-primary"><?=$ico['plus']?> Add Customer</a>
  </div>
  <table class="dt">
    <thead><tr><th>Name</th><th>Phone</th><th class="num">Total Orders</th><th class="num">Due Balance</th></tr></thead>
    <tbody>
    <?php if(empty($customers)): ?><tr><td colspan="4" class="empty-row">No customers yet.</td></tr>
    <?php else: foreach($customers as $c):
      $orders = $db->prepare("SELECT COUNT(*) c FROM sales WHERE customer_id=?"); $orders->execute([$c['id']]); $orders=$orders->fetch()['c'];
    ?>
    <tr>
      <td style="font-weight:600"><?=htmlspecialchars($c['name'])?></td>
      <td class="dim"><?=htmlspecialchars($c['phone']??'—')?></td>
      <td class="num"><?=$orders?></td>
      <td class="num" style="font-weight:700;color:<?=$c['due_balance']>0?'var(--red)':'var(--text2)'?>"><?=$sym?> <?=money($c['due_balance'])?></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?=render_pagination('/customers',$page,$totalPages,$perPage,$total)?>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
