<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? 'add';
    if ($action==='add') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $logoPath = null;
            if (!empty($_FILES['logo']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                    $fname = 'brand_' . time() . '_' . rand(100,999) . '.jpg';
                    if (resize_and_save_image($_FILES['logo']['tmp_name'], $ext, UPLOADS_DIR.'/'.$fname, 150)) $logoPath = $fname;
                }
            }
            $db->prepare("INSERT INTO brands (name,logo_path) VALUES (?,?) ON CONFLICT(name) DO NOTHING")->execute([$name,$logoPath]);
        }
    } elseif ($action==='delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE products SET brand_id=NULL WHERE brand_id=?")->execute([$id]);
        $db->prepare("DELETE FROM brands WHERE id=?")->execute([$id]);
    }
    header('Location: /brands'); exit;
}

$brands = $db->query("
  SELECT b.*, COUNT(p.id) product_count
  FROM brands b LEFT JOIN products p ON p.brand_id=b.id AND p.active=1
  GROUP BY b.id ORDER BY b.name")->fetchAll();

$activePage='brands'; $pageTitle='Brands';
include __DIR__.'/layout_top.php';
?>
<div style="display:grid;grid-template-columns:320px 1fr;gap:18px;align-items:start">
  <form class="form-card" method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add">
    <div class="panel-title" style="margin-bottom:14px">Add Brand</div>
    <div class="form-grid">
      <div class="form-group full"><label class="form-label">Brand Name</label><input class="form-input" name="name" required></div>
      <div class="form-group full"><label class="form-label">Logo (optional)</label><input class="form-input" type="file" name="logo" accept="image/*" style="padding:8px"></div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Add Brand</button></div>
    </div>
  </form>

  <div class="panel">
    <div class="panel-head"><span class="panel-title">All Brands (<?=count($brands)?>)</span></div>
    <table class="dt">
      <thead><tr><th>Logo</th><th>Name</th><th class="num">Products</th><th></th></tr></thead>
      <tbody>
      <?php if(empty($brands)): ?><tr><td colspan="4" class="empty-row">No brands yet.</td></tr>
      <?php else: foreach($brands as $b): ?>
      <tr>
        <td><?php if($b['logo_path']): ?><img src="/assets/uploads/<?=htmlspecialchars(basename($b['logo_path']))?>" style="width:28px;height:28px;object-fit:cover;border-radius:8px"><?php else: ?><div class="no-img" style="width:28px;height:28px;font-size:12px;border-radius:8px"><?=htmlspecialchars(strtoupper(substr($b['name'],0,1)))?></div><?php endif; ?></td>
        <td style="font-weight:600"><?=htmlspecialchars($b['name'])?></td>
        <td class="num"><?=$b['product_count']?></td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?=$b['id']?>">
            <button class="btn btn-xs btn-danger" type="submit" data-confirm="Delete brand '<?=htmlspecialchars($b['name'])?>'?">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
