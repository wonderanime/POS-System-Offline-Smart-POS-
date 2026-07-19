<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login(); $db = get_db();

$msg=''; $err='';

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='backup_now') {
    $filename = run_backup('manual');
    $msg = "Backup created: $filename";
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='import_db') {
    if (!empty($_FILES['dbfile']['tmp_name'])) {
        $tmp = $_FILES['dbfile']['tmp_name'];
        $header = file_get_contents($tmp, false, null, 0, 16);
        if (strpos($header,'SQLite') !== false) {
            run_backup('pre_import');
            copy($tmp, DB_PATH);
            $msg = 'Database restored. Please refresh the page.';
        } else { $err = 'Invalid file — only .db SQLite files are accepted.'; }
    } else { $err = 'No file uploaded.'; }
}

if (isset($_GET['dl'])) {
    $f = basename($_GET['dl']); $path = BACKUP_DIR.'/'.$f;
    if (file_exists($path) && preg_match('/^smartpos_[\d_\-]+\.db$/',$f)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$f.'"');
        readfile($path); exit;
    }
}

$files = glob(BACKUP_DIR.'/smartpos_*.db') ?: [];
usort($files, fn($a,$b) => filemtime($b)-filemtime($a));

$activePage='backup'; $pageTitle='Backup & Restore';
include __DIR__.'/layout_top.php';
?>
<?php if($msg): ?><div class="alert alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?=htmlspecialchars($err)?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
  <div class="panel">
    <div class="panel-head"><span class="panel-title">Backup Database</span></div>
    <div class="panel-body">
      <p style="font-size:13px;color:var(--text2);margin-bottom:12px">Last backup: <strong><?=get_setting('last_backup_at')?:'Never'?></strong></p>
      <p style="font-size:12px;color:var(--text3);margin-bottom:16px">Auto-backup runs once a day. You can also create one manually.</p>
      <form method="post"><input type="hidden" name="action" value="backup_now"><button class="btn btn-primary" type="submit">Create Backup Now</button></form>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><span class="panel-title">Restore Database</span></div>
    <div class="panel-body">
      <div class="alert alert-warning" style="margin-bottom:14px">⚠️ This replaces your current data. A safety backup is made first.</div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import_db">
        <input class="form-input" type="file" name="dbfile" accept=".db" required style="margin-bottom:12px;padding:8px">
        <button class="btn btn-danger" type="submit" data-confirm="This will replace ALL current data. Continue?">Restore Database</button>
      </form>
    </div>
  </div>

  <div class="panel" style="grid-column:1/-1">
    <div class="panel-head"><span class="panel-title">Saved Backups (<?=count($files)?>)</span></div>
    <table class="dt">
      <thead><tr><th>Filename</th><th>Size</th><th>Date</th><th></th></tr></thead>
      <tbody>
      <?php if(empty($files)): ?><tr><td colspan="4" class="empty-row">No backups yet.</td></tr>
      <?php else: foreach(array_slice($files,0,20) as $f): ?>
      <tr>
        <td style="font-size:11px"><?=htmlspecialchars(basename($f))?></td>
        <td style="font-size:11px;color:var(--text2)"><?=number_format(filesize($f)/1024,1)?> KB</td>
        <td style="font-size:11px;color:var(--text2)"><?=date('Y-m-d H:i',filemtime($f))?></td>
        <td><a class="btn btn-xs" href="/backup?dl=<?=urlencode(basename($f))?>">Download</a></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__.'/layout_bottom.php'; ?>
