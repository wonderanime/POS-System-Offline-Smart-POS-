<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login();
$db = get_db(); $sym = get_setting('currency_symbol','Rs');

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM repair_jobs WHERE id=?");
$stmt->execute([$id]); $job = $stmt->fetch();
if (!$job) { header('Location: /repairs'); exit; }

$statusLabels = ['received'=>'Received','in_progress'=>'In Progress','done'=>'Ready for Pickup','delivered'=>'Delivered','cancelled'=>'Cancelled'];
$balance = max(0, (float)$job['charge'] - (float)$job['advance_paid']);

$activePage='repairs'; $pageTitle='Repair Claim Ticket';
include __DIR__.'/layout_top.php';
?>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap" class="no-print">
  <button class="btn btn-primary" onclick="window.print()">🖨 Print Claim Ticket</button>
  <a class="btn" href="/repairs-add?id=<?=$id?>">Update Job</a>
  <a class="btn" href="/repairs">← Back to Repairs</a>
</div>

<div id="receipt-root"></div>

<script>
window.RECEIPT_DATA = {
    type: 'repair',
    business_name: <?= json_encode(get_setting('business_name','My Shop')) ?>,
    business_address: <?= json_encode(get_setting('business_address','')) ?>,
    business_phone: <?= json_encode(get_setting('business_phone','')) ?>,
    logo_url: <?= json_encode(get_setting('receipt_logo_url','')) ?>,
    footer: <?= json_encode(get_setting('receipt_footer','Thank you for your visit!')) ?>,
    signature_note: <?= json_encode(get_setting('shop_signature_note','Authorized Signature')) ?>,
    receipt_no: <?= json_encode($job['job_no']) ?>,
    date: <?= json_encode($job['received_at']) ?>,
    customer_name: <?= json_encode($job['customer_name']) ?>,
    customer_phone: <?= json_encode($job['customer_phone']) ?>,
    device: <?= json_encode($job['device_type'] . ($job['device_model'] ? ' — '.$job['device_model'] : '')) ?>,
    issue: <?= json_encode($job['issue']) ?>,
    status: <?= json_encode($statusLabels[$job['status']] ?? $job['status']) ?>,
    charge: <?= (float)$job['charge'] ?>,
    advance_paid: <?= (float)$job['advance_paid'] ?>,
    balance: <?= (float)$balance ?>,
    currency_symbol: <?= json_encode($sym) ?>
};
</script>
<script src="/assets/js/receipt.js"></script>
<script>document.getElementById('receipt-root').innerHTML = renderReceiptHTML(window.RECEIPT_DATA);</script>

<?php include __DIR__.'/layout_bottom.php'; ?>
