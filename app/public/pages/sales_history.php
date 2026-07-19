<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db(); $sym = get_setting('currency_symbol','Rs');

$dateFrom = trim($_GET['from'] ?? ''); $dateTo = trim($_GET['to'] ?? date('Y-m-d'));
$where=['1=1']; $params=[];
if ($dateFrom) { $where[]='date(s.created_at)>=?'; $params[]=$dateFrom; }
if ($dateTo)   { $where[]='date(s.created_at)<=?'; $params[]=$dateTo; }
$wsql = implode(' AND ',$where);

$cs = $db->prepare("SELECT COUNT(*) c FROM sales s WHERE $wsql"); $cs->execute($params); $total=(int)$cs->fetch()['c'];
[$page,$perPage] = paginate_params();
$totalPages = max(1,(int)ceil($total/$perPage)); $page=min($page,$totalPages); $offset=($page-1)*$perPage;

$stmt = $db->prepare("SELECT s.*,c.name cust_name FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE $wsql ORDER BY s.created_at DESC LIMIT ? OFFSET ?");
foreach($params as $i=>$v) $stmt->bindValue($i+1,$v);
$stmt->bindValue(count($params)+1,$perPage,PDO::PARAM_INT);
$stmt->bindValue(count($params)+2,$offset,PDO::PARAM_INT);
$stmt->execute(); $sales = $stmt->fetchAll();

$sumStmt = $db->prepare("SELECT COALESCE(SUM(s.total),0) t FROM sales s WHERE $wsql"); $sumStmt->execute($params); $sumTotal=$sumStmt->fetch()['t'];

$activePage='sales'; $pageTitle='Sales History';
include __DIR__.'/layout_top.php';
?>
<div class="metric-grid">
  <div class="metric-card"><span class="metric-label">Total Sales (filtered)</span><span class="metric-val"><?=$sym?> <?=money($sumTotal)?></span></div>
  <div class="metric-card"><span class="metric-label">Orders</span><span class="metric-val"><?=$total?></span></div>
</div>
<div class="panel">
  <div class="panel-head"><span class="panel-title">All Sales</span></div>
  <form class="filter-bar" method="get" action="/sales" style="padding:14px 18px">
    <input type="date" name="from" value="<?=htmlspecialchars($dateFrom)?>">
    <input type="date" name="to" value="<?=htmlspecialchars($dateTo)?>">
    <button class="btn btn-sm btn-primary" type="submit">Filter</button>
    <a class="btn btn-sm" href="/sales">Reset</a>
  </form>
  <table class="dt">
    <thead><tr><th>Invoice</th><th>Customer</th><th>Payment</th><th class="num">Total</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php if(empty($sales)): ?><tr><td colspan="6" class="empty-row">No sales found.</td></tr>
    <?php else: foreach($sales as $s): ?>
    <tr>
      <td><a href="/sale-view?id=<?=$s['id']?>"><b><?=htmlspecialchars($s['invoice_no'])?></b></a></td>
      <td class="dim"><?=htmlspecialchars($s['cust_name']??'Walk-in')?></td>
      <td><span class="badge badge-gray"><?=htmlspecialchars($s['payment_method'])?></span></td>
      <td class="num" style="font-weight:700"><?=$sym?> <?=money($s['total'])?></td>
      <td class="dim" style="font-size:12px"><?=htmlspecialchars($s['created_at'])?></td>
      <td>
        <a class="btn btn-xs" href="/sale-view?id=<?=$s['id']?>">View</a>
        <?php if($s['status']==='voided'): ?><span class="badge badge-red" style="margin-left:4px">Voided</span>
        <?php else: ?><a class="btn btn-xs btn-danger" href="/sale-void?id=<?=$s['id']?>" style="margin-left:4px">Void</a><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?=render_pagination('/sales',$page,$totalPages,$perPage,$total,['from'=>$dateFrom,'to'=>$dateTo])?>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
