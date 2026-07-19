<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
$user = require_login(); $db = get_db();
$sym  = get_setting('currency_symbol','Rs');

$id  = (int)($_GET['id'] ?? 0);
$job = null;
if ($id) { $q=$db->prepare("SELECT * FROM repair_jobs WHERE id=?"); $q->execute([$id]); $job=$q->fetch(); }

$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $custName  = trim($_POST['customer_name'] ?? '');
    $custPhone = trim($_POST['customer_phone'] ?? '');
    $device    = trim($_POST['device_type'] ?? '');
    $model     = trim($_POST['device_model'] ?? '');
    $issue     = trim($_POST['issue'] ?? '');
    $status    = trim($_POST['status'] ?? 'received');
    $cost      = (float)($_POST['cost'] ?? 0);
    $charge    = (float)($_POST['charge'] ?? 0);
    $advance   = (float)($_POST['advance_paid'] ?? 0);
    $note      = trim($_POST['note'] ?? '');

    if (!$custName || !$device || !$issue) {
        $error = 'Customer name, device type, and issue are required.';
    } else {
        $balance = max(0, $charge - $advance);
        $confirmedDelivery = isset($_POST['confirm_delivery']);
        if ($status === 'delivered' && $balance > 0 && !$confirmedDelivery) {
            $error = 'BALANCE_WARNING';
        } else {
        $deliveredAt = ($status === 'delivered') ? "datetime('now','localtime')" : ($job['delivered_at'] ?? null ? "'".$job['delivered_at']."'" : 'NULL');

        if ($job) {
            $sql = "UPDATE repair_jobs SET customer_name=?,customer_phone=?,device_type=?,device_model=?,issue=?,status=?,cost=?,charge=?,advance_paid=?,note=?,delivered_at=" . ($status==='delivered' ? "COALESCE(delivered_at, datetime('now','localtime'))" : "delivered_at") . " WHERE id=?";
            $db->prepare($sql)->execute([$custName,$custPhone,$device,$model,$issue,$status,$cost,$charge,$advance,$note,$job['id']]);
            audit('REPAIR_UPDATED','repair',$job['id'],"Updated repair {$job['job_no']} — status: $status",['status'=>$job['status']],['status'=>$status],$user['id']);
            header('Location: /repairs'); exit;
        } else {
            $jobNo = 'RJ-' . date('ymdHis');
            $db->prepare("INSERT INTO repair_jobs (job_no,customer_name,customer_phone,device_type,device_model,issue,status,cost,charge,advance_paid,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$jobNo,$custName,$custPhone,$device,$model,$issue,$status,$cost,$charge,$advance,$note,$user['id']]);
            $newId = (int)$db->lastInsertId();
            audit('REPAIR_CREATED','repair',$newId,"New repair job $jobNo for $custName — $device",null,['status'=>$status],$user['id']);
            header('Location: /repairs'); exit;
        }
        }
    }
}

if ($error === 'BALANCE_WARNING') {
    $job = array_merge($job ?? [], [
        'customer_name'=>$custName,'customer_phone'=>$custPhone,'device_type'=>$device,'device_model'=>$model,
        'issue'=>$issue,'status'=>$status,'cost'=>$cost,'charge'=>$charge,'advance_paid'=>$advance,'note'=>$note,
    ]);
}

$statusLabels = ['received'=>'Received','in_progress'=>'In Progress','done'=>'Done — Ready for Pickup','delivered'=>'Delivered to Customer','cancelled'=>'Cancelled'];

$activePage='repairs'; $pageTitle=$job?'Update Repair Job':'New Repair Job';
include __DIR__.'/layout_top.php';
?>
<?php if($error === 'BALANCE_WARNING'): ?>
<div class="alert alert-warning">
    ⚠️ This job still has an unpaid balance of <strong><?=$sym?> <?=money(max(0,(float)$job['charge']-(float)$job['advance_paid']))?></strong>.
    Marking it Delivered means you're giving out the device without full payment.
    Click "Save Changes" again to confirm and proceed anyway.
</div>
<?php elseif($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>

<form class="form-card" method="post" style="max-width:680px">
    <?php if($error === 'BALANCE_WARNING'): ?>
    <input type="hidden" name="confirm_delivery" value="1">
    <?php endif; ?>
    <?php if($job): ?>
    <div class="form-group full" style="margin-bottom:12px">
        <span style="font-size:12px;color:var(--text3)">Job #<?=htmlspecialchars($job['job_no'])?> &middot; Received <?=htmlspecialchars($job['received_at'])?></span>
    </div>
    <?php endif; ?>
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">Customer Name *</label>
            <input class="form-input" name="customer_name" required value="<?=htmlspecialchars($job['customer_name']??'')?>">
        </div>
        <div class="form-group">
            <label class="form-label">Phone</label>
            <input class="form-input" name="customer_phone" value="<?=htmlspecialchars($job['customer_phone']??'')?>">
        </div>
        <div class="form-group">
            <label class="form-label">Device Type *</label>
            <select class="form-input" name="device_type" required>
                <?php foreach(['Mobile Phone','Tablet','Laptop','Smart Watch','Other'] as $dt): ?>
                <option value="<?=$dt?>" <?=($job['device_type']??'')===$dt?'selected':''?>><?=$dt?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Model</label>
            <input class="form-input" name="device_model" placeholder="e.g. Samsung A54" value="<?=htmlspecialchars($job['device_model']??'')?>">
        </div>
        <div class="form-group full">
            <label class="form-label">Issue / Problem Description *</label>
            <textarea class="form-input" name="issue" rows="2" required placeholder="e.g. Screen cracked, battery not charging"><?=htmlspecialchars($job['issue']??'')?></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select class="form-input" name="status">
                <?php foreach($statusLabels as $k=>$l): ?>
                <option value="<?=$k?>" <?=($job['status']??'received')===$k?'selected':''?>><?=$l?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Repair Cost (parts/labor, <?=$sym?>)</label>
            <input class="form-input" type="number" step="0.01" min="0" name="cost" value="<?=htmlspecialchars($job['cost']??'0')?>">
        </div>
        <div class="form-group">
            <label class="form-label">Charge to Customer (<?=$sym?>)</label>
            <input class="form-input" type="number" step="0.01" min="0" name="charge" value="<?=htmlspecialchars($job['charge']??'0')?>">
        </div>
        <div class="form-group">
            <label class="form-label">Advance Paid (<?=$sym?>)</label>
            <input class="form-input" type="number" step="0.01" min="0" name="advance_paid" value="<?=htmlspecialchars($job['advance_paid']??'0')?>">
        </div>
        <div class="form-group full">
            <label class="form-label">Note</label>
            <input class="form-input" name="note" value="<?=htmlspecialchars($job['note']??'')?>">
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?=$job?'Save Changes':'Create Job'?></button>
            <a class="btn" href="/repairs">Cancel</a>
        </div>
    </div>
</form>

<?php include __DIR__.'/layout_bottom.php'; ?>
