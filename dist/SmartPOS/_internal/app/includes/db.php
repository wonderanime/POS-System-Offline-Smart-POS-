<?php

define('BASE_DIR',    dirname(__DIR__));
define('DB_PATH',     BASE_DIR . '/database/pos.db');
define('SCHEMA_PATH', BASE_DIR . '/database/schema.sql');
define('BACKUP_DIR',  BASE_DIR . '/database/backups');
define('UPLOADS_DIR', BASE_DIR . '/public/assets/uploads');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');
    }
    return $pdo;
}

function init_db(): void {
    foreach ([dirname(DB_PATH), BACKUP_DIR, UPLOADS_DIR] as $d) {
        if (!is_dir($d)) mkdir($d, 0777, true);
    }
    $isNew = !file_exists(DB_PATH);
    $pdo   = get_db();
    if ($isNew) {
        $pdo->exec(file_get_contents(SCHEMA_PATH));
        seed_data($pdo);
    } else {
        // Safe on every boot: adds anything missing without touching existing data
        $pdo->exec(file_get_contents(SCHEMA_PATH));
        migrate_columns($pdo);
    }
}

function migrate_columns(PDO $pdo): void {
    $columnsToAdd = [
        'sales' => ['void_reason' => 'TEXT', 'voided_at' => 'TEXT'],
    ];
    foreach ($columnsToAdd as $table => $cols) {
        $existing = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
        foreach ($cols as $col => $type) {
            if (!in_array($col, $existing, true)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $col $type");
            }
        }
    }
}

function seed_data(PDO $pdo): void {
    $pdo->beginTransaction();

    $pdo->prepare("INSERT INTO users (name,role,pin_hash) VALUES (?,?,?)")
        ->execute(['Admin', 'admin', password_hash('1234', PASSWORD_DEFAULT)]);

    $defaults = [
        'shop_name' => 'My Shop', 'shop_address' => '', 'shop_phone' => '', 'shop_logo' => '',
        'currency_symbol' => 'Rs', 'tax_rate_default' => '0',
        'receipt_size' => '80mm', 'theme' => 'dark',
        'last_backup_at' => '',
    ];
    $s = $pdo->prepare("INSERT INTO settings (key,value) VALUES (?,?)");
    foreach ($defaults as $k => $v) $s->execute([$k, $v]);

    $cats = ['Beverages','Snacks','Grocery','Dairy','Personal Care','Others'];
    $catIds = [];
    $cs = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
    foreach ($cats as $c) { $cs->execute([$c]); $catIds[$c] = $pdo->lastInsertId(); }

    $brands = ['Generic','Nestle','Unilever'];
    foreach ($brands as $b) $pdo->prepare("INSERT INTO brands (name) VALUES (?)")->execute([$b]);

    $products = [
        ['Coca Cola',  'BV-001', $catIds['Beverages'], 90,  120, 0, 84, 10],
        ['Pepsi',      'BV-002', $catIds['Beverages'], 85,  120, 0, 40, 10],
        ['Sprite',     'BV-003', $catIds['Beverages'], 70,  100, 0, 30, 10],
        ['Water Bottle','BV-004',$catIds['Beverages'], 25,  50,  0, 60, 10],
        ['Lays Chips', 'SN-001', $catIds['Snacks'],     55,  80,  0, 25, 10],
        ['Kurkure',    'SN-002', $catIds['Snacks'],     40,  60,  0, 35, 10],
        ['Milk 500ml', 'DA-001', $catIds['Dairy'],      45,  60,  0, 30, 10],
        ['Bread',      'GR-001', $catIds['Grocery'],    35,  50,  0, 20, 10],
        ['Eggs (6pcs)','GR-002', $catIds['Grocery'],    50,  70,  0, 40, 10],
        ['Sugar 1kg',  'GR-003', $catIds['Grocery'],    80,  100, 0, 25, 5],
        ['Rice 1kg',   'GR-004', $catIds['Grocery'],    90,  120, 0, 50, 5],
    ];
    $ps = $pdo->prepare("INSERT INTO products (name,sku,category_id,purchase_price,sale_price,tax_rate,stock_qty,low_stock_alert) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($products as $p) $ps->execute($p);

    $pdo->prepare("INSERT INTO suppliers (name,phone) VALUES (?,?)")->execute(['Punjab Wholesale','0300-1234567']);

    $pdo->commit();
}

function get_setting(string $key, $default = ''): string {
    $stmt = get_db()->prepare("SELECT value FROM settings WHERE key=?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string)$row['value'] : (string)$default;
}

function set_setting(string $key, $value): void {
    get_db()->prepare("INSERT INTO settings (key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value")
        ->execute([$key, $value]);
}

function audit(string $action, string $summary = ''): void {
    get_db()->prepare("INSERT INTO audit_log (action,summary) VALUES (?,?)")->execute([$action, $summary]);
}

function money(float $n): string {
    return number_format($n, 0, '.', ',');
}

function next_invoice(string $prefix = 'INV'): string {
    return $prefix . '-' . date('ymdHis');
}

function paginate_params(int $default = 20): array {
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? $default);
    if (!in_array($perPage, [10,20,25,50,100], true)) $perPage = $default;
    return [$page, $perPage];
}

// ── Backup ─────────────────────────────────────────────────
function run_backup(string $kind = 'manual'): string {
    if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0777, true);
    $filename = 'smartpos_' . date('Y-m-d_His') . '.db';
    copy(DB_PATH, BACKUP_DIR . '/' . $filename);
    get_db()->prepare("INSERT INTO backup_log (filename,kind) VALUES (?,?)")->execute([$filename, $kind]);
    set_setting('last_backup_at', date('Y-m-d H:i:s'));

    $files = glob(BACKUP_DIR . '/smartpos_*.db');
    if ($files && count($files) > 30) {
        usort($files, fn($a,$b) => filemtime($a) <=> filemtime($b));
        foreach (array_slice($files, 0, count($files) - 30) as $f) @unlink($f);
    }
    return $filename;
}

