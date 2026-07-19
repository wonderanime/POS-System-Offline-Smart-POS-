<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db();
$sym = get_setting('currency_symbol','Rs');

$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name    = trim($_POST['name']         ?? '');
    $type    = trim($_POST['type']         ?? '');
    $value   = (float)($_POST['value']     ?? 0);
    $minSub  = (float)($_POST['min_subtotal'] ?? 0);
    $buyQty  = (int)($_POST['buy_qty']     ?? 0);
    $getQty  = (int)($_POST['get_qty']     ?? 0);
    $prodId  = $_POST['product_id']        ?? null;

    if (!$name || !$type) { $error='Name and type are required.'; }
    else {
        $db->prepare("INSERT INTO discounts (name,type,value,min_subtotal,buy_qty,get_qty,product_id) VALUES (?,?,?,?,?,?,?)")
           ->execute([$name,$type,$value,$minSub?:null,$buyQty?:null,$getQty?:null,$prodId?:null]);
        header('Location: /discounts'); exit;
    }
}

$products = $db->query("SELECT id,name FROM products WHERE active=1 ORDER BY name")->fetchAll();
$activePage='discounts'; $pageTitle='Add Discount';
include __DIR__.'/layout_top.php';
?>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form class="form-card" method="post" id="disc-form">
    <div class="form-grid">
        <div class="form-group full">
            <label class="form-label">Discount Name *</label>
            <input class="form-input" name="name" required placeholder="e.g. Summer Sale 10%">
        </div>
        <div class="form-group full">
            <label class="form-label">Discount Type *</label>
            <select class="form-input" name="type" id="disc-type" onchange="updateFields()">
                <option value="">— Select type —</option>
                <option value="flat">Flat Amount (e.g. Rs. 100 off)</option>
                <option value="percent">Percentage (e.g. 10% off)</option>
                <option value="bogo">Buy X Get Y Free</option>
                <option value="combo">Combo Deal (fixed discount on product)</option>
            </select>
        </div>
        <div class="form-group" id="field-value">
            <label class="form-label" id="value-label">Discount Value</label>
            <input class="form-input" type="number" step="0.01" min="0" name="value">
        </div>
        <div class="form-group">
            <label class="form-label">Minimum Order Amount (<?=$sym?>, optional)</label>
            <input class="form-input" type="number" step="0.01" min="0" name="min_subtotal" placeholder="0 = no minimum">
        </div>
        <div class="form-group" id="field-buy" style="display:none">
            <label class="form-label">Buy Quantity</label>
            <input class="form-input" type="number" min="1" name="buy_qty" value="2">
        </div>
        <div class="form-group" id="field-get" style="display:none">
            <label class="form-label">Get Quantity Free</label>
            <input class="form-input" type="number" min="1" name="get_qty" value="1">
        </div>
        <div class="form-group full" id="field-product" style="display:none">
            <label class="form-label">Apply to Product (required for BOGO and Combo)</label>
            <select class="form-input" name="product_id">
                <option value="">— Any product —</option>
                <?php foreach($products as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['name'])?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save Discount</button>
            <a class="btn" href="/discounts">Cancel</a>
        </div>
    </div>
</form>
<script>
function updateFields() {
    var t = document.getElementById('disc-type').value;
    document.getElementById('field-buy').style.display     = (t==='bogo'||t==='combo') ? '' : 'none';
    document.getElementById('field-get').style.display     = t==='bogo' ? '' : 'none';
    document.getElementById('field-product').style.display = (t==='bogo'||t==='combo') ? '' : 'none';
    var vl = document.getElementById('value-label');
    if (t==='flat')    vl.textContent='Flat Discount Amount (<?=$sym?>)';
    else if(t==='percent') vl.textContent='Discount Percentage (%)';
    else if(t==='combo')   vl.textContent='Discount Amount Off (<?=$sym?>)';
    else vl.textContent='Value';
}
</script>
<?php include __DIR__.'/layout_bottom.php'; ?>
