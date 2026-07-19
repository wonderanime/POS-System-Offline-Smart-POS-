<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login();
$db = get_db();

$id = (int)($_GET['id'] ?? 0);
$product = null;
if ($id) { $s=$db->prepare("SELECT * FROM products WHERE id=?"); $s->execute([$id]); $product=$s->fetch(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $sku      = trim($_POST['sku'] ?? '');
    $barcode  = trim($_POST['barcode'] ?? '');
    $catId    = $_POST['category_id'] ?? '';
    $newCat   = trim($_POST['new_cat'] ?? '');
    $brandId  = $_POST['brand_id'] ?? '';
    $purPrice = (float)($_POST['purchase_price'] ?? 0);
    $salePrice= (float)($_POST['sale_price'] ?? 0);
    $taxRate  = (float)($_POST['tax_rate'] ?? 0);
    $unit     = trim($_POST['unit'] ?? 'pcs');
    $openQty  = (float)($_POST['stock_qty'] ?? 0);
    $lowAlert = (float)($_POST['low_stock_alert'] ?? 5);
    $imgUrl   = trim($_POST['image_url'] ?? '');

    if (!$name) { $error = 'Product name is required.'; }
    else {
        if ($newCat !== '') {
            $db->prepare("INSERT INTO categories (name) VALUES (?) ON CONFLICT(name) DO NOTHING")->execute([$newCat]);
            $cl = $db->prepare("SELECT id FROM categories WHERE name=?"); $cl->execute([$newCat]); $catId = $cl->fetch()['id'];
        }

        $imagePath = $product['image_path'] ?? '';
        if (!empty($_FILES['image']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $error = 'Image must be jpg, png, gif or webp.';
            } else {
                $fname = 'prod_' . time() . '_' . rand(100,999) . '.jpg';
                if (resize_and_save_image($_FILES['image']['tmp_name'], $ext, UPLOADS_DIR.'/'.$fname, 400)) {
                    if ($imagePath && file_exists(UPLOADS_DIR.'/'.basename($imagePath))) @unlink(UPLOADS_DIR.'/'.basename($imagePath));
                    $imagePath = $fname;
                } else { $error = 'Could not process the uploaded image.'; }
            }
        } elseif ($imgUrl) {
            $fname = 'prod_' . time() . '_' . rand(100,999) . '.jpg';
            if (download_and_resize_image($imgUrl, UPLOADS_DIR.'/'.$fname, 400)) {
                if ($imagePath && file_exists(UPLOADS_DIR.'/'.basename($imagePath))) @unlink(UPLOADS_DIR.'/'.basename($imagePath));
                $imagePath = $fname;
            } else { $error = 'Could not download image from that URL — check the link is correct and publicly accessible.'; }
        }

        if (!$error) {
            if ($product) {
                $db->prepare("UPDATE products SET name=?,sku=?,barcode=?,category_id=?,brand_id=?,purchase_price=?,sale_price=?,tax_rate=?,unit=?,low_stock_alert=?,image_path=? WHERE id=?")
                   ->execute([$name,$sku?:null,$barcode?:null,$catId?:null,$brandId?:null,$purPrice,$salePrice,$taxRate,$unit,$lowAlert,$imagePath,$product['id']]);
            } else {
                $db->prepare("INSERT INTO products (name,sku,barcode,category_id,brand_id,purchase_price,sale_price,tax_rate,unit,stock_qty,low_stock_alert,image_path) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$name,$sku?:null,$barcode?:null,$catId?:null,$brandId?:null,$purPrice,$salePrice,$taxRate,$unit,$openQty,$lowAlert,$imagePath]);
            }
            header('Location: /products'); exit;
        }
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$brands = $db->query("SELECT * FROM brands ORDER BY name")->fetchAll();

$activePage='products'; $pageTitle = $product ? 'Edit Product' : 'Add Product';
include __DIR__.'/layout_top.php';
?>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>

<form class="form-card" method="post" enctype="multipart/form-data">
  <div class="form-grid">
    <div class="form-group full"><label class="form-label">Product Name *</label><input class="form-input" name="name" required value="<?=htmlspecialchars($product['name']??'')?>"></div>
    <div class="form-group"><label class="form-label">SKU</label><input class="form-input" name="sku" value="<?=htmlspecialchars($product['sku']??'')?>"></div>
    <div class="form-group"><label class="form-label">Barcode</label><input class="form-input" name="barcode" value="<?=htmlspecialchars($product['barcode']??'')?>"></div>
    <div class="form-group">
      <label class="form-label">Category</label>
      <select class="form-input" name="category_id">
        <option value="">— No category —</option>
        <?php foreach($categories as $c): ?><option value="<?=$c['id']?>" <?=isset($product)&&$product['category_id']==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Or new category</label><input class="form-input" name="new_cat" placeholder="Type to create"></div>
    <div class="form-group">
      <label class="form-label">Brand</label>
      <select class="form-input" name="brand_id">
        <option value="">— No brand —</option>
        <?php foreach($brands as $b): ?><option value="<?=$b['id']?>" <?=isset($product)&&$product['brand_id']==$b['id']?'selected':''?>><?=htmlspecialchars($b['name'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Unit</label>
      <select class="form-input" name="unit">
        <?php foreach(['pcs','kg','g','ltr','ml','box','dozen'] as $u): ?><option value="<?=$u?>" <?=isset($product)&&$product['unit']===$u?'selected':''?>><?=$u?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Purchase Price</label><input class="form-input" type="number" step="0.01" min="0" name="purchase_price" value="<?=htmlspecialchars($product['purchase_price']??'0')?>"></div>
    <div class="form-group"><label class="form-label">Sale Price</label><input class="form-input" type="number" step="0.01" min="0" name="sale_price" required value="<?=htmlspecialchars($product['sale_price']??'0')?>"></div>
    <div class="form-group"><label class="form-label">Tax Rate (%)</label><input class="form-input" type="number" step="0.01" min="0" max="100" name="tax_rate" value="<?=htmlspecialchars($product['tax_rate']??'0')?>"></div>
    <?php if(!$product): ?>
    <div class="form-group"><label class="form-label">Opening Stock</label><input class="form-input" type="number" step="0.01" min="0" name="stock_qty" value="0"></div>
    <?php endif; ?>
    <div class="form-group"><label class="form-label">Low Stock Alert</label><input class="form-input" type="number" step="0.01" min="0" name="low_stock_alert" value="<?=htmlspecialchars($product['low_stock_alert']??'5')?>"></div>

    <div class="form-group"><label class="form-label">Upload Image</label><input class="form-input" type="file" name="image" accept="image/*" style="padding:8px">
      <?php if(!empty($product['image_path'])): ?><img src="/assets/uploads/<?=htmlspecialchars(basename($product['image_path']))?>" style="width:60px;height:60px;object-fit:cover;border-radius:10px;margin-top:6px"><?php endif; ?>
    </div>
    <div class="form-group"><label class="form-label">Or Paste Image URL</label><input class="form-input" type="url" name="image_url" placeholder="https://example.com/image.jpg">
      <span style="font-size:11px;color:var(--text3)">Downloaded and resized automatically.</span>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?=$product?'Save Changes':'Add Product'?></button>
      <?php if($product): ?><a class="btn" href="/stock-adjust?id=<?=$product['id']?>">Adjust Stock</a><?php endif; ?>
      <a class="btn" href="/products">Cancel</a>
    </div>
  </div>
</form>
<?php include __DIR__.'/layout_bottom.php'; ?>