function maybe_auto_backup(): void {
    $last  = get_setting('last_backup_at');
    $today = date('Y-m-d');
    if ($last === '' || substr($last, 0, 10) !== $today) {
        run_backup('auto');
    }
}

// ── Image resize/compress: every product image ends up the same size ──
function resize_and_save_image(string $srcPath, string $ext, string $destPath, int $targetSize = 400): bool {
    if (!extension_loaded('gd')) return copy($srcPath, $destPath);
    switch ($ext) {
        case 'jpg': case 'jpeg': $src = @imagecreatefromjpeg($srcPath); break;
        case 'png':              $src = @imagecreatefrompng($srcPath);  break;
        case 'gif':              $src = @imagecreatefromgif($srcPath);  break;
        case 'webp':              $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false; break;
        default: $src = false;
    }
    if (!$src) return copy($srcPath, $destPath);

    $srcW = imagesx($src); $srcH = imagesy($src);
    $side = min($srcW, $srcH);
    $cropX = (int)(($srcW - $side) / 2);
    $cropY = (int)(($srcH - $side) / 2);

    $dst = imagecreatetruecolor($targetSize, $targetSize);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $targetSize, $targetSize, $side, $side);

    $ok = imagejpeg($dst, $destPath, 82);
    imagedestroy($src);
    imagedestroy($dst);
    return $ok;
}

function download_and_resize_image(string $url, string $destPath, int $targetSize = 400): bool {
    $data = @file_get_contents($url);
    if ($data === false) return false;
    $tmp = tempnam(sys_get_temp_dir(), 'posimg');
    file_put_contents($tmp, $data);
    $info = @getimagesize($tmp);
    $mimeToExt = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    $ext = $info && isset($mimeToExt[$info['mime']]) ? $mimeToExt[$info['mime']] : null;
    if (!$ext) { @unlink($tmp); return false; }
    $ok = resize_and_save_image($tmp, $ext, $destPath, $targetSize);
    @unlink($tmp);
    return $ok;
}
