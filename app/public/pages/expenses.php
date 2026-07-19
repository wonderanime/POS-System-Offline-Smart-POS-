<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db(); $sym = get_setting('currency_symbol','Rs');

$dateFrom = trim($_GET['from'] ?? date('Y-m-01')); $dateTo = trim($_GET['to'] ?? date('Y-m-d'));
$where=['1=1']; $params=[];
if ($dateFrom) { $where[]='date(created_at)>=?'; $params[]=$dateFrom; }
if ($dateTo)   { $where[]='date(created_at)<=?'; $params[]=$dateTo; }
$wsql = implode(' AND ',$where);

$cs = $db->prepare("SELECT COUNT(*) c FROM expenses WHERE $wsql"); $cs->execute($params); $total=(int)$cs->fetch()['c'];
[$page,$perPage] = paginate_params();
$totalPages = max(1,(int)ceil($total/$perPage)); $page=min($page,$totalPages); $offset=($page-1)*$perPage;

$stmt = $db->prepare("SELECT * FROM expenses WHERE $wsql ORDER BY created_at DESC LIMIT ? OFFSET ?");
foreach($params as $i=>$v) $stmt->bindValue($i+1,$v);
$stmt->bindValue(count($params)+1,$perPage,PDO::PARAM_INT);
$stmt->bindValue(count($params)+2,$offset,PDO::PARAM_INT);
$stmt->execute(); $expenses = $stmt->fetchAll();

$sumStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) v FROM expenses WHERE $wsql"); $sumStmt->execute($params); $sumAmt=$sumStmt->fetch()['v'];

$activePage='expenses'; $pageTitle='Expenses';
include __DIR__.'/layout_top.php';
?>
<div class="metric-grid">
  <div class="metric-card"><span class="metric-label">Total Expenses (filtered)</span><span class="metric-val red"><?=$sym?> <?=money($sumAmt)?></span></div>
</div>
<div class="panel">
  <div class="panel-head">
    <span class="panel-title">Expense Records</span>
    <a href="/expenses-add" class="btn btn-primary"><?=$ico['plus']?> Add Expense</a>
  </div>
  <form class="filter-bar" method="get" action="/expenses" style="padding:14px 18px">
    <input type="date" name="from" value="<?=htmlspecialchars($dateFrom)?>">
    <input type="date" name="to" value="<?=htmlspecialchars($dateTo)?>">
    <button class="btn btn-sm btn-primary" type="submit">Filter</button>
  </form>
  <table class="dt">
    <thead><tr><th>Title</th><th>Category</th><th>Note</th><th class="num">Amount</th><th>Date</th></tr></thead>
    <tbody>
    <?php if(empty($expenses)): ?><tr><td colspan="5" class="empty-row">No expenses found.</td></tr>
    <?php else: foreach($expenses as $e): ?>
    <tr>
      <td style="font-weight:600"><?=htmlspecialchars($e['title'])?></td>
      <td><span class="badge badge-gray"><?=htmlspecialchars($e['category']??'General')?></span></td>
      <td class="dim" style="font-size:12px"><?=htmlspecialchars($e['note']??'—')?></td>
      <td class="num" style="font-weight:700;color:var(--red)"><?=$sym?> <?=money($e['amount'])?></td>
      <td class="dim" style="font-size:12px"><?=htmlspecialchars($e['created_at'])?></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?=render_pagination('/expenses',$page,$totalPages,$perPage,$total,['from'=>$dateFrom,'to'=>$dateTo])?>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
