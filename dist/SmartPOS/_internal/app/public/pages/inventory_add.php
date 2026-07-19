<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
$user = require_login();
$db   = get_db();

$id      = (int)($_GET['id'] ?? 0);
$product = null;
if ($id) {
    $s = $db->prepare("SELECT * FROM products WHERE id=?");
    $s->execute([$id]);
    $product = $s->fetch();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $sku      = trim($_POST['sku']      ?? '');
    $barcode  = trim($_POST['barcode']  ?? '');
    $catId    = $_POST['category_id']   ?? '';
    $newCat   = trim($_POST['new_cat']  ?? '');
    $price    = (float)($_POST['price']     ?? 0);
    $cost     = (float)($_POST['cost_price']?? 0);
    $openQty  = (float)($_POST['stock_qty'] ?? 0);
    $lowThres = (float)($_POST['low_stock_threshold'] ?? 5);
    $unit     = trim($_POST['unit']     ?? 'pcs');
    $taxRate  = (float)($_POST['tax_rate']  ?? 0);
    $imgUrl   = trim($_POST['image_url']?? '');
    $active   = isset($_POST['active']) ? 1 : 0;

    if (!$name) {
        $error = 'Product name is required.';
    } else {
        // New category inline
        if ($newCat !== '') {
            $cs = $db->prepare("INSERT INTO categories (name) VALUES (?) ON CONFLICT(name) DO NOTHING");
            $cs->execute([$newCat]);
            $cl = $db->prepare("SELECT id FROM categories WHERE name=?");
            $cl->execute([$newCat]);
            $catId = $cl->fetch()['id'];
        }

        // Handle image upload (file takes priority if both given)
        $imagePath = $product['image_path'] ?? '';
        if (!empty($_FILES['image']['tmp_name'])) {
            $ext  = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext,$allowed)) {
                $error = 'Image must be jpg, png, gif or webp.';
            } else {
                $fname = 'prod_' . time() . '_' . rand(100,999) . '.jpg';
                $dest  = UPLOADS_DIR . '/' . $fname;
                if (resize_and_save_image($_FILES['image']['tmp_name'], $ext, $dest, 500)) {
                    if ($imagePath && file_exists(UPLOADS_DIR . '/' . basename($imagePath))) {
                        @unlink(UPLOADS_DIR . '/' . basename($imagePath));
                    }
                    $imagePath = $fname;
                    $imgUrl = ''; // uploaded file replaces any URL
                } else {
                    $error = 'Could not process the uploaded image.';
                }
            }
        } elseif ($imgUrl && $imgUrl !== ($product['image_url'] ?? '')) {
            // New/changed URL — download it and compress to a consistent local size,
            // same as an upload, so it displays uniformly instead of at source size.
            $downloaded = @file_get_contents($imgUrl);
            if ($downloaded === false) {
                $error = 'Could not download the image from that URL. Check the link is correct and publicly accessible.';
            } else {
                $tmpFile = tempnam(sys_get_temp_dir(), 'posimg');
                file_put_contents($tmpFile, $downloaded);
                $info = @getimagesize($tmpFile);
                $mimeToExt = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
                $urlExt = $info && isset($mimeToExt[$info['mime']]) ? $mimeToExt[$info['mime']] : '';
                if (!$urlExt) {
                    $error = 'That link does not appear to be a valid image (jpg, png, gif, or webp).';
                    @unlink($tmpFile);
                } else {
                    $fname = 'prod_' . time() . '_' . rand(100,999) . '.jpg';
                    $dest  = UPLOADS_DIR . '/' . $fname;
                    if (resize_and_save_image($tmpFile, $urlExt, $dest, 500)) {
                        if ($imagePath && file_exists(UPLOADS_DIR . '/' . basename($imagePath))) {
                            @unlink(UPLOADS_DIR . '/' . basename($imagePath));
                        }
                        $imagePath = $fname;
                        $imgUrl = ''; // now stored locally, drop the external link
                    } else {
                        $error = 'Could not process the image from that URL.';
                    }
                    @unlink($tmpFile);
                }
            }
        }

        if (!$error) {
            if ($product) {
                $db->prepare("UPDATE products SET name=?,sku=?,barcode=?,category_id=?,price=?,cost_price=?,low_stock_threshold=?,unit=?,tax_rate=?,image_path=?,image_url=?,active=? WHERE id=?")
                   ->execute([$name,$sku?:null,$barcode?:null,$catId?:null,$price,$cost,$lowThres,$unit,$taxRate,$imagePath,$imgUrl,$active,$product['id']]);

                audit('PRODUCT_EDITED','product',$product['id'],"Edited product: $name",
                    ['price'=>$product['price'],'stock'=>$product['stock_qty']],
                    ['price'=>$price],
                    $user['id']);
            } else {
                $db->prepare("INSERT INTO products (name,sku,barcode,category_id,price,cost_price,stock_qty,low_stock_threshold,unit,tax_rate,image_path,image_url,active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$name,$sku?:null,$barcode?:null,$catId?:null,$price,$cost,$openQty,$lowThres,$unit,$taxRate,$imagePath,$imgUrl,$active]);

                $newId = (int)$db->lastInsertId();
                audit('PRODUCT_CREATED','product',$newId,"Created product: $name",null,['name'=>$name,'price'=>$price],$user['id']);
            }
            header('Location: /inventory'); exit;
        }
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$activePage = 'inventory';
$pageTitle  = $product ? 'Edit Product' : 'Add Product';
include __DIR__ . '/layout_top.php';
?>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form class="form-card" method="post" enctype="multipart/form-data" style="max-width:760px">
    <div class="form-grid">

        <div class="form-group full">
            <label class="form-label">Product Name <span style="color:var(--red)">*</span></label>
            <input class="form-input" name="name" required
                   value="<?= htmlspecialchars($product['name'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label">SKU</label>
            <input class="form-input" name="sku" placeholder="e.g. BV-001"
                   value="<?= htmlspecialchars($product['sku'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Barcode</label>
            <input class="form-input" name="barcode" placeholder="Scan or type barcode"
                   value="<?= htmlspecialchars($product['barcode'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Category</label>
            <select class="form-input" name="category_id">
                <option value="">— No category —</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= isset($product) && $product['category_id'] == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Or create new category</label>
            <input class="form-input" name="new_cat" placeholder="Type new category name">
        </div>

        <div class="form-group">
            <label class="form-label">Selling Price (<?= get_setting('currency_symbol','Rs') ?>)</label>
            <input class="form-input" type="number" step="0.01" min="0" name="price" id="price-input" required
                   value="<?= htmlspecialchars($product['price'] ?? '0') ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Cost Price (<?= get_setting('currency_symbol','Rs') ?>)</label>
            <input class="form-input" type="number" step="0.01" min="0" name="cost_price" id="cost-input"
                   value="<?= htmlspecialchars($product['cost_price'] ?? '0') ?>">
        </div>

        <div class="form-group full" id="margin-display" style="background:var(--surface2);border-radius:8px;padding:8px 12px;font-size:12.5px;color:var(--text2)">
            Profit margin: <strong id="margin-value" style="color:var(--accent)">—</strong>
        </div>

        <div class="form-group">
            <label class="form-label">Tax Rate (%)</label>
            <input class="form-input" type="number" step="0.01" min="0" max="100" name="tax_rate"
                   placeholder="0 = no tax"
                   value="<?= htmlspecialchars($product['tax_rate'] ?? '0') ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Unit</label>
            <select class="form-input" name="unit">
                <?php foreach (['pcs','kg','g','ltr','ml','box','dozen','pair'] as $u): ?>
                <option value="<?= $u ?>" <?= isset($product) && $product['unit']===$u ? 'selected' : '' ?>>
                    <?= $u ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!$product): ?>
        <div class="form-group">
            <label class="form-label">Opening Stock Quantity</label>
            <input class="form-input" type="number" step="0.01" min="0" name="stock_qty" value="0">
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Low Stock Alert Threshold</label>
            <input class="form-input" type="number" step="0.01" min="0" name="low_stock_threshold"
                   value="<?= htmlspecialchars($product['low_stock_threshold'] ?? '5') ?>">
        </div>

        <!-- Image upload -->
        <div class="form-group">
            <label class="form-label">Product Image (upload file)</label>
            <input class="form-input" type="file" name="image" accept="image/*" style="padding:6px">
            <?php if (!empty($product['image_path'])): ?>
            <img src="/assets/uploads/<?= htmlspecialchars(basename($product['image_path'])) ?>"
                 style="width:60px;height:60px;object-fit:cover;border-radius:6px;margin-top:6px"
                 onerror="this.style.display='none'">
            <?php endif; ?>
        </div>

        <!-- Image URL -->
        <div class="form-group">
            <label class="form-label">Or Paste an Image URL</label>
            <input class="form-input" type="url" name="image_url"
                   placeholder="https://example.com/image.jpg">
            <span style="font-size:11px;color:var(--text3)">Downloaded and resized automatically — same as an upload.</span>
        </div>

        <?php if ($product): ?>
        <div class="form-group">
            <label class="form-label">Active</label>
            <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
                <label class="toggle">
                    <input type="checkbox" name="active" value="1" <?= ($product['active']??1) ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
                <span style="font-size:12.5px;color:var(--text2)">Show on POS screen</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                <?= $product ? 'Save Changes' : 'Add Product' ?>
            </button>
            <?php if ($product): ?>
            <a class="btn" href="/inventory-adjust?id=<?= $product['id'] ?>">Adjust Stock</a>
            <?php endif; ?>
            <a class="btn" href="/inventory">Cancel</a>
        </div>

    </div>
