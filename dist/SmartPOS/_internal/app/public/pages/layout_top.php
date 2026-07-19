<?php
require_once dirname(__DIR__,2) . '/includes/pagination.php';
$activePage  = $activePage  ?? '';
$pageTitle   = $pageTitle   ?? 'SmartPOS';
$topbarExtra = $topbarExtra ?? '';
$user        = current_user();
$shop        = get_setting('shop_name', 'SmartPOS');
$sym         = get_setting('currency_symbol', 'Rs');

function navL(string $href, string $label, string $ico, string $ap, string $key): string {
    $cls = $ap === $key ? ' active' : '';
    return '<a href="' . $href . '" class="nav-link' . $cls . '">' . $ico . '<span>' . $label . '</span></a>';
}

$ico = [
  'dashboard' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>',
  'pos'       => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M2.5 3h2l2.4 12h10.2l1.9-8H6"/></svg>',
  'products'  => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>',
  'categories'=> '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
  'brands'    => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2l2.5 5 5.5.7-4 3.9.9 5.4L12 14.8 7.1 17l.9-5.4-4-3.9L9.5 7z"/></svg>',
  'stock'     => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 3h18v6H3z"/><path d="M3 9v12h18V9"/><path d="M9 13h6"/></svg>',
  'purchases' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="7" width="13" height="10" rx="1"/><path d="M14 10h4l3 3v4h-7"/><circle cx="6" cy="19" r="1.5"/><circle cx="17.5" cy="19" r="1.5"/></svg>',
  'suppliers' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21V8l9-5 9 5v13"/><path d="M9 21v-6h6v6"/></svg>',
  'customers' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 6-6h.5"/><circle cx="17" cy="14" r="3"/><path d="M14 21v-1a3 3 0 0 1 3-3h0a3 3 0 0 1 3 3v1"/></svg>',
  'sales'     => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>',
  'expenses'  => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><circle cx="17" cy="15" r="1.2"/></svg>',
  'reports'   => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="4" y1="20" x2="4" y2="11"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="20" y1="20" x2="20" y2="14"/></svg>',
  'settings'  => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 13a1.65 1.65 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.65 1.65 0 0 0-1.8-.3 1.65 1.65 0 0 0-1 1.5V19a2 2 0 1 1-4 0v-.1A1.65 1.65 0 0 0 9 17.4a1.65 1.65 0 0 0-1.8.3l-.1.1A2 2 0 1 1 4.3 15l.1-.1a1.65 1.65 0 0 0 .3-1.8 1.65 1.65 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.65 1.65 0 0 0 4.6 7a1.65 1.65 0 0 0-.3-1.8l-.1-.1A2 2 0 1 1 7 2.3l.1.1a1.65 1.65 0 0 0 1.8.3H9a1.65 1.65 0 0 0 1-1.5V1a2 2 0 1 1 4 0v.1a1.65 1.65 0 0 0 1 1.5h.1a1.65 1.65 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.65 1.65 0 0 0-.3 1.8V7a1.65 1.65 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.65 1.65 0 0 0-1.5 1z"/></svg>',
  'backup'    => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v12M7 11l5 5 5-5"/><path d="M4 19h16"/></svg>',
  'power'     => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v8"/><path d="M18.4 6.6a8 8 0 1 1-12.8 0"/></svg>',
  'sun'       => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"/></svg>',
  'moon'      => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/></svg>',
  'barcode'   => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 5v14M7 5v14M11 5v14M14 5v14M18 5v14M21 5v14"/></svg>',
  'edit'      => '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
  'trash'     => '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>',
  'plus'      => '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($shop) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<script>(function(){var t=localStorage.getItem('pos_theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
</head>
<body>
<div class="shell">

<aside class="sidebar">
  <div class="brand">
    <div class="brand-icon"><?= htmlspecialchars(substr($shop,0,1)) ?></div>
    <span class="brand-name"><?= htmlspecialchars($shop) ?></span>
  </div>
  <nav class="nav">
    <?= navL('/dashboard',  'Dashboard',  $ico['dashboard'],  $activePage, 'dashboard')  ?>
    <?= navL('/pos',        'POS',        $ico['pos'],        $activePage, 'pos')        ?>
    <?= navL('/products',   'Products',   $ico['products'],   $activePage, 'products')   ?>
    <?= navL('/categories', 'Categories', $ico['categories'], $activePage, 'categories') ?>
    <?= navL('/brands',     'Brands',     $ico['brands'],     $activePage, 'brands')     ?>
    <?= navL('/stock',      'Stock Inventory', $ico['stock'], $activePage, 'stock')      ?>
    <?= navL('/purchases',  'Purchase',   $ico['purchases'],  $activePage, 'purchases')  ?>
    <?= navL('/suppliers',  'Suppliers',  $ico['suppliers'],  $activePage, 'suppliers')  ?>
    <?= navL('/customers',  'Customers',  $ico['customers'],  $activePage, 'customers')  ?>
    <?= navL('/sales',      'Sales History', $ico['sales'],   $activePage, 'sales')      ?>
    <?= navL('/expenses',   'Expenses',   $ico['expenses'],   $activePage, 'expenses')   ?>
    <?= navL('/reports',    'Reports',    $ico['reports'],    $activePage, 'reports')    ?>
    <?= navL('/logs',       'Audit Log',  $ico['sales'],       $activePage, 'logs')       ?>
    <?= navL('/settings',   'Settings',   $ico['settings'],   $activePage, 'settings')   ?>
    <?= navL('/backup',     'Backup',     $ico['backup'],     $activePage, 'backup')     ?>
  </nav>
  <div class="nav-bottom">
    <a href="/logout" class="nav-link"><?= $ico['power'] ?><span>Log out</span></a>
  </div>
</aside>

<div class="main">
<header class="topbar">
  <div class="topbar-left">
    <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
    <?= $topbarExtra ?>
  </div>
  <div class="topbar-right">
    <button class="icon-btn" id="themeBtn" title="Toggle theme">
      <span id="ico-sun"><?= $ico['sun']  ?></span>
      <span id="ico-moon" style="display:none"><?= $ico['moon'] ?></span>
    </button>
    <span class="clock" id="clock"></span>
    <div class="user-pill">
      <div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($user['name'],0,1))) ?></div>
      <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
    </div>
  </div>
</header>
<div class="content">
