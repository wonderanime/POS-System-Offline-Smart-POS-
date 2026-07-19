<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db();
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name = trim($_POST['name'] ?? '');
    if (!$name) { $error='Supplier name is required.'; }
    else {
        $db->prepare("INSERT INTO suppliers (name,phone,email,address) VALUES (?,?,?,?)")
           ->execute([$name, trim($_POST['phone']??''), trim($_POST['email']??''), trim($_POST['address']??'')]);
        header('Location: /suppliers'); exit;
    }
}
$activePage='suppliers'; $pageTitle='Add Supplier';
include __DIR__.'/layout_top.php';
?>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form class="form-card" method="post">
  <div class="form-grid">
    <div class="form-group full"><label class="form-label">Supplier Name *</label><input class="form-input" name="name" required></div>
    <div class="form-group"><label class="form-label">Phone</label><input class="form-input" name="phone"></div>
    <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" name="email"></div>
    <div class="form-group full"><label class="form-label">Address</label><textarea class="form-input" name="address" rows="2"></textarea></div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Add Supplier</button><a class="btn" href="/suppliers">Cancel</a></div>
  </div>
</form>
<?php include __DIR__.'/layout_bottom.php'; ?>
