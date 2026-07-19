<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? 'add';
    if ($action==='add') {
        $name = trim($_POST['name'] ?? '');
        if ($name) $db->prepare("INSERT INTO categories (name) VALUES (?) ON CONFLICT(name) DO NOTHING")->execute([$name]);
    } elseif ($action==='delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE products SET category_id=NULL WHERE category_id=?")->execute([$id]);
        $db->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
    }
    header('Location: /categories'); exit;
}

$categories = $db->query("
  SELECT c.*, COUNT(p.id) product_count
  FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.active=1
  GROUP BY c.id ORDER BY c.name")->fetchAll();

$activePage='categories'; $pageTitle='Categories';
include __DIR__.'/layout_top.php';
?>
<div style="display:grid;grid-template-columns:320px 1fr;gap:18px;align-items:start">
  <form class="form-card" method="post">
    <input type="hidden" name="action" value="add">
    <div class="panel-title" style="margin-bottom:14px">Add Category</div>
    <div class="form-grid">
      <div class="form-group full"><label class="form-label">Category Name</label><input class="form-input" name="name" required></div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Add Category</button></div>
    </div>
  </form>

  <div class="panel">
    <div class="panel-head"><span class="panel-title">All Categories (<?=count($categories)?>)</span></div>
    <table class="dt">
      <thead><tr><th>Name</th><th class="num">Products</th><th></th></tr></thead>
      <tbody>
      <?php if(empty($categories)): ?><tr><td colspan="3" class="empty-row">No categories yet.</td></tr>
      <?php else: foreach($categories as $c): ?>
      <tr>
        <td style="font-weight:600"><?=htmlspecialchars($c['name'])?></td>
        <td class="num"><?=$c['product_count']?></td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?=$c['id']?>">
            <button class="btn btn-xs btn-danger" type="submit" data-confirm="Delete category '<?=htmlspecialchars($c['name'])?>'? Products keep their data, just lose the category tag.">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
