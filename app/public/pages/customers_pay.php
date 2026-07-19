<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
$user = require_login(); $db = get_db();
$sym  = get_setting('currency_symbol','Rs');

$id = (int)($_GET['id'] ?? 0);
$q  = $db->prepare("SELECT * FROM customers WHERE id=?"); $q->execute([$id]); $customer=$q->fetch();
if (!$customer) { header('Location: /customers'); exit; }

$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $method = trim($_POST['method'] ?? 'cash');
    $note   = trim($_POST['note']   ?? '');
    if ($amount <= 0) { $error='Enter a valid payment amount.'; }
    else {
        $db->beginTransaction();
        $db->prepare("INSERT INTO customer_payments (customer_id,amount,method,note) VALUES (?,?,?,?)")->execute([$customer['id'],$amount,$method,$note]);
        $db->prepare("UPDATE customers SET balance=balance-? WHERE id=?")->execute([$amount,$customer['id']]);
        audit('CUSTOMER_PAYMENT','customer',$customer['id'],"Payment of $sym $amount received from {$customer['name']} via $method",null,['amount'=>$amount],$user['id']);
        $db->commit();
        header('Location: /customers'); exit;
    }
}

$hist=$db->prepare("SELECT * FROM customer_payments WHERE customer_id=? ORDER BY created_at DESC LIMIT 15");
$hist->execute([$id]); $hist=$hist->fetchAll();

$activePage='customers'; $pageTitle='Record Payment — '.htmlspecialchars($customer['name']);
include __DIR__.'/layout_top.php';
?>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start">
<form class="form-card" method="post">
    <div class="metric-card" style="margin-bottom:16px">
        <span class="metric-label">Credit Balance (customer owes you)</span>
        <span class="metric-val red"><?=$sym?> <?=money($customer['balance'])?></span>
    </div>
    <div class="form-grid">
        <div class="form-group full"><label class="form-label">Amount Received (<?=$sym?>) *</label><input class="form-input" type="number" step="0.01" min="0.01" name="amount" required></div>
        <div class="form-group full">
            <label class="form-label">Payment Method</label>
            <select class="form-input" name="method">
                <?php foreach(['cash','card','easypaisa','jazzcash','bank','raast','other'] as $m): ?>
                <option value="<?=$m?>"><?=ucfirst($m)?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group full"><label class="form-label">Note</label><input class="form-input" name="note" placeholder="Optional note"></div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save Payment</button>
            <a class="btn" href="/customers">Cancel</a>
        </div>
    </div>
</form>
<div class="panel">
    <div class="panel-head"><span class="panel-title">Payment History</span></div>
    <table class="dt">
        <thead><tr><th>Date</th><th>Method</th><th class="num">Amount</th><th>Note</th></tr></thead>
        <tbody>
        <?php if(empty($hist)): ?><tr><td colspan="4" class="empty-row">No payments yet.</td></tr>
        <?php else: foreach($hist as $h): ?>
        <tr>
            <td style="font-size:11px;color:var(--text2)"><?=htmlspecialchars($h['created_at'])?></td>
            <td><span class="badge badge-gray"><?=htmlspecialchars($h['method'])?></span></td>
            <td class="num green"><?=$sym?> <?=money($h['amount'])?></td>
            <td style="font-size:12px"><?=htmlspecialchars($h['note']??'—')?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
