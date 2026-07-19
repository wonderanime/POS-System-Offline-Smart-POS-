<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login();
require_once dirname(__DIR__,2) . '/includes/pagination.php';

$db  = get_db();
$sym = get_setting('currency_symbol','Rs');

$status = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');

$where=['1=1']; $params=[];
if ($status) { $where[]='status=?'; $params[]=$status; }
if ($search) { $where[]='(customer_name LIKE ? OR job_no LIKE ? OR device_model LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; $params[]="%$search%"; }
$wsql=implode(' AND ',$where);

$cs=$db->prepare("SELECT COUNT(*) c FROM repair_jobs WHERE $wsql"); $cs->execute($params); $total=(int)$cs->fetch()['c'];

[$page,$perPage]=paginate_params();
$totalPages=max(1,(int)ceil($total/$perPage));
$page=min($page,$totalPages); $offset=($page-1)*$perPage;

$stmt=$db->prepare("SELECT * FROM repair_jobs WHERE $wsql ORDER BY received_at DESC LIMIT ? OFFSET ?");
foreach($params as $i=>$v) $stmt->bindValue($i+1,$v);
$stmt->bindValue(count($params)+1,$perPage,PDO::PARAM_INT);
$stmt->bindValue(count($params)+2,$offset, PDO::PARAM_INT);
$stmt->execute(); $rows=$stmt->fetchAll();

$statusCounts = $db->query("SELECT status, COUNT(*) c FROM repair_jobs GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$pendingCount = ($statusCounts['received']??0) + ($statusCounts['in_progress']??0);

$profitSum = $db->query("SELECT COALESCE(SUM(charge-cost),0) v FROM repair_jobs WHERE status IN ('done','delivered')")->fetch()['v'];

$statusLabels = ['received'=>'Received','in_progress'=>'In Progress','done'=>'Done','delivered'=>'Delivered','cancelled'=>'Cancelled'];
$statusBadge  = ['received'=>'badge-gray','in_progress'=>'badge-yellow','done'=>'badge-blue','delivered'=>'badge-green','cancelled'=>'badge-red'];

$activePage='repairs'; $pageTitle='Repair Jobs';
include __DIR__.'/layout_top.php';
?>

<div class="metric-grid" style="margin-bottom:16px">
    <div class="metric-card"><span class="metric-label">Pending Jobs</span><span class="metric-val yellow"><?=$pendingCount?></span></div>
    <div class="metric-card"><span class="metric-label">Completed Jobs</span><span class="metric-val green"><?=($statusCounts['delivered']??0)?></span></div>
    <div class="metric-card"><span class="metric-label">Repair Profit (done/delivered)</span><span class="metric-val"><?=$sym?> <?=money($profitSum)?></span></div>
    <div class="metric-card"><span class="metric-label">Total Jobs</span><span class="metric-val"><?=$total?></span></div>
</div>

<div class="panel">
    <div class="panel-head">
        <span class="panel-title">Repair Jobs</span>
        <a href="/repairs-add" class="btn btn-primary">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            New Repair Job
        </a>
    </div>
    <form class="filter-bar" method="get" action="/repairs" style="padding:12px 16px">
        <input type="text" name="q" placeholder="Job # / customer / model" value="<?=htmlspecialchars($search)?>">
        <select name="status">
            <option value="">All Status</option>
            <?php foreach($statusLabels as $k=>$l): ?>
            <option value="<?=$k?>" <?=$status===$k?'selected':''?>><?=$l?> (<?=$statusCounts[$k]??0?>)</option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-primary" type="submit">Filter</button>
        <a class="btn btn-sm" href="/repairs">Reset</a>
    </form>
    <table class="dt">
        <thead><tr><th>Job #</th><th>Customer</th><th>Device</th><th>Issue</th><th class="num">Charge</th><th class="num">Cost</th><th class="num">Profit</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if(empty($rows)): ?>
        <tr><td colspan="9" class="empty-row">No repair jobs yet. <a href="/repairs-add">Add your first job</a>.</td></tr>
        <?php else: foreach($rows as $r): ?>
        <tr>
            <td><a href="/repairs-add?id=<?=$r['id']?>"><?=htmlspecialchars($r['job_no'])?></a></td>
            <td><?=htmlspecialchars($r['customer_name'])?><?=$r['customer_phone']?'<br><small style="color:var(--text3)">'.htmlspecialchars($r['customer_phone']).'</small>':''?></td>
            <td><?=htmlspecialchars($r['device_type'])?><?=$r['device_model']?' — '.htmlspecialchars($r['device_model']):''?></td>
            <td style="font-size:12px;max-width:180px"><?=htmlspecialchars($r['issue'])?></td>
            <td class="num"><?=$sym?> <?=money($r['charge'])?></td>
            <td class="num dim"><?=$sym?> <?=money($r['cost'])?></td>
            <td class="num" style="font-weight:600;color:<?=($r['charge']-$r['cost'])>=0?'var(--green)':'var(--red)'?>"><?=$sym?> <?=money($r['charge']-$r['cost'])?></td>
            <td><span class="badge <?=$statusBadge[$r['status']]?>"><?=$statusLabels[$r['status']]?></span></td>
            <td>
                <a class="btn btn-xs" href="/repairs-add?id=<?=$r['id']?>">Update</a>
                <a class="btn btn-xs" href="/repair-ticket?id=<?=$r['id']?>">Ticket</a>
                <?php if($r['status']==='done' && $r['customer_phone']):
                    $msg = urlencode("Hi {$r['customer_name']}, your {$r['device_type']} repair (Job #{$r['job_no']}) is ready for pickup. Balance due: ".money(max(0,$r['charge']-$r['advance_paid'])));
                    $phoneClean = preg_replace('/[^0-9]/','',$r['customer_phone']);
                ?>
                <a class="btn btn-xs" href="https://wa.me/<?=$phoneClean?>?text=<?=$msg?>" target="_blank" title="Send pickup reminder">📱 Remind</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?=render_pagination('/repairs',$page,$totalPages,$perPage,$total,['q'=>$search,'status'=>$status])?>
</div>

<?php include __DIR__.'/layout_bottom.php'; ?>
