<?php
require_once dirname(__DIR__,2) . '/includes/config.php';
require_login();

$db  = get_db();
$sym = get_setting('currency_symbol','Rs');

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$products   = $db->query("
  SELECT p.*,c.name cat_name,
    CASE WHEN p.image_path IS NOT NULL AND p.image_path!='' THEN p.image_path
         WHEN p.image_url  IS NOT NULL AND p.image_url!=''  THEN p.image_url
         ELSE NULL END AS img
  FROM products p LEFT JOIN categories c ON c.id=p.category_id
  WHERE p.active=1 ORDER BY p.name")->fetchAll();

$customers  = $db->query("SELECT id,name,balance FROM customers WHERE active=1 ORDER BY name")->fetchAll();
$discounts  = $db->query("SELECT * FROM discounts WHERE active=1 ORDER BY name")->fetchAll();

$activePage='sales'; $pageTitle='New Sale';
$topbarExtra = '
<div class="topbar-search">
  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 5v14M7 5v14M11 5v14M14 5v14M18 5v14M21 5v14"/></svg>
  <input type="text" id="scan-input" placeholder="Scan barcode or search product (Enter to add)" autocomplete="off">
</div>';

$inlineScript = "
window.POS_SYM    = " . json_encode($sym) . ";
window.POS_TAX    = " . json_encode(get_setting('tax_rate_default','0')) . ";
window.POS_DISCS  = " . json_encode($discounts) . ";
window.POS_BIZ    = " . json_encode(get_setting('business_name','My Shop')) . ";
window.POS_ADDR   = " . json_encode(get_setting('business_address','')) . ";
window.POS_PHONE  = " . json_encode(get_setting('business_phone','')) . ";
window.POS_FOOTER = " . json_encode(get_setting('receipt_footer','Thank you!')) . ";
window.POS_LOGO   = " . json_encode(get_setting('receipt_logo_url','')) . ";
window.POS_SIGNATURE = " . json_encode(get_setting('shop_signature_note','Authorized Signature')) . ";
window.POS_SEC_CUR   = " . json_encode(get_setting('secondary_currency','')) . ";
window.POS_SEC_RATE  = " . json_encode((float)get_setting('secondary_currency_rate','0')) . ";
window.POS_AUTO_PRINT = " . json_encode(get_setting('auto_print_receipt','0') === '1') . ";
window.POS_MIN_CREDIT = " . json_encode((float)get_setting('min_credit_amount','0')) . ";
";
$pageScripts = ['/assets/js/receipt.js', '/assets/js/sales.js'];

include __DIR__.'/layout_top.php';
?>

<div class="pos-layout">

  <!-- ── Product panel ──────────────────────────────── -->
  <div>
    <!-- Category pills -->
    <div class="pill-row" id="pill-row">
      <button class="pill active" data-cat="all">All</button>
      <?php foreach($categories as $c): ?>
      <button class="pill" data-cat="<?=htmlspecialchars(strtolower($c['name']))?>"><?=htmlspecialchars($c['name'])?></button>
      <?php endforeach; ?>
    </div>

    <!-- Product grid -->
    <div class="product-grid" id="product-grid">
      <?php foreach($products as $p):
        $disabled = $p['stock_qty'] <= 0 ? 'disabled' : '';
      ?>
      <button class="product-card" <?=$disabled?>
        data-id="<?=$p['id']?>"
        data-name="<?=htmlspecialchars($p['name'])?>"
        data-price="<?=$p['price']?>"
        data-cost="<?=$p['cost_price']?>"
        data-tax="<?=$p['tax_rate']?>"
        data-sku="<?=htmlspecialchars($p['sku']??'')?>"
        data-barcode="<?=htmlspecialchars($p['barcode']??'')?>"
        data-cat="<?=htmlspecialchars(strtolower($p['cat_name']??''))?>">
        <?php if($p['img']): ?>
          <?php if(filter_var($p['img'],FILTER_VALIDATE_URL)): ?>
          <img src="<?=htmlspecialchars($p['img'])?>" alt="" onerror="this.outerHTML='<div class=&quot;no-img&quot;>'+<?=json_encode(htmlspecialchars(strtoupper(substr($p['name'],0,1))))?>+'</div>'">
          <?php else: ?>
          <img src="/assets/uploads/<?=htmlspecialchars(basename($p['img']))?>" alt="">
          <?php endif; ?>
        <?php else: ?>
        <div class="no-img"><?=htmlspecialchars(strtoupper(substr($p['name'],0,1)))?></div>
        <?php endif; ?>
        <span class="product-name"><?=htmlspecialchars($p['name'])?></span>
        <span class="product-price"><?=$sym?> <?=number_format($p['price'],0)?></span>
        <span class="product-stock"><?php
          if($p['stock_qty']<=0) echo '<span class="badge badge-red product-badge">Out</span>';
          elseif($p['stock_qty']<=$p['low_stock_threshold']) echo '<span class="badge badge-yellow product-badge">Low: '.rtrim(rtrim(number_format($p['stock_qty'],2),'0'),'.').'</span>';
          else echo 'Stock: '.rtrim(rtrim(number_format($p['stock_qty'],2),'0'),'.').' '.$p['unit'];
        ?></span>
        <?php if($p['tax_rate']>0): ?>
        <span class="badge badge-accent product-badge" style="top:auto;bottom:6px"><?=$p['tax_rate']?>% tax</span>
        <?php endif; ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Cart panel ─────────────────────────────────── -->
  <div class="cart-panel">
    <div class="cart-head">
      <span>Current Order</span>
      <button id="clear-cart" class="btn btn-xs btn-danger">Clear</button>
    </div>

    <div class="cart-body" id="cart-body">
      <p class="empty-cart" id="cart-empty">Cart is empty.<br>Tap a product or scan a barcode.</p>
    </div>

    <div class="cart-foot">
      <!-- Customer select -->
      <div class="cart-select-group">
        <label>Customer</label>
        <select id="cust-sel" class="form-input">
          <option value="">— Walk-in customer —</option>
          <?php foreach($customers as $c): ?>
          <option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?><?=$c['balance']>0?' (owes '.$sym.' '.money($c['balance']).')':''?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Discount select -->
      <div class="cart-select-group">
        <label>Discount</label>
        <select id="disc-sel" class="form-input">
          <option value="">— No discount —</option>
          <?php foreach($discounts as $d): ?>
          <option value="<?=$d['id']?>"><?=htmlspecialchars($d['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Totals -->
      <div class="total-row"><span>Subtotal</span><span id="out-sub"><?=$sym?> 0</span></div>
      <div class="total-row"><span>Discount</span><span id="out-disc" style="color:var(--red)"><?=$sym?> 0</span></div>
      <div class="total-row"><span>Tax</span><span id="out-tax"><?=$sym?> 0</span></div>
      <div class="total-row grand"><span>TOTAL</span><span id="out-total"><?=$sym?> 0</span></div>

      <!-- Payment methods -->
      <div class="pay-grid">
        <button class="pay-btn active" data-method="cash">Cash</button>
        <button class="pay-btn" data-method="card">Card</button>
        <button class="pay-btn" data-method="easypaisa">EasyPaisa</button>
        <button class="pay-btn" data-method="jazzcash">JazzCash</button>
        <button class="pay-btn" data-method="bank">Bank</button>
        <button class="pay-btn" data-method="raast">Raast</button>
        <button class="pay-btn credit-btn" data-method="credit" disabled>Credit (select customer)</button>
      </div>

      <!-- Transaction ID (shown for non-cash) -->
      <div class="trans-row" id="trans-row" style="display:none">
        <input type="text" id="trans-id" class="form-input" placeholder="Transaction ID / Ref No">
      </div>

      <!-- Note -->
      <input type="text" id="sale-note" class="form-input" placeholder="Order note (optional)">

      <!-- Charge button -->
      <button class="charge-btn" id="charge-btn" disabled>
        Charge · <span id="charge-lbl"><?=$sym?> 0</span>
      </button>
      <p id="checkout-msg" style="font-size:12px;text-align:center;margin-top:6px;min-height:16px"></p>
      <p style="font-size:10.5px;color:var(--text3);text-align:center;margin-top:4px">F2 Search &middot; F5 Charge &middot; F6 Clear &middot; F7 Cash &middot; F8 Card &middot; F9 EasyPaisa</p>
    </div>
  </div>
</div>

<!-- ── Receipt modal ──────────────────────────────────── -->
<div id="receipt-modal">
  <div class="receipt-wrap">
    <div class="receipt-actions no-print">
      <button class="btn btn-primary" onclick="POS_printReceipt('58')">🖨 58mm</button>
      <button class="btn btn-primary" onclick="POS_printReceipt('80')">🖨 80mm</button>
      <button class="btn btn-primary" onclick="POS_printReceipt('a4')">🖨 A4 PDF</button>
      <button class="btn" onclick="POS_shareWhatsApp()">📱 WhatsApp</button>
      <button class="btn" onclick="POS_shareEmail()">✉️ Email</button>
      <button class="btn" onclick="POS_closeReceipt()">Close</button>
    </div>
    <p class="no-print" style="font-size:11px;color:var(--text3);margin:-6px 0 10px">You can reprint or reshare this anytime from Sales History.</p>
    <div id="receipt-preview"></div>
  </div>
</div>

<?php include __DIR__.'/layout_bottom.php'; ?>
