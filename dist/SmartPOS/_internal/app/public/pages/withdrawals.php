<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login();
require_once dirname(__DIR__,2) . '/includes/pagination.php';

$db  = get_db();
$sym = get_setting('currency_symbol','Rs');

$dateFrom = trim($_GET['from'] ?? date('Y-m-01'));
$dateTo   = trim($_GET['to']   ?? date('Y-m-d'));
$service  = trim($_GET['service'] ?? '');

$where=['1=1']; $params=[];
if ($dateFrom) { $where[]='date(w.created_at)>=?'; $params[]=$dateFrom; }
if ($dateTo)   { $where[]='date(w.created_at)<=?'; $params[]=$dateTo;   }
if ($service)  { $where[]='w.service=?';           $params[]=$service;  }
$wsql=implode(' AND ',$where);

$cs=$db->prepare("SELECT COUNT(*) c FROM withdrawals w WHERE $wsql"); $cs->execute($params); $total=(int)$cs->fetch()['c'];

[$page,$perPage]=paginate_params();
$totalPages=max(1,(int)ceil($total/$perPage));
$page=min($page,$totalPages); $offset=($page-1)*$perPage;

$stmt=$db->prepare("SELECT w.*,u.name uname FROM withdrawals w LEFT JOIN users u ON u.id=w.created_by WHERE $wsql ORDER BY w.created_at DESC LIMIT ? OFFSET ?");
foreach($params as $i=>$v) $stmt->bindValue($i+1,$v);
$stmt->bindValue(count($params)+1,$perPage,PDO::PARAM_INT);
$stmt->bindValue(count($params)+2,$offset, PDO::PARAM_INT);
$stmt->execute(); $rows=$stmt->fetchAll();

$sumStmt=$db->prepare("SELECT COALESCE(SUM(amount),0) amt, COALESCE(SUM(commission),0) comm, COALESCE(SUM(cash_paid),0) paid, COUNT(*) cnt FROM withdrawals w WHERE $wsql");
$sumStmt->execute($params); $sums=$sumStmt->fetch();

$serviceLabels=['easypaisa'=>'EasyPaisa','jazzcash'=>'JazzCash','bank'=>'Bank','raast'=>'Raast','bisp'=>'BISP / Benazir','other'=>'Other'];

$activePage='withdrawals'; $pageTitle='Withdrawals (Agent Cash-Out)';
include __DIR__.'/layout_top.php';
?>

<div class="metric-grid" style="margin-bottom:16px">
    <div class="metric-card"><span class="metric-label">Total Withdrawn</span><span class="metric-val"><?=$sym?> <?=money($sums['amt'])?></span></div>
    <div class="metric-card"><span class="metric-label">Commission Earned</span><span class="metric-val green"><?=$sym?> <?=money($sums['comm'])?></span></div>
    <div class="metric-card"><span class="metric-label">Cash Paid Out</span><span class="metric-val yellow"><?=$sym?> <?=money($sums['paid'])?></span></div>
    <div class="metric-card"><span class="metric-label">Transactions</span><span class="metric-val"><?=$sums['cnt']?></span></div>
</div>

<div class="panel">
    <div class="panel-head">
        <span class="panel-title">Withdrawal Transactions</span>
        <a href="/withdrawals-add" class="btn btn-primary">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            New Withdrawal
        </a>
    </div>
    <form class="filter-bar" method="get" action="/withdrawals" style="padding:12px 16px">
        <input type="date" name="from" value="<?=htmlspecialchars($dateFrom)?>">
        <input type="date" name="to"   value="<?=htmlspecialchars($dateTo)?>">
        <select name="service">
            <option value="">All Services</option>
            <?php foreach($serviceLabels as $k=>$l): ?>
            <option value="<?=$k?>" <?=$service===$k?'selected':''?>><?=$l?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-primary" type="submit">Filter</button>
        <a class="btn btn-sm" href="/withdrawals">Reset</a>
    </form>
    <table class="dt">
        <thead><tr><th>Receipt #</th><th>Service</th><th>Customer</th><th>Account</th><th class="num">Amount</th><th class="num">Commission</th><th class="num">Cash Paid</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php if(empty($rows)): ?>
        <tr><td colspan="9" class="empty-row">No withdrawals recorded yet.</td></tr>
        <?php else: foreach($rows as $r): ?>
        <tr>
            <td><a href="/withdrawal-view?id=<?=$r['id']?>"><?=htmlspecialchars($r['receipt_no'])?></a></td>
            <td><span class="badge badge-accent"><?=$serviceLabels[$r['service']]??$r['service']?></span></td>
            <td><?=htmlspecialchars($r['customer_name'])?><?=$r['customer_cnic']?'<br><small style="color:var(--text3)">CNIC: '.htmlspecialchars($r['customer_cnic']).'</small>':''?></td>
            <td class="dim" style="font-size:11px"><?=htmlspecialchars($r['account_no']??'—')?></td>
            <td class="num"><?=$sym?> <?=money($r['amount'])?></td>
            <td class="num green"><?=$sym?> <?=money($r['commission'])?></td>
            <td class="num" style="font-weight:600"><?=$sym?> <?=money($r['cash_paid'])?></td>
            <td class="dim" style="font-size:11px"><?=htmlspecialchars($r['created_at'])?></td>
            <td><a class="btn btn-xs" href="/withdrawal-view?id=<?=$r['id']?>">Receipt</a></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?=render_pagination('/withdrawals',$page,$totalPages,$perPage,$total,['from'=>$dateFrom,'to'=>$dateTo,'service'=>$service])?>
</div>

<?php include __DIR__.'/layout_bottom.php'; ?>
