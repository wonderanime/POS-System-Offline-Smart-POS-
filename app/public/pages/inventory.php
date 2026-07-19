<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login();
require_once dirname(__DIR__,2) . '/includes/pagination.php';

$db  = get_db();
$sym = get_setting('currency_symbol','Rs');

$search  = trim($_GET['q']   ?? '');
$catId   = trim($_GET['cat'] ?? '');
$stock   = trim($_GET['stock']?? '');

$where = ['p.active=1']; $params = [];
if ($search) { $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($catId)  { $where[] = 'p.category_id=?'; $params[] = $catId; }
if ($stock === 'low')  $where[] = 'p.stock_qty <= p.low_stock_threshold AND p.stock_qty > 0';
if ($stock === 'out')  $where[] = 'p.stock_qty <= 0';
if ($stock === 'ok')   $where[] = 'p.stock_qty > p.low_stock_threshold';
$wsql = implode(' AND ', $where);

$cstmt = $db->prepare("SELECT COUNT(*) c FROM products p WHERE $wsql");
$cstmt->execute($params); $total = (int)$cstmt->fetch()['c'];

[$page,$perPage] = paginate_params();
$totalPages = max(1,(int)ceil($total/$perPage));
$page  = min($page,$totalPages);
$offset= ($page-1)*$perPage;

$pstmt = $db->prepare("
    SELECT p.*, c.name cat_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE $wsql
    ORDER BY p.name
    LIMIT ? OFFSET ?");
foreach ($params as $i => $v) $pstmt->bindValue($i+1, $v);
$pstmt->bindValue(count($params)+1, $perPage, PDO::PARAM_INT);
$pstmt->bindValue(count($params)+2, $offset,  PDO::PARAM_INT);
$pstmt->execute();
$products = $pstmt->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$stockVal  = $db->query("SELECT COALESCE(SUM(stock_qty*cost_price),0) v FROM products WHERE active=1")->fetch()['v'];
$stockRet  = $db->query("SELECT COALESCE(SUM(stock_qty*price),0) v FROM products WHERE active=1")->fetch()['v'];
$lowCount  = $db->query("SELECT COUNT(*) c FROM products WHERE stock_qty<=low_stock_threshold AND stock_qty>0 AND active=1")->fetch()['c'];
$outCount  = $db->query("SELECT COUNT(*) c FROM products WHERE stock_qty<=0 AND active=1")->fetch()['c'];

$activePage = 'inventory'; $pageTitle = 'Inventory';
include __DIR__ . '/layout_top.php';
?>

<div class="metric-grid" style="margin-bottom:16px">
    <div class="metric-card">
        <span class="metric-label">Total Products</span>
        <span class="metric-val"><?= $total ?></span>
    </div>
    <div class="metric-card">
        <span class="metric-label">Stock Value (Cost)</span>
        <span class="metric-val"><?= $sym ?> <?= money($stockVal) ?></span>
    </div>
    <div class="metric-card">
        <span class="metric-label">Stock Value (Retail)</span>
        <span class="metric-val blue"><?= $sym ?> <?= money($stockRet) ?></span>
    </div>
    <div class="metric-card">
        <span class="metric-label">Low Stock</span>
        <span class="metric-val yellow"><?= $lowCount ?></span>
    </div>
    <div class="metric-card">
        <span class="metric-label">Out of Stock</span>
        <span class="metric-val red"><?= $outCount ?></span>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <span class="panel-title">Products</span>
        <a href="/inventory-add" class="btn btn-primary">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add Product
        </a>
    </div>

    <form class="filter-bar" method="get" action="/inventory" style="padding:12px 16px">
        <input type="text" name="q" placeholder="Name / SKU / Barcode" value="<?= htmlspecialchars($search) ?>">
        <select name="cat">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $catId==(string)$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="stock">
            <option value="">All Stock</option>
            <option value="ok"  <?= $stock==='ok' ?'selected':''?>>In Stock</option>
            <option value="low" <?= $stock==='low'?'selected':''?>>Low Stock</option>
            <option value="out" <?= $stock==='out'?'selected':''?>>Out of Stock</option>
        </select>
        <button class="btn btn-primary btn-sm" type="submit">Filter</button>
        <a class="btn btn-sm" href="/inventory">Reset</a>
    </form>

    <div class="page-toolbar" id="bulk-bar" style="display:none;padding:0 16px 12px">
        <span id="bulk-count" style="font-size:12.5px;color:var(--text2)"></span>
        <div style="display:flex;gap:8px">
            <a class="btn btn-sm" id="bulk-edit-btn" href="#">Edit Selected</a>
            <form method="post" action="/inventory-bulk-delete" id="bulk-delete-form" style="display:inline">
                <input type="hidden" name="ids" id="bulk-ids">
                <button class="btn btn-sm btn-danger" type="submit"
                        data-confirm="Delete the selected products? This cannot be undone.">Delete Selected</button>
            </form>
        </div>
    </div>

    <table class="dt">
        <thead><tr>
            <th style="width:30px"><input type="checkbox" id="select-all"></th>
            <th>Product</th><th>SKU</th><th>Category</th>
            <th class="num">Cost</th><th class="num">Price</th><th class="num">Tax</th>
            <th class="num">Stock</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($products)): ?>
        <tr><td colspan="10" class="empty-row">No products found. <a href="/inventory-add">Add your first product</a>.</td></tr>
        <?php else: foreach ($products as $p): ?>
        <tr>
            <td><input type="checkbox" class="row-check" value="<?= $p['id'] ?>"></td>
            <td>
                <?php if ($p['image_path'] || $p['image_url']): ?>
                <img src="<?= $p['image_path'] ? '/assets/uploads/'.htmlspecialchars(basename($p['image_path'])) : htmlspecialchars($p['image_url']) ?>"
                     style="width:28px;height:28px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:6px"
                     onerror="this.style.display='none'">
                <?php endif; ?>
                <?= htmlspecialchars($p['name']) ?>
            </td>
            <td class="dim"><?= htmlspecialchars($p['sku'] ?? '—') ?></td>
            <td class="dim"><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
            <td class="num"><?= $sym ?> <?= number_format($p['cost_price'],0) ?></td>
            <td class="num"><?= $sym ?> <?= number_format($p['price'],0) ?></td>
            <td class="num"><?= $p['tax_rate'] > 0 ? $p['tax_rate'].'%' : '—' ?></td>
            <td class="num"><?= rtrim(rtrim(number_format($p['stock_qty'],2),'0'),'.') ?> <?= htmlspecialchars($p['unit']) ?></td>
            <td>
                <?php if ($p['stock_qty'] <= 0): ?>
                    <span class="badge badge-red">Out of Stock</span>
                <?php elseif ($p['stock_qty'] <= $p['low_stock_threshold']): ?>
                    <span class="badge badge-yellow">Low Stock</span>
                <?php else: ?>
                    <span class="badge badge-green">In Stock</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="actions">
                    <a class="act-btn" href="/inventory-add?id=<?= $p['id'] ?>" title="Edit">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                    </a>
                    <a class="act-btn" href="/inventory-adjust?id=<?= $p['id'] ?>" title="Adjust stock">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/></svg>
                    </a>
                    <form method="post" action="/inventory-delete" style="display:inline">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button class="act-btn danger" type="submit" title="Delete"
                                data-confirm="Delete '<?= htmlspecialchars($p['name']) ?>'? This cannot be undone.">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                        </button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?= render_pagination('/inventory',$page,$totalPages,$perPage,$total,['q'=>$search,'cat'=>$catId,'stock'=>$stock]) ?>
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
        bulkEditBtn.href = ids.length === 1 ? '/inventory-add?id=' + ids[0] : '#';
    }
    if (selectAll) selectAll.addEventListener('change', function(){
        document.querySelectorAll('.row-check').forEach(function(c){ c.checked = selectAll.checked; });
        refresh();
    });
    document.querySelectorAll('.row-check').forEach(function(c){ c.addEventListener('change', refresh); });
})();
</script>

<?php include __DIR__ . '/layout_bottom.php'; ?>
