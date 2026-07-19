<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login();
$db = get_db(); $sym = get_setting('currency_symbol','Rs');
$today = date('Y-m-d'); $month = date('Y-m');

$tod = $db->prepare("SELECT COALESCE(SUM(total),0) sales, COUNT(*) orders FROM sales WHERE date(created_at)=? AND status='completed'");
$tod->execute([$today]); $tod = $tod->fetch();

$todProfit = $db->prepare("SELECT COALESCE(SUM(si.qty*(si.price-si.cost_price)),0) p FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE date(s.created_at)=? AND s.status='completed'");
$todProfit->execute([$today]); $todProfit = $todProfit->fetch()['p'];

$mon = $db->prepare("SELECT COALESCE(SUM(total),0) sales FROM sales WHERE strftime('%Y-%m',created_at)=? AND status='completed'");
$mon->execute([$month]); $mon = $mon->fetch();

$monProfit = $db->prepare("SELECT COALESCE(SUM(si.qty*(si.price-si.cost_price)),0) p FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE strftime('%Y-%m',s.created_at)=? AND s.status='completed'");
$monProfit->execute([$month]); $monProfit = $monProfit->fetch()['p'];

$totalProducts = (int)$db->query("SELECT COUNT(*) c FROM products WHERE active=1")->fetch()['c'];
$lowStock = (int)$db->query("SELECT COUNT(*) c FROM products WHERE stock_qty<=low_stock_alert AND stock_qty>0 AND active=1")->fetch()['c'];
$outStock = (int)$db->query("SELECT COUNT(*) c FROM products WHERE stock_qty<=0 AND active=1")->fetch()['c'];
$totalCustomers = (int)$db->query("SELECT COUNT(*) c FROM customers WHERE active=1")->fetch()['c'];

$topProds = $db->prepare("SELECT si.product_name, SUM(si.qty) qty, SUM(si.total) rev FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE date(s.created_at)=? AND s.status='completed' GROUP BY si.product_name ORDER BY rev DESC LIMIT 5");
$topProds->execute([$today]); $topProds = $topProds->fetchAll();

$chart = [];
for ($i=6;$i>=0;$i--) {
  $d = date('Y-m-d', strtotime("-$i days"));
  $r = $db->prepare("SELECT COALESCE(SUM(total),0) v FROM sales WHERE date(created_at)=? AND status='completed'");
  $r->execute([$d]); $chart[] = ['day'=>date('D',strtotime($d)), 'val'=>(float)$r->fetch()['v']];
}
$chartMax = max(array_column($chart,'val')) ?: 1;

$recent = $db->query("SELECT invoice_no,total,payment_method,created_at FROM sales ORDER BY created_at DESC LIMIT 6")->fetchAll();
$lowStockList = $db->query("SELECT name,stock_qty,unit FROM products WHERE stock_qty<=low_stock_alert AND active=1 ORDER BY stock_qty ASC LIMIT 6")->fetchAll();

$activePage='dashboard'; $pageTitle='Dashboard';
include __DIR__.'/layout_top.php';
?>

<?php
$todExp = $db->prepare("SELECT COALESCE(SUM(amount),0) v FROM expenses WHERE date(created_at)=?"); $todExp->execute([$today]); $todExp = $todExp->fetch()['v'];
$netProfitToday = $todProfit - $todExp;
$monExp = $db->prepare("SELECT COALESCE(SUM(amount),0) v FROM expenses WHERE strftime('%Y-%m',created_at)=?"); $monExp->execute([$month]); $monExp = $monExp->fetch()['v'];
$netProfitMonth = $monProfit - $monExp;
$vt = $db->prepare("SELECT COUNT(*) c FROM sales WHERE date(created_at)=? AND status='voided'"); $vt->execute([$today]); $voidedTodayCount = (int)$vt->fetch()['c'];
?>
<div class="panel">
  <div class="panel-head"><span class="panel-title">Profit &amp; Loss — Today</span></div>
  <div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px">
    <div><div style="font-size:11px;color:var(--text2)">Sales Revenue</div><div style="font-size:18px;font-weight:800"><?=$sym?> <?=money($tod['sales'])?></div></div>
    <div><div style="font-size:11px;color:var(--text2)">Gross Profit</div><div style="font-size:18px;font-weight:800;color:var(--green)"><?=$sym?> <?=money($todProfit)?></div></div>
    <div><div style="font-size:11px;color:var(--text2)">Expenses</div><div style="font-size:18px;font-weight:800;color:var(--red)">- <?=$sym?> <?=money($todExp)?></div></div>
    <div><div style="font-size:11px;color:var(--text2)">Net Profit</div><div style="font-size:18px;font-weight:800;color:<?=$netProfitToday>=0?'var(--accent)':'var(--red)'?>"><?=$sym?> <?=money($netProfitToday)?></div></div>
    <div><div style="font-size:11px;color:var(--text2)">Profit Margin</div><div style="font-size:18px;font-weight:800"><?=$tod['sales']>0?round(($netProfitToday/$tod['sales'])*100,1):'0'?>%</div></div>
  </div>
  <?php if($voidedTodayCount>0): ?>
  <div style="padding:0 18px 16px;font-size:12px;color:var(--text3)"><?=$voidedTodayCount?> sale(s) voided today — already excluded from these numbers.</div>
  <?php endif; ?>
