<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db(); $sym = get_setting('currency_symbol','Rs');

[$page,$perPage] = paginate_params();
$total = (int)$db->query("SELECT COUNT(*) c FROM suppliers")->fetch()['c'];
$totalPages = max(1,(int)ceil($total/$perPage)); $page=min($page,$totalPages); $offset=($page-1)*$perPage;
$stmt = $db->prepare("SELECT * FROM suppliers ORDER BY name LIMIT ? OFFSET ?");
$stmt->bindValue(1,$perPage,PDO::PARAM_INT); $stmt->bindValue(2,$offset,PDO::PARAM_INT); $stmt->execute();
$suppliers = $stmt->fetchAll();

$activePage='suppliers'; $pageTitle='Suppliers';
include __DIR__.'/layout_top.php';
?>
<div class="panel">
  <div class="panel-head">
    <span class="panel-title">All Suppliers</span>
    <a href="/suppliers-add" class="btn btn-primary"><?=$ico['plus']?> Add Supplier</a>
  </div>
  <table class="dt">
    <thead><tr><th>Name</th><th>Phone</th><th>Address</th><th class="num">Due Balance</th></tr></thead>
    <tbody>
    <?php if(empty($suppliers)): ?><tr><td colspan="4" class="empty-row">No suppliers yet.</td></tr>
    <?php else: foreach($suppliers as $s): ?>
    <tr>
      <td style="font-weight:600"><?=htmlspecialchars($s['name'])?></td>
      <td class="dim"><?=htmlspecialchars($s['phone']??'—')?></td>
      <td class="dim"><?=htmlspecialchars($s['address']??'—')?></td>
      <td class="num" style="font-weight:700;color:<?=$s['due_balance']>0?'var(--yellow)':'var(--text2)'?>"><?=$sym?> <?=money($s['due_balance'])?></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?=render_pagination('/suppliers',$page,$totalPages,$perPage,$total)?>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
