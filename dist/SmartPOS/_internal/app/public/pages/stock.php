<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db(); $sym = get_setting('currency_symbol','Rs');

$search = trim($_GET['q'] ?? ''); $stockFilter = trim($_GET['stock'] ?? '');
$where=['p.active=1']; $params=[];
if ($search) { $where[]='p.name LIKE ?'; $params[]="%$search%"; }
if ($stockFilter==='low') $where[]='p.stock_qty<=p.low_stock_alert AND p.stock_qty>0';
if ($stockFilter==='out') $where[]='p.stock_qty<=0';
$wsql = implode(' AND ',$where);

$cs = $db->prepare("SELECT COUNT(*) c FROM products p WHERE $wsql"); $cs->execute($params); $total=(int)$cs->fetch()['c'];
[$page,$perPage] = paginate_params();
$totalPages = max(1,(int)ceil($total/$perPage)); $page=min($page,$totalPages); $offset=($page-1)*$perPage;

$stmt = $db->prepare("SELECT p.*,c.name cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE $wsql ORDER BY p.stock_qty ASC LIMIT ? OFFSET ?");
foreach($params as $i=>$v) $stmt->bindValue($i+1,$v);
$stmt->bindValue(count($params)+1,$perPage,PDO::PARAM_INT);
$stmt->bindValue(count($params)+2,$offset,PDO::PARAM_INT);
$stmt->execute(); $products = $stmt->fetchAll();

$stockValue = $db->query("SELECT COALESCE(SUM(stock_qty*purchase_price),0) v FROM products WHERE active=1")->fetch()['v'];

$activePage='stock'; $pageTitle='Stock Inventory';
include __DIR__.'/layout_top.php';
?>
<div class="metric-grid">
  <div class="metric-card"><span class="metric-label">Stock Value (Cost)</span><span class="metric-val"><?=$sym?> <?=money($stockValue)?></span></div>
  <div class="metric-card"><span class="metric-label">Total Products</span><span class="metric-val"><?=$total?></span></div>
</div>
<div class="panel">
  <div class="panel-head"><span class="panel-title">Current Stock</span></div>
  <form class="filter-bar" method="get" action="/stock" style="padding:14px 18px">
    <input type="text" name="q" placeholder="Search product" value="<?=htmlspecialchars($search)?>">
    <select name="stock">
      <option value="">All Stock</option>
      <option value="low" <?=$stockFilter==='low'?'selected':''?>>Low Stock</option>
      <option value="out" <?=$stockFilter==='out'?'selected':''?>>Out of Stock</option>
    </select>
    <button class="btn btn-sm btn-primary" type="submit">Filter</button>
    <a class="btn btn-sm" href="/stock">Reset</a>
  </form>
  <table class="dt">
    <thead><tr><th>Product</th><th>Category</th><th class="num">Stock</th><th>Unit</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if(empty($products)): ?><tr><td colspan="6" class="empty-row">No products found.</td></tr>
    <?php else: foreach($products as $p): ?>
    <tr>
      <td style="font-weight:600"><?=htmlspecialchars($p['name'])?></td>
      <td class="dim"><?=htmlspecialchars($p['cat_name']??'—')?></td>
      <td class="num"><?=rtrim(rtrim(number_format($p['stock_qty'],2),'0'),'.')?></td>
      <td class="dim"><?=htmlspecialchars($p['unit'])?></td>
      <td>
        <?php if($p['stock_qty']<=0): ?><span class="badge badge-red">Out of Stock</span>
        <?php elseif($p['stock_qty']<=$p['low_stock_alert']): ?><span class="badge badge-yellow">Low</span>
        <?php else: ?><span class="badge badge-green">In Stock</span><?php endif; ?>
      </td>
      <td><a class="btn btn-xs" href="/stock-adjust?id=<?=$p['id']?>">Adjust</a></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?=render_pagination('/stock',$page,$totalPages,$perPage,$total,['q'=>$search,'stock'=>$stockFilter])?>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