</div>

<div class="metric-grid">
  <div class="metric-card"><span class="metric-label">Total Sales (Today)</span><span class="metric-val"><?=$sym?> <?=money($tod['sales'])?></span><span class="metric-sub"><?=$tod['orders']?> orders</span></div>
  <div class="metric-card"><span class="metric-label">Net Profit (Today)</span><span class="metric-val green"><?=$sym?> <?=money($netProfitToday)?></span></div>
  <div class="metric-card"><span class="metric-label">Monthly Sales</span><span class="metric-val blue"><?=$sym?> <?=money($mon['sales'])?></span></div>
  <div class="metric-card"><span class="metric-label">Net Profit (Month)</span><span class="metric-val green"><?=$sym?> <?=money($netProfitMonth)?></span></div>
  <div class="metric-card"><span class="metric-label">Products</span><span class="metric-val"><?=$totalProducts?></span></div>
  <div class="metric-card"><span class="metric-label">Low Stock</span><span class="metric-val yellow"><?=$lowStock?></span></div>
  <div class="metric-card"><span class="metric-label">Out of Stock</span><span class="metric-val red"><?=$outStock?></span></div>
  <div class="metric-card"><span class="metric-label">Customers</span><span class="metric-val"><?=$totalCustomers?></span></div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:18px;margin-bottom:18px">
  <div class="panel">
    <div class="panel-head"><span class="panel-title">Sales Overview — Last 7 Days</span></div>
    <div class="panel-body" style="display:flex;align-items:flex-end;gap:10px;height:130px">
      <?php foreach($chart as $c): $h = max(4, round(($c['val']/$chartMax)*110)); ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
        <span style="font-size:10px;color:var(--text3)"><?=$sym?> <?=money($c['val'])?></span>
        <div style="width:100%;height:<?=$h?>px;background:linear-gradient(180deg,var(--accent),#7c3aed);border-radius:6px 6px 0 0"></div>
        <span style="font-size:10px;color:var(--text2);font-weight:600"><?=$c['day']?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel">
    <div class="panel-head"><span class="panel-title">Top Selling Products</span></div>
    <div class="panel-body" style="padding:10px 18px">
      <?php if(empty($topProds)): ?><p style="color:var(--text3);font-size:12px">No sales today yet</p>
      <?php else: foreach($topProds as $i=>$p): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:12.5px">
        <span style="color:var(--text2)"><?=$i+1?>. <?=htmlspecialchars($p['product_name'])?></span>
        <span style="font-weight:700"><?=$sym?> <?=money($p['rev'])?></span>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<?php if($lowStock>0): ?>
<div class="alert alert-warning">
  ⚠️ <?=$lowStock?> product(s) running low:
  <?php foreach($lowStockList as $a): ?>
  <span class="badge badge-yellow" style="margin-left:6px"><?=htmlspecialchars($a['name'])?> (<?=rtrim(rtrim(number_format($a['stock_qty'],2),'0'),'.')?> <?=$a['unit']?>)</span>
  <?php endforeach; ?>
  <a href="/stock" style="margin-left:8px;font-size:12px;font-weight:700">View Stock →</a>
</div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head"><span class="panel-title">Recent Sales</span><a href="/sales" class="btn btn-sm">View all</a></div>
  <table class="dt">
    <thead><tr><th>Invoice</th><th>Payment</th><th>Date</th><th class="num">Total</th></tr></thead>
    <tbody>
    <?php if(empty($recent)): ?>
      <tr><td colspan="4" class="empty-row">No sales yet — go to <a href="/pos">POS</a> to ring up your first order.</td></tr>
    <?php else: foreach($recent as $s): ?>
      <tr>
        <td><b><?=htmlspecialchars($s['invoice_no'])?></b></td>
        <td><span class="badge badge-gray"><?=htmlspecialchars($s['payment_method'])?></span></td>
        <td class="dim" style="font-size:12px"><?=htmlspecialchars($s['created_at'])?></td>
        <td class="num"><?=$sym?> <?=money($s['total'])?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__.'/layout_bottom.php'; ?>
