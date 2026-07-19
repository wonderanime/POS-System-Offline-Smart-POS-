<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login();
$db = get_db(); $sym = get_setting('currency_symbol','Rs');

$search = trim($_GET['q'] ?? ''); $catId = trim($_GET['cat'] ?? '');
$where=['p.active=1']; $params=[];
if ($search) { $where[]='(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; $params[]="%$search%"; }
if ($catId)  { $where[]='p.category_id=?'; $params[]=$catId; }
$wsql = implode(' AND ',$where);

$cs = $db->prepare("SELECT COUNT(*) c FROM products p WHERE $wsql"); $cs->execute($params); $total=(int)$cs->fetch()['c'];
[$page,$perPage] = paginate_params();
$totalPages = max(1,(int)ceil($total/$perPage)); $page=min($page,$totalPages); $offset=($page-1)*$perPage;

$stmt = $db->prepare("SELECT p.*,c.name cat_name,b.name brand_name FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN brands b ON b.id=p.brand_id WHERE $wsql ORDER BY p.name LIMIT ? OFFSET ?");
foreach($params as $i=>$v) $stmt->bindValue($i+1,$v);
$stmt->bindValue(count($params)+1,$perPage,PDO::PARAM_INT);
$stmt->bindValue(count($params)+2,$offset,PDO::PARAM_INT);
$stmt->execute(); $products = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$activePage='products'; $pageTitle='Products';
include __DIR__.'/layout_top.php';
?>
<div class="panel">
  <div class="panel-head">
    <span class="panel-title">All Products (<?=$total?>)</span>
    <a href="/products-add" class="btn btn-primary"><?=$ico['plus']?> Add Product</a>
  </div>
  <form class="filter-bar" method="get" action="/products" style="padding:14px 18px">
    <input type="text" name="q" placeholder="Name / SKU / Barcode" value="<?=htmlspecialchars($search)?>">
    <select name="cat">
      <option value="">All Categories</option>
      <?php foreach($categories as $c): ?><option value="<?=$c['id']?>" <?=$catId==(string)$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-primary" type="submit">Filter</button>
    <a class="btn btn-sm" href="/products">Reset</a>
  </form>
  <div class="page-toolbar" id="bulk-bar" style="display:none;padding:0 18px 14px">
    <span id="bulk-count" style="font-size:12.5px;color:var(--text2);font-weight:600"></span>
    <div style="display:flex;gap:8px;margin-left:auto">
      <a class="btn btn-sm" id="bulk-edit-btn" href="#">Edit Selected</a>
      <form method="post" action="/products-bulk-delete" id="bulk-delete-form" style="display:inline">
        <input type="hidden" name="ids" id="bulk-ids">
        <button class="btn btn-sm btn-danger" type="submit" data-confirm="Delete the selected products? This cannot be undone.">Delete Selected</button>
      </form>
    </div>
  </div>
  <table class="dt">
    <thead><tr><th style="width:32px"><input type="checkbox" id="select-all"></th><th>Product</th><th>SKU</th><th>Category</th><th class="num">Cost</th><th class="num">Price</th><th class="num">Stock</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if(empty($products)): ?>
    <tr><td colspan="9" class="empty-row">No products found. <a href="/products-add">Add your first product</a>.</td></tr>
    <?php else: foreach($products as $p): ?>
    <tr>
      <td><input type="checkbox" class="row-check" value="<?=$p['id']?>"></td>
      <td>
        <?php if($p['image_path']): ?><img src="/assets/uploads/<?=htmlspecialchars(basename($p['image_path']))?>" style="width:30px;height:30px;object-fit:cover;border-radius:8px;vertical-align:middle;margin-right:8px"><?php endif; ?>
        <?=htmlspecialchars($p['name'])?>
      </td>
      <td class="dim"><?=htmlspecialchars($p['sku']??'—')?></td>
      <td class="dim"><?=htmlspecialchars($p['cat_name']??'—')?></td>
      <td class="num"><?=$sym?> <?=number_format($p['purchase_price'],0)?></td>
      <td class="num"><?=$sym?> <?=number_format($p['sale_price'],0)?></td>
      <td class="num"><?=rtrim(rtrim(number_format($p['stock_qty'],2),'0'),'.')?> <?=htmlspecialchars($p['unit'])?></td>
      <td>
        <?php if($p['stock_qty']<=0): ?><span class="badge badge-red">Out of Stock</span>
        <?php elseif($p['stock_qty']<=$p['low_stock_alert']): ?><span class="badge badge-yellow">Low Stock</span>
        <?php else: ?><span class="badge badge-green">In Stock</span><?php endif; ?>
      </td>
      <td>
        <div class="actions">
          <a class="act-btn" href="/products-add?id=<?=$p['id']?>" title="Edit"><?=$ico['edit']?></a>
          <form method="post" action="/products-delete" style="display:inline">
            <input type="hidden" name="id" value="<?=$p['id']?>">
            <button class="act-btn danger" type="submit" data-confirm="Delete '<?=htmlspecialchars($p['name'])?>'?"><?=$ico['trash']?></button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?=render_pagination('/products',$page,$totalPages,$perPage,$total,['q'=>$search,'cat'=>$catId])?>
</div>

<script>
(function(){
    var selectAll = document.getElementById('select-all');
    var bulkBar   = document.getElementById('bulk-bar');
    var bulkCount = document.getElementById('bulk-count');
    var bulkIds   = document.getElementById('bulk-ids');
    var bulkEditBtn = document.getElementById('bulk-edit-btn');

    function getChecked() {
        return Array.from(document.querySelectorAll('.row-check:checked')).map(function(c){ return c.value; });
    }
    function refresh() {
        var ids = getChecked();
        bulkBar.style.display = ids.length ? 'flex' : 'none';
        bulkCount.textContent = ids.length + ' selected';
        bulkIds.value = ids.join(',');
        bulkEditBtn.href = ids.length === 1 ? '/products-add?id=' + ids[0] : '#';
        bulkEditBtn.style.opacity = ids.length === 1 ? '1' : '.5';
        bulkEditBtn.title = ids.length === 1 ? '' : 'Select exactly one product to edit';
    }
    if (selectAll) selectAll.addEventListener('change', function(){
        document.querySelectorAll('.row-check').forEach(function(c){ c.checked = selectAll.checked; });
        refresh();
    });
    document.querySelectorAll('.row-check').forEach(function(c){ c.addEventListener('change', refresh); });
})();
</script>

<?php include __DIR__.'/layout_bottom.php'; ?>
