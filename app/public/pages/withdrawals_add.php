<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
$user = require_login(); $db = get_db();
$sym  = get_setting('currency_symbol','Rs');
$defaultPct = (float)get_setting('withdraw_default_commission_pct','1');

$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $service  = trim($_POST['service'] ?? '');
    $custName = trim($_POST['customer_name'] ?? '');
    $custCnic = trim($_POST['customer_cnic'] ?? '');
    $custPhone= trim($_POST['customer_phone'] ?? '');
    $account  = trim($_POST['account_no'] ?? '');
    $amount   = (float)($_POST['amount'] ?? 0);
    $commission = (float)($_POST['commission'] ?? 0);
    $note     = trim($_POST['note'] ?? '');

    if (!$service || !$custName || $amount<=0) {
        $error = 'Service, customer name, and amount are required.';
    } else {
        $cashPaid = max(0, $amount - $commission);
        $receiptNo = 'WD-' . date('ymdHis');
        $db->prepare("INSERT INTO withdrawals (receipt_no,service,customer_name,customer_cnic,customer_phone,account_no,amount,commission,cash_paid,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$receiptNo,$service,$custName,$custCnic,$custPhone,$account,$amount,$commission,$cashPaid,$note,$user['id']]);
        $id = (int)$db->lastInsertId();
        audit('WITHDRAWAL_CREATED','withdrawal',$id,"Withdrawal $receiptNo — $sym $amount via $service for $custName",null,['amount'=>$amount,'service'=>$service],$user['id']);
        header('Location: /withdrawal-view?id='.$id.'&new=1'); exit;
    }
}

$activePage='withdrawals'; $pageTitle='New Withdrawal';
include __DIR__.'/layout_top.php';
?>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>

<form class="form-card" method="post" id="wd-form" style="max-width:640px">
    <div class="form-grid">
        <div class="form-group full">
            <label class="form-label">Service *</label>
            <select class="form-input" name="service" required>
                <option value="">— Select service —</option>
                <option value="easypaisa">EasyPaisa</option>
                <option value="jazzcash">JazzCash</option>
                <option value="bank">Bank Account</option>
                <option value="raast">Raast</option>
                <option value="bisp">BISP / Benazir Income Support</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Customer Name *</label>
            <input class="form-input" name="customer_name" required>
        </div>
        <div class="form-group">
            <label class="form-label">CNIC</label>
            <input class="form-input" name="customer_cnic" placeholder="XXXXX-XXXXXXX-X">
        </div>
        <div class="form-group">
            <label class="form-label">Phone</label>
            <input class="form-input" name="customer_phone" placeholder="03XX-XXXXXXX">
        </div>
        <div class="form-group">
            <label class="form-label">Account / Wallet Number</label>
            <input class="form-input" name="account_no">
        </div>
        <div class="form-group">
            <label class="form-label">Withdrawal Amount (<?=$sym?>) *</label>
            <input class="form-input" type="number" step="0.01" min="0.01" name="amount" id="wd-amount" required>
        </div>
        <div class="form-group">
            <label class="form-label">Your Commission (<?=$sym?>)</label>
            <input class="form-input" type="number" step="0.01" min="0" name="commission" id="wd-commission" value="0">
            <span style="font-size:11px;color:var(--text3)">Default rate: <?=$defaultPct?>% (auto-calculated, editable)</span>
        </div>
        <div class="form-group full" style="background:var(--surface2);border-radius:8px;padding:10px 12px">
            <span style="font-size:12.5px;color:var(--text2)">Cash to hand the customer: <strong id="wd-cash-out" style="color:var(--accent);font-size:15px"><?=$sym?> 0</strong></span>
        </div>
        <div class="form-group full">
            <label class="form-label">Note</label>
            <input class="form-input" name="note" placeholder="Optional note">
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save & Print Receipt</button>
            <a class="btn" href="/withdrawals">Cancel</a>
        </div>
    </div>
</form>

<script>
(function(){
    var amt  = document.getElementById('wd-amount');
    var comm = document.getElementById('wd-commission');
    var out  = document.getElementById('wd-cash-out');
    var defaultPct = <?= json_encode($defaultPct) ?>;
    var sym = <?= json_encode($sym) ?>;
    var commTouched = false;

    function update() {
        var a = parseFloat(amt.value) || 0;
        if (!commTouched) comm.value = (a * defaultPct / 100).toFixed(2);
        var c = parseFloat(comm.value) || 0;
        out.textContent = sym + ' ' + Math.max(0, a - c).toLocaleString();
    }
    amt.addEventListener('input', update);
    comm.addEventListener('input', function(){ commTouched = true; update(); });
    update();
})();
</script>

<?php include __DIR__.'/layout_bottom.php'; ?>
