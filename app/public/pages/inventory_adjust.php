<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
$user = require_login();
$db   = get_db();

$id = (int)($_GET['id'] ?? 0);
$s  = $db->prepare("SELECT p.*,c.name cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=?");
$s->execute([$id]); $product = $s->fetch();
if (!$product) { header('Location: /inventory'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $changeQty = abs((float)($_POST['change_qty'] ?? 0));
    $direction = $_POST['direction'] ?? 'add';
    $reason    = trim($_POST['reason'] ?? '');

    if ($changeQty <= 0) {
        $error = 'Enter a quantity greater than zero.';
    } elseif (!$reason) {
        $error = 'Please enter a reason for the adjustment.';
    } else {
        $signed  = $direction === 'remove' ? -$changeQty : $changeQty;
        $oldQty  = (float)$product['stock_qty'];
        $newQty  = max(0, $oldQty + $signed);

        $db->beginTransaction();
        $db->prepare("UPDATE products SET stock_qty=? WHERE id=?")->execute([$newQty, $product['id']]);
        $db->prepare("INSERT INTO stock_adjustments (product_id,change_qty,old_qty,new_qty,reason,created_by) VALUES (?,?,?,?,?,?)")
           ->execute([$product['id'],$signed,$oldQty,$newQty,$reason,$user['id']]);
        audit('STOCK_ADJUSTED','product',$product['id'],
            "Stock adjusted: {$product['name']} $direction $changeQty ({$user['name']}). Reason: $reason",
            ['stock_qty'=>$oldQty], ['stock_qty'=>$newQty], $user['id']);
        $db->commit();

        header('Location: /inventory'); exit;
    }
}

// Recent adjustments for this product
$history = $db->prepare("
    SELECT sa.*, u.name uname FROM stock_adjustments sa
    LEFT JOIN users u ON u.id=sa.created_by
    WHERE sa.product_id=? ORDER BY sa.created_at DESC LIMIT 10");
$history->execute([$id]); $history = $history->fetchAll();

$activePage = 'inventory'; $pageTitle = 'Stock Adjustment';
include __DIR__ . '/layout_top.php';
?>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start">

    <form class="form-card" method="post">
        <div style="margin-bottom:16px">
            <div style="font-size:16px;font-weight:600"><?= htmlspecialchars($product['name']) ?></div>
            <div style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($product['cat_name'] ?? '') ?> &bull; SKU: <?= htmlspecialchars($product['sku'] ?? '—') ?></div>
        </div>

        <div class="metric-card" style="margin-bottom:16px">
            <span class="metric-label">Current Stock</span>
            <span class="metric-val"><?= rtrim(rtrim(number_format($product['stock_qty'],2),'0'),'.') ?> <?= htmlspecialchars($product['unit']) ?></span>
            <span class="metric-sub">Low stock alert at &le; <?= $product['low_stock_threshold'] ?></span>
        </div>

        <div class="form-grid">
            <div class="form-group full">
                <label class="form-label">Direction</label>
                <select class="form-input" name="direction">
                    <option value="add">Add Stock &mdash; received goods / correction up</option>
                    <option value="remove">Remove Stock &mdash; damaged / lost / correction down</option>
                </select>
            </div>
            <div class="form-group full">
                <label class="form-label">Quantity</label>
                <input class="form-input" type="number" step="0.01" min="0.01" name="change_qty" required placeholder="0">
            </div>
            <div class="form-group full">
                <label class="form-label">Reason <span style="color:var(--red)">*</span></label>
                <input class="form-input" name="reason" required
                       placeholder="e.g. Received from supplier, Damaged in storage, Stock count correction">
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save Adjustment</button>
                <a class="btn" href="/inventory">Cancel</a>
            </div>
        </div>
    </form>

    <div class="panel">
        <div class="panel-head"><span class="panel-title">Adjustment History</span></div>
        <table class="dt">
            <thead><tr><th>Date</th><th>Change</th><th>New Qty</th><th>Reason</th><th>By</th></tr></thead>
            <tbody>
            <?php if (empty($history)): ?>
            <tr><td colspan="5" class="empty-row">No adjustments yet.</td></tr>
            <?php else: foreach ($history as $h): ?>
            <tr>
                <td style="font-size:11px;color:var(--text2)"><?= htmlspecialchars($h['created_at']) ?></td>
                <td class="num" style="font-weight:600;color:<?= $h['change_qty']>0?'var(--green)':'var(--red)' ?>">
                    <?= $h['change_qty'] > 0 ? '+' : '' ?><?= number_format($h['change_qty'],2) ?>
                </td>
                <td class="num"><?= number_format($h['new_qty'],2) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($h['reason'] ?? '—') ?></td>
                <td style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($h['uname'] ?? '—') ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php include __DIR__ . '/layout_bottom.php'; ?>
