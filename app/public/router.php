<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri !== '/' && preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|webp|woff2?)$/', $uri)) {
    $file = __DIR__ . $uri;
    if (file_exists($file)) return false;
}

$routes = [
  'GET' => [
    '/'               => 'pages/index.php',
    '/login'          => 'pages/login.php',
    '/logout'         => 'pages/logout.php',
    '/dashboard'      => 'pages/dashboard.php',
    '/pos'            => 'pages/pos.php',
    '/sales'          => 'pages/sales_history.php',
    '/sale-view'      => 'pages/sale_view.php',
    '/sale-void'      => 'pages/sale_void.php',
    '/logs'           => 'pages/logs.php',
    '/products'       => 'pages/products.php',
    '/products-add'   => 'pages/products_add.php',
    '/categories'     => 'pages/categories.php',
    '/brands'         => 'pages/brands.php',
    '/stock'          => 'pages/stock.php',
    '/stock-adjust'   => 'pages/stock_adjust.php',
    '/purchases'      => 'pages/purchases.php',
    '/purchases-add'  => 'pages/purchases_add.php',
    '/suppliers'      => 'pages/suppliers.php',
    '/suppliers-add'  => 'pages/suppliers_add.php',
    '/customers'      => 'pages/customers.php',
    '/customers-add'  => 'pages/customers_add.php',
    '/expenses'       => 'pages/expenses.php',
    '/expenses-add'   => 'pages/expenses_add.php',
    '/reports'        => 'pages/reports.php',
    '/settings'       => 'pages/settings.php',
    '/backup'         => 'pages/backup.php',
  ],
  'POST' => [
    '/login'          => 'pages/login.php',
    '/products-add'   => 'pages/products_add.php',
    '/products-delete'=> 'pages/products_delete.php',
    '/products-bulk-delete'=> 'pages/products_bulk_delete.php',
    '/sale-void'      => 'pages/sale_void.php',
    '/categories'     => 'pages/categories.php',
    '/brands'         => 'pages/brands.php',
    '/stock-adjust'   => 'pages/stock_adjust.php',
    '/purchases-add'  => 'pages/purchases_add.php',
    '/suppliers-add'  => 'pages/suppliers_add.php',
    '/customers-add'  => 'pages/customers_add.php',
    '/expenses-add'   => 'pages/expenses_add.php',
    '/settings'       => 'pages/settings.php',
    '/backup'         => 'pages/backup.php',
    '/api/checkout'   => 'api/checkout.php',
    '/api/save-theme' => 'api/save_theme.php',
  ],
];

$method = $_SERVER['REQUEST_METHOD'];
$file   = $routes[$method][$uri] ?? null;

if ($file && file_exists(__DIR__ . '/' . $file)) {
    require __DIR__ . '/' . $file;
} else {
    http_response_code(404);
    echo '<h1 style="font-family:sans-serif;padding:40px">404 — Not Found</h1>';
}
