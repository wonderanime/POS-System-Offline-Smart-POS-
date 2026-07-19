<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
$user = require_login(); $db = get_db();
$sym  = get_setting('currency_symbol','Rs');

$today = date('Y-m-d');
$stmt = $db->prepare("SELECT * FROM cash_drawer WHERE business_date=?");
$stmt->execute([$today]); $todayDrawer = $stmt->fetch();

$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'open') {
        $opening = (float)($_POST['opening_balance'] ?? 0);
        if ($todayDrawer) { $error = 'Drawer already opened today.'; }
        else {
            $db->prepare("INSERT INTO cash_drawer (business_date,opening_balance,opened_by) VALUES (?,?,?)")
               ->execute([$today,$opening,$user['id']]);
            audit('DRAWER_OPENED','cash_drawer',0,"Opened drawer with $sym $opening",null,['opening'=>$opening],$user['id']);
            header('Location: /cash-drawer'); exit;
        }
    } elseif ($action === 'close') {
        $closing = (float)($_POST['closing_balance'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        if (!$todayDrawer) { $error = 'Open the drawer first.'; }
        else {
            $cashSales = $db->prepare("SELECT COALESCE(SUM(total),0) v FROM sales WHERE date(created_at)=? AND payment_method='cash' AND status='completed'");
            $cashSales->execute([$today]); $cashSales = $cashSales->fetch()['v'];

            $cashExp = $db->prepare("SELECT COALESCE(SUM(amount),0) v FROM expenses WHERE date(created_at)=? AND method='cash'");
            $cashExp->execute([$today]); $cashExp = $cashExp->fetch()['v'];

            $cashWd = $db->prepare("SELECT COALESCE(SUM(cash_paid),0) v FROM withdrawals WHERE date(created_at)=?");
            $cashWd->execute([$today]); $cashWd = $cashWd->fetch()['v'];

            $expected = $todayDrawer['opening_balance'] + $cashSales - $cashExp - $cashWd;

            $db->prepare("UPDATE cash_drawer SET closing_balance=?, expected_closing=?, note=?, closed_by=?, closed_at=datetime('now','localtime') WHERE id=?")
               ->execute([$closing,$expected,$note,$user['id'],$todayDrawer['id']]);
            audit('DRAWER_CLOSED','cash_drawer',$todayDrawer['id'],"Closed drawer: counted $sym $closing, expected $sym ".round($expected,2),null,['closing'=>$closing,'expected'=>$expected],$user['id']);
            header('Location: /cash-drawer'); exit;
        }
    }
}

$stmt2 = $db->prepare("SELECT * FROM cash_drawer WHERE business_date=?");
$stmt2->execute([$today]); $todayDrawer = $stmt2->fetch();

$history = $db->query("SELECT * FROM cash_drawer ORDER BY business_date DESC LIMIT 14")->fetchAll();

$activePage='cashdrawer'; $pageTitle='Cash Drawer';
include __DIR__.'/layout_top.php';
?>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start">

<div class="panel">
    <div class="panel-head"><span class="panel-title">Today — <?=$today?></span></div>
    <div class="panel-body">
    <?php if (!$todayDrawer): ?>
        <p style="font-size:13px;color:var(--text2);margin-bottom:14px">Drawer not opened yet today. Count your starting cash and open it.</p>
        <form method="post">
            <input type="hidden" name="action" value="open">
            <div class="form-group" style="margin-bottom:12px">
                <label class="form-label">Opening Cash Balance (<?=$sym?>)</label>
                <input class="form-input" type="number" step="0.01" min="0" name="opening_balance" required autofocus>
            </div>
            <button class="btn btn-primary" type="submit">Open Drawer</button>
        </form>
    <?php elseif (!$todayDrawer['closing_balance']): ?>
        <div class="metric-card" style="margin-bottom:14px">
            <span class="metric-label">Opening Balance</span>
            <span class="metric-val"><?=$sym?> <?=money($todayDrawer['opening_balance'])?></span>
            <span class="metric-sub">opened <?=htmlspecialchars($todayDrawer['opened_at'])?></span>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="close">
            <div class="form-group" style="margin-bottom:10px">
                <label class="form-label">Cash Counted at Closing (<?=$sym?>)</label>
                <input class="form-input" type="number" step="0.01" min="0" name="closing_balance" required>
            </div>
            <div class="form-group" style="margin-bottom:12px">
                <label class="form-label">Note (optional)</label>
                <input class="form-input" name="note" placeholder="e.g. Rs 200 short, gave change to customer">
            </div>
            <button class="btn btn-primary" type="submit">Close Drawer</button>
        </form>
    <?php else:
        $diff = $todayDrawer['closing_balance'] - $todayDrawer['expected_closing'];
    ?>
        <div class="metric-grid" style="grid-template-columns:1fr 1fr">
            <div class="metric-card"><span class="metric-label">Expected</span><span class="metric-val"><?=$sym?> <?=money($todayDrawer['expected_closing'])?></span></div>
            <div class="metric-card"><span class="metric-label">Counted</span><span class="metric-val"><?=$sym?> <?=money($todayDrawer['closing_balance'])?></span></div>
        </div>
        <div class="alert <?=abs($diff)<1?'alert-success':($diff<0?'alert-danger':'alert-warning')?>" style="margin-top:12px">
            <?php if(abs($diff)<1): ?>✓ Drawer balanced perfectly.
            <?php elseif($diff<0): ?>⚠️ Short by <?=$sym?> <?=money(abs($diff))?>
            <?php else: ?>Over by <?=$sym?> <?=money($diff)?>
            <?php endif; ?>
        </div>
        <p style="font-size:12px;color:var(--text3);margin-top:10px">Closed <?=htmlspecialchars($todayDrawer['closed_at'])?></p>
    <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-head"><span class="panel-title">History</span></div>
    <table class="dt">
        <thead><tr><th>Date</th><th class="num">Opening</th><th class="num">Expected</th><th class="num">Counted</th><th>Diff</th></tr></thead>
        <tbody>
        <?php if(empty($history)): ?>
        <tr><td colspan="5" class="empty-row">No history yet.</td></tr>
        <?php else: foreach($history as $h):
            $d = $h['closing_balance']!==null ? $h['closing_balance']-$h['expected_closing'] : null;
        ?>
        <tr>
            <td style="font-size:12px"><?=htmlspecialchars($h['business_date'])?></td>
            <td class="num"><?=$sym?> <?=money($h['opening_balance'])?></td>
            <td class="num dim"><?=$h['expected_closing']!==null?$sym.' '.money($h['expected_closing']):'—'?></td>
            <td class="num"><?=$h['closing_balance']!==null?$sym.' '.money($h['closing_balance']):'—'?></td>
            <td>
            <?php if($d===null): ?><span class="badge badge-gray">Open</span>
            <?php elseif(abs($d)<1): ?><span class="badge badge-green">Balanced</span>
            <?php elseif($d<0): ?><span class="badge badge-red">Short <?=$sym?> <?=money(abs($d))?></span>
            <?php else: ?><span class="badge badge-yellow">Over <?=$sym?> <?=money($d)?></span>
            <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

</div>

<?php include __DIR__.'/layout_bottom.php'; ?>
