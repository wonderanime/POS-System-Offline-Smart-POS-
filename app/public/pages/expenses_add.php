<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db();
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $title = trim($_POST['title'] ?? ''); $amount = (float)($_POST['amount'] ?? 0);
    if (!$title || $amount<=0) { $error='Title and a valid amount are required.'; }
    else {
        $db->prepare("INSERT INTO expenses (title,category,amount,note) VALUES (?,?,?,?)")
           ->execute([$title, trim($_POST['category']??''), $amount, trim($_POST['note']??'')]);
        header('Location: /expenses'); exit;
    }
}
$activePage='expenses'; $pageTitle='Add Expense';
include __DIR__.'/layout_top.php';
?>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form class="form-card" method="post">
  <div class="form-grid">
    <div class="form-group full"><label class="form-label">Title *</label><input class="form-input" name="title" required placeholder="e.g. Shop Rent"></div>
    <div class="form-group"><label class="form-label">Category</label><input class="form-input" name="category" placeholder="e.g. Rent, Utilities"></div>
    <div class="form-group"><label class="form-label">Amount *</label><input class="form-input" type="number" step="0.01" min="0.01" name="amount" required></div>
    <div class="form-group full"><label class="form-label">Note</label><input class="form-input" name="note"></div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Save Expense</button><a class="btn" href="/expenses">Cancel</a></div>
  </div>
</form>
<?php include __DIR__.'/layout_bottom.php'; ?>
