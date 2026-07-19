<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login();
$db = get_db(); $sym = get_setting('currency_symbol','Rs');

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$products = $db->query("
  SELECT p.*, c.name cat_name FROM products p
  LEFT JOIN categories c ON c.id=p.category_id
  WHERE p.active=1 ORDER BY p.name")->fetchAll();
$customers = $db->query("SELECT id,name FROM customers WHERE active=1 ORDER BY name")->fetchAll();

$activePage='pos'; $pageTitle='New Sale';
$topbarExtra = '
<div class="topbar-search">
  <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 5v14M7 5v14M11 5v14M14 5v14M18 5v14M21 5v14"/></svg>
  <input type="text" id="scan-input" placeholder="Search product by name or barcode (F2)" autocomplete="off">
</div>';
$inlineScript = "
window.POS_SYM   = " . json_encode($sym) . ";
window.POS_SHOP  = " . json_encode(get_setting('shop_name','My Shop')) . ";
window.POS_ADDR  = " . json_encode(get_setting('shop_address','')) . ";
window.POS_PHONE = " . json_encode(get_setting('shop_phone','')) . ";
window.POS_LOGO  = " . json_encode(get_setting('shop_logo','')) . ";
window.POS_FOOTER= " . json_encode('Thank you for your visit!') . ";
";
$pageScripts = ['/assets/js/receipt.js', '/assets/js/pos.js'];
include __DIR__.'/layout_top.php';
?>

<div class="pos-layout">
  <div>
    <div class="pill-row" id="pill-row">
      <button class="pill active" data-cat="all">All</button>
      <?php foreach($categories as $c): ?>
      <button class="pill" data-cat="<?=htmlspecialchars(strtolower($c['name']))?>"><?=htmlspecialchars($c['name'])?></button>
      <?php endforeach; ?>
    </div>

    <div class="product-grid" id="product-grid">
      <?php foreach($products as $p): $disabled = $p['stock_qty']<=0 ? 'disabled' : ''; ?>
      <button class="product-card" <?=$disabled?>
        data-id="<?=$p['id']?>" data-name="<?=htmlspecialchars($p['name'])?>" data-price="<?=$p['sale_price']?>"
        data-tax="<?=$p['tax_rate']?>" data-sku="<?=htmlspecialchars($p['sku']??'')?>" data-barcode="<?=htmlspecialchars($p['barcode']??'')?>"
        data-cat="<?=htmlspecialchars(strtolower($p['cat_name']??''))?>">
        <?php if($p['image_path']): ?>
        <img src="/assets/uploads/<?=htmlspecialchars(basename($p['image_path']))?>" alt="">
        <?php else: ?>
        <div class="no-img"><?=htmlspecialchars(strtoupper(substr($p['name'],0,1)))?></div>
        <?php endif; ?>
        <span class="product-name"><?=htmlspecialchars($p['name'])?></span>
        <span class="product-price"><?=$sym?> <?=number_format($p['sale_price'],0)?></span>
        <span class="product-stock"><?php
          if($p['stock_qty']<=0) echo '<span class="badge badge-red product-badge">Out</span>';
          elseif($p['stock_qty']<=$p['low_stock_alert']) echo rtrim(rtrim(number_format($p['stock_qty'],2),'0'),'.').' left';
          else echo 'Stock: '.rtrim(rtrim(number_format($p['stock_qty'],2),'0'),'.').' '.$p['unit'];
        ?></span>
      </button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="cart-panel">
    <div class="cart-head">
      <span>Current Sale</span>
      <button id="clear-cart" class="btn btn-xs btn-danger">Cancel</button>
    </div>

    <div class="cart-body" id="cart-body">
      <p class="empty-cart" id="cart-empty">Cart is empty.<br>Tap a product or scan a barcode.</p>
    </div>

    <div class="cart-foot">
      <?php if($customers): ?>
      <div style="margin-bottom:10px">
        <select id="cust-sel" class="form-input" style="font-size:12.5px;padding:8px 10px">
          <option value="">Walk-in Customer</option>
          <?php foreach($customers as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="total-row"><span>Subtotal</span><span id="out-sub"><?=$sym?> 0</span></div>
      <div class="total-row"><span>Discount</span><span>
        <input type="number" id="disc-input" value="0" min="0" step="0.01" class="mini-input">
      </span></div>
      <div class="total-row"><span>Tax</span><span id="out-tax"><?=$sym?> 0</span></div>
      <div class="total-row grand"><span>TOTAL</span><span id="out-total"><?=$sym?> 0</span></div>

      <div style="margin-top:10px">
        <label style="font-size:11px;color:var(--text3)">Paid Amount</label>
        <input type="number" id="paid-input" value="0" min="0" step="0.01" class="form-input" style="margin-top:4px">
      </div>
      <div class="total-row"><span>Change</span><span id="change-out" style="font-weight:700;color:var(--green)"><?=$sym?> 0</span></div>

      <div class="pay-grid">
        <button class="pay-btn active" data-method="cash">💵 Cash</button>
        <button class="pay-btn" data-method="card">💳 Card</button>
        <button class="pay-btn" data-method="bank">🏦 Bank</button>
        <button class="pay-btn" data-method="other">⋯ Other</button>
      </div>

      <button class="charge-btn" id="charge-btn" disabled>
        <span>Pay / Complete (F3)</span><span id="charge-lbl"><?=$sym?> 0</span>
      </button>
      <div style="display:flex;gap:8px;margin-top:8px">
        <button class="hold-btn" id="hold-btn" style="flex:1">Hold (F4)</button>
        <button class="hold-btn" id="resume-btn" style="flex:1">Resume</button>
      </div>
      <p id="checkout-msg" style="font-size:12px;text-align:center;margin-top:8px;min-height:16px"></p>
      <p style="font-size:10px;color:var(--text3);text-align:center;margin-top:2px">F2 Search &middot; F3 Complete &middot; F4 Hold &middot; Esc Cancel</p>
    </div>
  </div>
</div>

<div id="receipt-modal">
  <div class="receipt-wrap">
    <div class="receipt-actions no-print">
      <button class="btn btn-primary" onclick="POS_printReceipt()">🖨 Print Receipt</button>
      <button class="btn" onclick="POS_closeReceipt()">Close</button>
    </div>
    <div id="receipt-preview"></div>
  </div>
</div>

<?php include __DIR__.'/layout_bottom.php'; ?>
