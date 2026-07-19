<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db();

[$page,$perPage] = paginate_params();
$total = (int)$db->query("SELECT COUNT(*) c FROM audit_log")->fetch()['c'];
$totalPages = max(1,(int)ceil($total/$perPage)); $page=min($page,$totalPages); $offset=($page-1)*$perPage;

$stmt = $db->prepare("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1,$perPage,PDO::PARAM_INT); $stmt->bindValue(2,$offset,PDO::PARAM_INT); $stmt->execute();
$logs = $stmt->fetchAll();

$badgeFor = fn($a) => str_contains($a,'VOID') ? 'badge-red' : 'badge-gray';

$activePage='logs'; $pageTitle='Audit Log';
include __DIR__.'/layout_top.php';
?>
<div class="panel">
  <div class="panel-head"><span class="panel-title">System Activity Log</span></div>
  <table class="dt">
    <thead><tr><th>Time</th><th>Action</th><th>Details</th></tr></thead>
    <tbody>
    <?php if(empty($logs)): ?><tr><td colspan="3" class="empty-row">No log entries yet.</td></tr>
    <?php else: foreach($logs as $l): ?>
    <tr>
      <td style="font-size:11px;color:var(--text2);white-space:nowrap"><?=htmlspecialchars($l['created_at'])?></td>
      <td><span class="badge <?=$badgeFor($l['action'])?>"><?=htmlspecialchars($l['action'])?></span></td>
      <td style="font-size:12.5px"><?=htmlspecialchars($l['summary']??'—')?></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?=render_pagination('/logs',$page,$totalPages,$perPage,$total)?>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
