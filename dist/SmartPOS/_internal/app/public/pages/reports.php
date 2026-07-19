<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db(); $sym = get_setting('currency_symbol','Rs');

$rpt  = $_GET['rpt']  ?? 'sales';
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

$reports = ['sales'=>'Sales Report','profit'=>'Profit Report','expenses'=>'Expense Report','tax'=>'Tax Report','products'=>'Top Products','stock'=>'Stock Report','customers'=>'Customer Report'];

$rows = []; $cols = []; $summary = [];

switch ($rpt) {
  case 'sales':
    $cols = ['Date','Orders','Revenue']; 
    $stmt = $db->prepare("SELECT date(created_at) d, COUNT(*) cnt, SUM(total) rev FROM sales WHERE date(created_at) BETWEEN ? AND ? AND status='completed' GROUP BY d ORDER BY d DESC");
    $stmt->execute([$from,$to]); $raw = $stmt->fetchAll();
    foreach($raw as $r) $rows[] = [$r['d'],$r['cnt'],$sym.' '.money($r['rev'])];
    $summary = ['Total Revenue' => $sym.' '.money(array_sum(array_column($raw,'rev')))];
    break;

  case 'profit':
    $cols = ['Period','Revenue','Cost of Goods','Gross Profit','Expenses','Net Profit'];
    $rev = $db->prepare("SELECT COALESCE(SUM(total),0) v FROM sales WHERE date(created_at) BETWEEN ? AND ? AND status='completed'"); $rev->execute([$from,$to]); $rev=$rev->fetch()['v'];
    $cost= $db->prepare("SELECT COALESCE(SUM(si.qty*si.cost_price),0) v FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE date(s.created_at) BETWEEN ? AND ? AND s.status='completed'"); $cost->execute([$from,$to]); $cost=$cost->fetch()['v'];
    $exp = $db->prepare("SELECT COALESCE(SUM(amount),0) v FROM expenses WHERE date(created_at) BETWEEN ? AND ?"); $exp->execute([$from,$to]); $exp=$exp->fetch()['v'];
    $gross = $rev-$cost; $net = $gross-$exp;
    $rows = [[$from.' to '.$to, $sym.' '.money($rev), $sym.' '.money($cost), $sym.' '.money($gross), $sym.' '.money($exp), $sym.' '.money($net)]];
    $summary = ['Net Profit' => $sym.' '.money($net), 'Net Margin' => $rev>0?round(($net/$rev)*100,1).'%':'0%'];
    break;

  case 'expenses':
    $cols = ['Date','Title','Category','Amount'];
    $stmt = $db->prepare("SELECT * FROM expenses WHERE date(created_at) BETWEEN ? AND ? ORDER BY created_at DESC");
    $stmt->execute([$from,$to]); $raw = $stmt->fetchAll();
    foreach($raw as $r) $rows[] = [$r['created_at'],$r['title'],$r['category']?:'—',$sym.' '.money($r['amount'])];
    $summary = ['Total Expenses' => $sym.' '.money(array_sum(array_column($raw,'amount')))];
    break;

  case 'tax':
    $cols = ['Invoice','Date','Tax Collected'];
    $stmt = $db->prepare("SELECT invoice_no,created_at,tax FROM sales WHERE tax>0 AND date(created_at) BETWEEN ? AND ? AND status='completed' ORDER BY created_at DESC");
    $stmt->execute([$from,$to]); $raw = $stmt->fetchAll();
    foreach($raw as $r) $rows[] = [$r['invoice_no'],$r['created_at'],$sym.' '.money($r['tax'])];
    $summary = ['Total Tax Collected' => $sym.' '.money(array_sum(array_column($raw,'tax')))];
    break;

  case 'products':
    $cols = ['Product','Qty Sold','Revenue','Profit'];
    $stmt = $db->prepare("SELECT si.product_name, SUM(si.qty) qty, SUM(si.total) rev, SUM(si.qty*(si.price-si.cost_price)) profit FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE date(s.created_at) BETWEEN ? AND ? AND s.status='completed' GROUP BY si.product_name ORDER BY rev DESC LIMIT 50");
    $stmt->execute([$from,$to]); $raw = $stmt->fetchAll();
    foreach($raw as $r) $rows[] = [$r['product_name'],rtrim(rtrim(number_format($r['qty'],2),'0'),'.'),$sym.' '.money($r['rev']),$sym.' '.money($r['profit'])];
    break;

  case 'stock':
    $cols = ['Product','Category','Stock','Unit','Stock Value'];
    $raw = $db->query("SELECT p.name,c.name cat,p.stock_qty,p.unit,p.stock_qty*p.purchase_price val FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 ORDER BY val DESC")->fetchAll();
    foreach($raw as $r) $rows[] = [$r['name'],$r['cat']??'—',rtrim(rtrim(number_format($r['stock_qty'],2),'0'),'.'),$r['unit'],$sym.' '.money($r['val'])];
    $summary = ['Total Stock Value' => $sym.' '.money(array_sum(array_column($raw,'val')))];
    break;

  case 'customers':
    $cols = ['Customer','Phone','Due Balance'];
    $raw = $db->query("SELECT name,phone,due_balance FROM customers ORDER BY due_balance DESC")->fetchAll();
    foreach($raw as $r) $rows[] = [$r['name'],$r['phone']??'—',$sym.' '.money($r['due_balance'])];
    break;
}

