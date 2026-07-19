<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login();
$db = get_db(); $sym = get_setting('currency_symbol','Rs');

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT w.*,u.name uname FROM withdrawals w LEFT JOIN users u ON u.id=w.created_by WHERE w.id=?");
$stmt->execute([$id]); $w = $stmt->fetch();
if (!$w) { header('Location: /withdrawals'); exit; }

$serviceLabels=['easypaisa'=>'EasyPaisa','jazzcash'=>'JazzCash','bank'=>'Bank Account','raast'=>'Raast','bisp'=>'BISP / Benazir Income Support','other'=>'Other'];

$activePage='withdrawals'; $pageTitle='Withdrawal Receipt';
include __DIR__.'/layout_top.php';
?>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap" class="no-print">
  <button class="btn btn-primary" onclick="window.print()">🖨 Print Receipt</button>
  <a class="btn" href="/withdrawals">← Back to Withdrawals</a>
</div>

<div id="receipt-root"></div>

<script>
window.RECEIPT_DATA = {
    type: 'withdrawal',
    business_name: <?= json_encode(get_setting('business_name','My Shop')) ?>,
    business_address: <?= json_encode(get_setting('business_address','')) ?>,
    business_phone: <?= json_encode(get_setting('business_phone','')) ?>,
    logo_url: <?= json_encode(get_setting('receipt_logo_url','')) ?>,
    footer: <?= json_encode(get_setting('receipt_footer','Thank you for your visit!')) ?>,
    signature_note: <?= json_encode(get_setting('shop_signature_note','Authorized Signature')) ?>,
    receipt_no: <?= json_encode($w['receipt_no']) ?>,
    date: <?= json_encode($w['created_at']) ?>,
    service: <?= json_encode($serviceLabels[$w['service']] ?? $w['service']) ?>,
    customer_name: <?= json_encode($w['customer_name']) ?>,
    customer_cnic: <?= json_encode($w['customer_cnic']) ?>,
    customer_phone: <?= json_encode($w['customer_phone']) ?>,
    account_no: <?= json_encode($w['account_no']) ?>,
    amount: <?= (float)$w['amount'] ?>,
    commission: <?= (float)$w['commission'] ?>,
    cash_paid: <?= (float)$w['cash_paid'] ?>,
    cashier: <?= json_encode($w['uname'] ?? 'Admin') ?>,
    currency_symbol: <?= json_encode($sym) ?>
};
</script>
<script src="/assets/js/receipt.js"></script>
<script>document.getElementById('receipt-root').innerHTML = renderReceiptHTML(window.RECEIPT_DATA);</script>

<?php include __DIR__.'/layout_bottom.php'; ?>