</form>

<?php if ($product): ?>
<p style="margin-top:12px;font-size:12px;color:var(--text3)">
    Current stock: <strong><?= rtrim(rtrim(number_format($product['stock_qty'],2),'0'),'.') ?> <?= htmlspecialchars($product['unit']) ?></strong>
    — use <a href="/inventory-adjust?id=<?= $product['id'] ?>">Stock Adjustment</a> to change quantity.
</p>
<?php endif; ?>

<script>
(function(){
    var priceIn  = document.getElementById('price-input');
    var costIn   = document.getElementById('cost-input');
    var marginEl = document.getElementById('margin-value');
    function update() {
        var price = parseFloat(priceIn.value) || 0;
        var cost  = parseFloat(costIn.value)  || 0;
        if (price <= 0) { marginEl.textContent = '—'; return; }
        var profit = price - cost;
        var pct = (profit / price) * 100;
        marginEl.textContent = <?= json_encode(get_setting('currency_symbol','Rs')) ?> + ' ' +
            profit.toFixed(2) + '  (' + pct.toFixed(1) + '% margin)';
        marginEl.style.color = pct < 10 ? 'var(--red)' : pct < 25 ? 'var(--yellow)' : 'var(--accent)';
    }
    priceIn.addEventListener('input', update);
    costIn.addEventListener('input', update);
    update();
})();
</script>

<?php include __DIR__ . '/layout_bottom.php'; ?>