if (isset($_GET['export']) && $_GET['export']==='csv' && !empty($cols)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$rpt.'_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w'); fputcsv($out,$cols);
    foreach($rows as $row) fputcsv($out,$row);
    fclose($out); exit;
}

$activePage='reports'; $pageTitle='Reports';
include __DIR__.'/layout_top.php';
?>
<div style="display:flex;gap:16px;align-items:flex-start">
<div class="panel" style="width:200px;flex-shrink:0">
  <div class="panel-head"><span class="panel-title">Report Type</span></div>
  <div style="padding:8px">
    <?php foreach($reports as $key=>$label): ?>
    <a href="/reports?rpt=<?=$key?>&from=<?=htmlspecialchars($from)?>&to=<?=htmlspecialchars($to)?>"
       style="display:block;padding:9px 12px;border-radius:10px;font-size:13px;font-weight:600;color:<?=$rpt===$key?'#fff':'var(--text2)'?>;background:<?=$rpt===$key?'var(--accent)':'transparent'?>;text-decoration:none;margin-bottom:3px">
      <?=htmlspecialchars($label)?>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<div style="flex:1;min-width:0">
  <div class="panel" style="margin-bottom:14px">
    <div class="panel-head">
      <span class="panel-title"><?=htmlspecialchars($reports[$rpt]??$rpt)?></span>
      <a href="/reports?rpt=<?=$rpt?>&from=<?=htmlspecialchars($from)?>&to=<?=htmlspecialchars($to)?>&export=csv" class="btn btn-sm">Export CSV</a>
    </div>
    <form class="filter-bar" method="get" action="/reports" style="padding:14px 18px">
      <input type="hidden" name="rpt" value="<?=htmlspecialchars($rpt)?>">
      <label style="font-size:12px;color:var(--text2)">From</label><input type="date" name="from" value="<?=htmlspecialchars($from)?>">
      <label style="font-size:12px;color:var(--text2)">To</label><input type="date" name="to" value="<?=htmlspecialchars($to)?>">
      <button class="btn btn-sm btn-primary" type="submit">Run</button>
    </form>
    <?php if(!empty($summary)): ?>
    <div style="display:flex;gap:12px;padding:0 18px 14px;flex-wrap:wrap">
      <?php foreach($summary as $k=>$v): ?>
      <div class="metric-card" style="min-width:150px"><span class="metric-label"><?=htmlspecialchars($k)?></span><span class="metric-val" style="font-size:19px"><?=htmlspecialchars($v)?></span></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="panel">
    <table class="dt">
      <thead><tr><?php foreach($cols as $c): ?><th><?=htmlspecialchars($c)?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php if(empty($rows)): ?><tr><td colspan="<?=count($cols)?>" class="empty-row">No data for this period.</td></tr>
      <?php else: foreach($rows as $row): ?><tr><?php foreach($row as $cell): ?><td style="font-size:12.5px"><?=htmlspecialchars((string)$cell)?></td><?php endforeach; ?></tr><?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
