<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
$user = require_login();
$db   = get_db();

$id   = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM sales WHERE id=?");
$stmt->execute([$id]); $sale = $stmt->fetch();

if (!$sale || $sale['status'] === 'voided') {
    header('Location: /sales-history'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newNote  = trim($_POST['note']  ?? '');
    $newTrans = trim($_POST['transaction_id'] ?? '');

    $old = ['note' => $sale['note'], 'transaction_id' => $sale['transaction_id']];
    $new = ['note' => $newNote,      'transaction_id' => $newTrans];

    $db->prepare("UPDATE sales SET note=?, transaction_id=? WHERE id=?")
       ->execute([$newNote, $newTrans, $id]);

    audit('SALE_EDITED', 'sale', $id,
        "Edited invoice {$sale['invoice_no']} — note/trans ID updated",
        $old, $new, $user['id']);

    header('Location: /sale-view?id=' . $id); exit;
}

$activePage = 'history'; $pageTitle = 'Edit Sale';
include __DIR__ . '/layout_top.php';
?>

<p style="color:var(--text2);font-size:13px;margin-bottom:16px">
    Editing invoice <strong><?= htmlspecialchars($sale['invoice_no']) ?></strong>.
    Only the note and transaction ID can be changed after a sale is finalised.
    To fix items or amounts, void the sale and re-enter it.
</p>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form class="form-card" method="post">
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">Transaction ID / Reference</label>
            <input class="form-input" name="transaction_id"
                   value="<?= htmlspecialchars($sale['transaction_id'] ?? '') ?>"
                   placeholder="e.g. JC9384728310">
        </div>
        <div class="form-group">
            <label class="form-label">Payment method (read-only)</label>
            <input class="form-input" value="<?= htmlspecialchars($sale['payment_method']) ?>" disabled>
        </div>
        <div class="form-group full">
            <label class="form-label">Order note</label>
            <textarea class="form-input" name="note" rows="3"
                      placeholder="Internal note about this sale"><?= htmlspecialchars($sale['note'] ?? '') ?></textarea>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save Changes</button>
            <a class="btn" href="/sale-view?id=<?= $id ?>">Cancel</a>
        </div>
    </div>
</form>

<?php include __DIR__ . '/layout_bottom.php'; ?>
