<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db();

$success=''; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    if ($action==='store') {
        foreach(['shop_name','shop_address','shop_phone','currency_symbol'] as $f) set_setting($f, trim($_POST[$f]??''));
        set_setting('tax_rate_default', (string)(float)($_POST['tax_rate_default']??0));
        $success='Store information saved.';
    }
    elseif ($action==='logo') {
        if (!empty($_FILES['logo']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext,['jpg','jpeg','png','gif','webp'])) {
                $fname = 'logo_'.time().'.jpg';
                if (resize_and_save_image($_FILES['logo']['tmp_name'], $ext, UPLOADS_DIR.'/'.$fname, 200)) {
                    set_setting('shop_logo', '/assets/uploads/'.$fname);
                    $success='Logo updated.';
                }
            } else { $error='Logo must be jpg, png, gif, or webp.'; }
        }
    }
    elseif ($action==='pos_prefs') {
        set_setting('receipt_size', trim($_POST['receipt_size']??'80mm'));
        $success='POS settings saved.';
    }
    elseif ($action==='pin') {
        $cur=trim($_POST['current_pin']??''); $new1=trim($_POST['new_pin']??''); $new2=trim($_POST['confirm_pin']??'');
        $u = $db->query("SELECT * FROM users LIMIT 1")->fetch();
        if (!password_verify($cur,$u['pin_hash'])) { $error='Current PIN is incorrect.'; }
        elseif (strlen($new1)!==4 || !ctype_digit($new1)) { $error='New PIN must be exactly 4 digits.'; }
        elseif ($new1!==$new2) { $error='New PINs do not match.'; }
        else { $db->prepare("UPDATE users SET pin_hash=? WHERE id=?")->execute([password_hash($new1,PASSWORD_DEFAULT),$u['id']]); $success='PIN updated.'; }
    }
}

$s = fn($k) => get_setting($k,'');
$activePage='settings'; $pageTitle='Settings';
include __DIR__.'/layout_top.php';
?>
<?php if($success): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>

<div style="display:flex;flex-direction:column;gap:18px;max-width:720px">

  <form class="form-card" method="post">
    <input type="hidden" name="action" value="store">
    <div class="panel-title" style="margin-bottom:14px">Store Information</div>
    <div class="form-grid">
      <div class="form-group full"><label class="form-label">Shop Name</label><input class="form-input" name="shop_name" value="<?=htmlspecialchars($s('shop_name'))?>"></div>
      <div class="form-group full"><label class="form-label">Address</label><textarea class="form-input" name="shop_address" rows="2"><?=htmlspecialchars($s('shop_address'))?></textarea></div>
      <div class="form-group"><label class="form-label">Phone</label><input class="form-input" name="shop_phone" value="<?=htmlspecialchars($s('shop_phone'))?>"></div>
      <div class="form-group"><label class="form-label">Currency Symbol</label><input class="form-input" name="currency_symbol" value="<?=htmlspecialchars($s('currency_symbol'))?>"></div>
      <div class="form-group full"><label class="form-label">Default Tax Rate (%)</label><input class="form-input" type="number" step="0.01" min="0" name="tax_rate_default" value="<?=htmlspecialchars($s('tax_rate_default'))?>"></div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Save Changes</button></div>
    </div>
  </form>

  <form class="form-card" method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="logo">
    <div class="panel-title" style="margin-bottom:10px">Shop Logo</div>
    <div style="display:flex;align-items:center;gap:16px">
      <?php if($s('shop_logo')): ?><img src="<?=htmlspecialchars($s('shop_logo'))?>" style="width:60px;height:60px;object-fit:cover;border-radius:14px"><?php else: ?><div class="no-img" style="width:60px;height:60px"><?=htmlspecialchars(strtoupper(substr($s('shop_name')?:'S',0,1)))?></div><?php endif; ?>
      <input type="file" name="logo" accept="image/*" style="flex:1">
      <button class="btn btn-primary" type="submit">Upload</button>
    </div>
  </form>

  <form class="form-card" method="post">
    <input type="hidden" name="action" value="pos_prefs">
    <div class="panel-title" style="margin-bottom:14px">POS Settings</div>
    <div class="form-grid">
      <div class="form-group"><label class="form-label">Receipt Size</label>
        <select class="form-input" name="receipt_size">
          <option value="58mm" <?=$s('receipt_size')==='58mm'?'selected':''?>>58mm Thermal</option>
          <option value="80mm" <?=$s('receipt_size')==='80mm'?'selected':''?>>80mm Thermal</option>
          <option value="a4"   <?=$s('receipt_size')==='a4'?'selected':''?>>A4 Paper</option>
        </select>
      </div>
      <div class="form-group full" style="background:var(--surface2);border-radius:10px;padding:12px 14px;font-size:12px;color:var(--text2)">
        Keyboard shortcuts (always on): <strong>F2</strong> Search &middot; <strong>F3</strong> Complete Sale &middot; <strong>F4</strong> Hold &middot; <strong>Esc</strong> Cancel
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Save</button></div>
    </div>
  </form>

  <form class="form-card" method="post" style="max-width:400px">
    <input type="hidden" name="action" value="pin">
    <div class="panel-title" style="margin-bottom:14px">Change PIN</div>
    <div class="form-grid">
      <div class="form-group full"><label class="form-label">Current PIN</label><input class="form-input" type="password" name="current_pin" maxlength="4" pattern="\d{4}" required></div>
      <div class="form-group full"><label class="form-label">New PIN</label><input class="form-input" type="password" name="new_pin" maxlength="4" pattern="\d{4}" required></div>
      <div class="form-group full"><label class="form-label">Confirm New PIN</label><input class="form-input" type="password" name="confirm_pin" maxlength="4" pattern="\d{4}" required></div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Change PIN</button></div>
    </div>
  </form>

</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
