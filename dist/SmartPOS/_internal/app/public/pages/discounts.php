<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db();

// Toggle active
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_id'])) {
    $tid = (int)$_POST['toggle_id'];
    $db->prepare("UPDATE discounts SET active = CASE WHEN active=1 THEN 0 ELSE 1 END WHERE id=?")->execute([$tid]);
    header('Location: /discounts'); exit;
}

$discounts = $db->query("SELECT d.*,p.name prod_name FROM discounts d LEFT JOIN products p ON p.id=d.product_id ORDER BY d.active DESC,d.name")->fetchAll();
$sym = get_setting('currency_symbol','Rs');

$activePage='discounts'; $pageTitle='Discounts & Offers';
include __DIR__.'/layout_top.php';
?>
<div class="panel">
    <div class="panel-head">
        <span class="panel-title">All Discounts</span>
        <a href="/discounts-add" class="btn btn-primary">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add Discount
        </a>
    </div>
    <table class="dt">
        <thead><tr><th>Name</th><th>Type</th><th>Value</th><th>Condition</th><th>Product</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if(empty($discounts)): ?>
        <tr><td colspan="7" class="empty-row">No discounts yet. <a href="/discounts-add">Create your first offer</a>.</td></tr>
        <?php else: foreach($discounts as $d): ?>
        <tr>
            <td style="font-weight:500"><?=htmlspecialchars($d['name'])?></td>
            <td><span class="badge badge-accent"><?=htmlspecialchars($d['type'])?></span></td>
            <td>
                <?php if($d['type']==='flat'):    echo $sym.' '.money($d['value']);
                elseif($d['type']==='percent'):   echo $d['value'].'%';
                elseif($d['type']==='bogo'):      echo 'Buy '.$d['buy_qty'].' Get '.$d['get_qty'].' Free';
                elseif($d['type']==='combo'):     echo $sym.' '.money($d['value']).' off'; endif; ?>
            </td>
            <td class="dim" style="font-size:12px">
                <?= $d['min_subtotal']>0 ? 'Min order: '.$sym.' '.money($d['min_subtotal']) : '—' ?>
            </td>
            <td class="dim" style="font-size:12px"><?=htmlspecialchars($d['prod_name']??'—')?></td>
            <td>
                <?= $d['active']
                    ? '<span class="badge badge-green">Active</span>'
                    : '<span class="badge badge-gray">Inactive</span>' ?>
            </td>
            <td>
                <form method="post" style="display:inline">
                    <input type="hidden" name="toggle_id" value="<?=$d['id']?>">
                    <button class="btn btn-xs <?=$d['active']?'':'btn-green'?>" type="submit">
                        <?=$d['active']?'Disable':'Enable'?>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
