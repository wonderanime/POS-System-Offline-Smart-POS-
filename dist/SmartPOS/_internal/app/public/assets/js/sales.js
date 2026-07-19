/* SmartPOS — sales.js */
(function () {
  var cart         = {};
  var sym          = window.POS_SYM     || 'Rs';
  var taxDefault   = parseFloat(window.POS_TAX  || 0);
  var discounts    = window.POS_DISCS   || [];
  var payMethod    = 'cash';
  var transId      = '';
  var selectedDisc = null;
  var selectedCust = null;

  /* ── DOM refs ─────────────────────────────────────────────── */
  var grid      = document.getElementById('product-grid');
  var cartBody  = document.getElementById('cart-body');
  var emptyMsg  = document.getElementById('cart-empty');
  var scanIn    = document.getElementById('scan-input');
  var discSel   = document.getElementById('disc-sel');
  var custSel   = document.getElementById('cust-sel');
  var transRow  = document.getElementById('trans-row');
  var transIn   = document.getElementById('trans-id');
  var outSub    = document.getElementById('out-sub');
  var outDisc   = document.getElementById('out-disc');
  var outTax    = document.getElementById('out-tax');
  var outTotal  = document.getElementById('out-total');
  var chargeBtn = document.getElementById('charge-btn');
  var chargeLbl = document.getElementById('charge-lbl');
  var msgEl     = document.getElementById('checkout-msg');
  var noteIn    = document.getElementById('sale-note');

  /* ── Money fmt ────────────────────────────────────────────── */
  function fmt(n) { return sym + ' ' + Math.round(n).toLocaleString(); }

  /* ── Cart logic ───────────────────────────────────────────── */
  function addItem(card) {
    var id = card.dataset.id;
    if (!cart[id]) {
      cart[id] = {
        id:        id,
        name:      card.dataset.name,
        price:     parseFloat(card.dataset.price),
        costPrice: parseFloat(card.dataset.cost),
        taxRate:   parseFloat(card.dataset.tax || 0),
        qty:       0
      };
    }
    cart[id].qty++;
    render();
  }

  function changeQty(id, d) {
    if (!cart[id]) return;
    cart[id].qty += d;
    if (cart[id].qty <= 0) delete cart[id];
    render();
  }

  function removeItem(id) { delete cart[id]; render(); }

  /* ── Discount calculation ─────────────────────────────────── */
  function calcDisc(subtotal) {
    if (!selectedDisc) return 0;
    var d = selectedDisc;
    if (d.type === 'flat') {
      if (d.min_subtotal && subtotal < d.min_subtotal) return 0;
      return Math.min(parseFloat(d.value), subtotal);
    }
    if (d.type === 'percent') {
      if (d.min_subtotal && subtotal < d.min_subtotal) return 0;
      return subtotal * (parseFloat(d.value) / 100);
    }
    if (d.type === 'bogo' && d.product_id) {
      var it = cart[d.product_id];
      if (!it) return 0;
      var gs = (parseInt(d.buy_qty)||1) + (parseInt(d.get_qty)||0);
      return Math.floor(it.qty / gs) * (parseInt(d.get_qty)||0) * it.price;
    }
    if (d.type === 'combo' && d.product_id) {
      var ci = cart[d.product_id];
      if (ci && ci.qty >= (parseInt(d.buy_qty)||1)) return parseFloat(d.value);
    }
    return 0;
  }

  /* ── Render cart ──────────────────────────────────────────── */
  function render() {
    var ids = Object.keys(cart);
    cartBody.innerHTML = '';
    var subtotal = 0, totalTax = 0;

    if (ids.length === 0) {
      cartBody.appendChild(emptyMsg);
      chargeBtn.disabled = true;
    } else {
      chargeBtn.disabled = false;
      ids.forEach(function(id) {
        var it     = cart[id];
        var line   = it.price * it.qty;
        var taxAmt = line * (it.taxRate / 100);
        subtotal  += line;
        totalTax  += taxAmt;

        var div = document.createElement('div');
        div.className = 'cart-line';
        div.innerHTML =
          '<span class="cart-line-name" title="' + it.name + '">' + it.name +
          (it.taxRate > 0 ? ' <small>(' + it.taxRate + '%)</small>' : '') + '</span>' +
          '<div class="qty-ctrl">' +
            '<button class="qty-btn" data-act="dec" data-id="' + id + '">-</button>' +
            '<span class="qty-num">' + it.qty + '</span>' +
            '<button class="qty-btn" data-act="inc" data-id="' + id + '">+</button>' +
          '</div>' +
          '<span class="cart-line-total">' + fmt(line) + '</span>' +
          '<button class="remove-btn" data-act="remove" data-id="' + id + '">&times;</button>';
        cartBody.appendChild(div);
      });
    }

    var discAmt = calcDisc(subtotal);
    var total   = Math.max(0, subtotal - discAmt + totalTax);

    outSub.textContent   = fmt(subtotal);
    outDisc.textContent  = discAmt > 0 ? '- ' + fmt(discAmt) : fmt(0);
    outTax.textContent   = fmt(totalTax);
    outTotal.textContent = fmt(total);
    chargeLbl.textContent = fmt(total);
  }

  /* ── Cart events ──────────────────────────────────────────── */
  if (grid) grid.addEventListener('click', function(e) {
    var card = e.target.closest('.product-card');
    if (card && !card.disabled) addItem(card);
  });

  cartBody.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-act]');
    if (!btn) return;
    var id  = btn.dataset.id;
    var act = btn.dataset.act;
    if (act === 'inc')    changeQty(id,  1);
    if (act === 'dec')    changeQty(id, -1);
    if (act === 'remove') removeItem(id);
  });

  /* ── Category pills ───────────────────────────────────────── */
  var pillRow = document.getElementById('pill-row');
  if (pillRow) pillRow.addEventListener('click', function(e) {
    var pill = e.target.closest('.pill');
    if (!pill) return;
    pillRow.querySelectorAll('.pill').forEach(function(p){ p.classList.remove('active'); });
    pill.classList.add('active');
    var cat = pill.dataset.cat;
    document.querySelectorAll('.product-card').forEach(function(c) {
      c.style.display = (cat === 'all' || c.dataset.cat === cat) ? '' : 'none';
    });
  });

  /* ── Search / scan ────────────────────────────────────────── */
  if (scanIn) {
    scanIn.addEventListener('input', function() {
      var q = scanIn.value.toLowerCase();
      document.querySelectorAll('.product-card').forEach(function(c) {
        c.style.display = (!q || c.dataset.name.toLowerCase().includes(q) || (c.dataset.sku||'').toLowerCase().includes(q)) ? '' : 'none';
      });
    });

    scanIn.addEventListener('keydown', function(e) {
      if (e.key !== 'Enter') return;
      var q = scanIn.value.trim().toLowerCase();
      var match = Array.from(document.querySelectorAll('.product-card')).find(function(c) {
        return (c.dataset.sku || '').toLowerCase() === q || (c.dataset.barcode || '').toLowerCase() === q;
      });
      if (match && !match.disabled) {
        addItem(match);
        scanIn.value = '';
        document.querySelectorAll('.product-card').forEach(function(c){ c.style.display = ''; });
      }
    });
  }

  /* ── Payment method ───────────────────────────────────────── */
  document.querySelectorAll('.pay-btn').forEach(function(b) {
    b.addEventListener('click', function() {
      document.querySelectorAll('.pay-btn').forEach(function(x){ x.classList.remove('active'); });
      b.classList.add('active');
      payMethod = b.dataset.method;
      // Show transaction ID field for non-cash payments
      if (transRow) transRow.style.display = payMethod !== 'cash' ? 'flex' : 'none';
    });
  });

  if (transIn) transIn.addEventListener('input', function(){ transId = transIn.value.trim(); });

  /* ── Discount select ──────────────────────────────────────── */
  if (discSel) discSel.addEventListener('change', function() {
    selectedDisc = discounts.find(function(d){ return String(d.id) === discSel.value; }) || null;
    render();
  });

  /* ── Customer select ──────────────────────────────────────── */
  if (custSel) custSel.addEventListener('change', function() {
    selectedCust = custSel.value || null;
    // Enable/disable credit button
    var creditBtn = document.querySelector('.pay-btn[data-method="credit"]');
    if (creditBtn) creditBtn.disabled = !selectedCust;
  });

  /* ── Clear cart ───────────────────────────────────────────── */
  var clearBtn = document.getElementById('clear-cart');
  if (clearBtn) clearBtn.addEventListener('click', function() {
    if (Object.keys(cart).length === 0) return;
    if (!confirm('Clear the cart?')) return;
    cart = {}; render();
  });

  /* ── Checkout ─────────────────────────────────────────────── */
  if (chargeBtn) chargeBtn.addEventListener('click', function() {
    var ids = Object.keys(cart);
    if (!ids.length) return;

    chargeBtn.disabled = true;
    msgEl.textContent = 'Processing...';
    msgEl.className = '';

    fetch('/api/checkout', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        items:          ids.map(function(id){ return { product_id: id, qty: cart[id].qty }; }),
        discount_id:    selectedDisc ? selectedDisc.id : null,
        customer_id:    selectedCust,
        payment_method: payMethod,
        transaction_id: transId,
        note:           noteIn ? noteIn.value.trim() : ''
      })
    })
    .then(function(r){ return r.json().then(function(d){ return { ok: r.ok, d: d }; }); })
    .then(function(res) {
      if (res.ok && res.d.ok) {
        msgEl.textContent = '✓ ' + res.d.invoice_no + '  charged ' + fmt(res.d.total);
        msgEl.style.color = 'var(--green)';
        cart = {}; render();
        if (transRow) transRow.style.display = 'none';
        if (transIn)  transIn.value = '';
        if (noteIn)   noteIn.value  = '';
        if (discSel)  { discSel.value = ''; selectedDisc = null; }
        if (custSel)  { custSel.value = ''; selectedCust = null; }
        // Auto-show receipt
        showReceipt(res.d);
        if (window.POS_AUTO_PRINT) {
          setTimeout(function(){ POS_printReceipt('80'); }, 400);
        }
      } else {
        msgEl.textContent = '✗ ' + (res.d.msg || 'Checkout failed');
        msgEl.style.color = 'var(--red)';
        chargeBtn.disabled = false;
      }
    })
    .catch(function() {
      msgEl.textContent = 'Server error';
      msgEl.style.color = 'var(--red)';
      chargeBtn.disabled = false;
    });
  });

  /* ── Receipt ──────────────────────────────────────────────── */
  var lastReceiptData = null;

  function showReceipt(data) {
    lastReceiptData = data;
    var modal = document.getElementById('receipt-modal');
    if (!modal) return;
    var html = buildReceipt(data);
    document.getElementById('receipt-preview').innerHTML = html;
    modal.classList.add('open');
  }

  function receiptPlainText(data) {
    var lines = [
      window.POS_BIZ || 'My Shop',
      'Invoice: ' + data.invoice_no,
      ''
    ];
    (data.items || []).forEach(function(it) {
      lines.push(it.name + ' x' + it.qty + ' — ' + fmt(it.total));
    });
    lines.push('');
    lines.push('Total: ' + fmt(data.total));
    lines.push('Payment: ' + (data.payment_method || 'cash').toUpperCase() +
      (data.transaction_id ? ' (Trans ID: ' + data.transaction_id + ')' : ''));
    lines.push('');
    lines.push(window.POS_FOOTER || 'Thank you!');
    return lines.join('\n');
  }

  window.POS_shareWhatsApp = function() {
    if (!lastReceiptData) return;
    window.open('https://wa.me/?text=' + encodeURIComponent(receiptPlainText(lastReceiptData)), '_blank');
  };

  window.POS_shareEmail = function() {
    if (!lastReceiptData) return;
    var subject = encodeURIComponent('Receipt ' + lastReceiptData.invoice_no);
    var body = encodeURIComponent(receiptPlainText(lastReceiptData));
    window.location.href = 'mailto:?subject=' + subject + '&body=' + body;
  };

  function buildReceipt(data) {
    data.business_name = window.POS_BIZ || 'My Shop';
    data.business_address = window.POS_ADDR || '';
    data.business_phone = window.POS_PHONE || '';
    data.logo_url = window.POS_LOGO || '';
    data.footer = window.POS_FOOTER || 'Thank you!';
    data.signature_note = window.POS_SIGNATURE || 'Authorized Signature';
    data.secondary_currency = window.POS_SEC_CUR || '';
    data.secondary_rate = window.POS_SEC_RATE || 0;
    data.currency_symbol = sym;
    data.date = new Date().toLocaleString();
    return renderReceiptHTML(data);
  }

  // Receipt modal close / print buttons wired in inline script on page
  window.POS_closeReceipt = function() {
    var m = document.getElementById('receipt-modal');
    if (m) m.classList.remove('open');
    chargeBtn.disabled = false;
  };

  window.POS_printReceipt = function(size) {
    var prev = document.getElementById('receipt-preview').innerHTML;
    var w = window.open('', '_blank', 'width=440,height=650');
    var widthCss = size === '58' ? '58mm' : size === '80' ? '80mm' : '210mm';
    var fontCss  = size === '58' ? '8pt' : size === '80' ? '9pt' : '11pt';
    var css = '<link rel="stylesheet" href="/assets/css/style.css">' +
      '<style>body{margin:0;padding:4mm;font-size:' + fontCss + '} .rcpt-card{width:' + widthCss + ';padding:8px 6px}</style>';
    w.document.write('<html><head>' + css + '</head><body>' + prev + '</body></html>');
    w.document.close();
    setTimeout(function(){ w.focus(); w.print(); w.close(); }, 300);
  };

  render();

  /* ── Keyboard shortcuts ───────────────────────────────────── */
  document.addEventListener('keydown', function(e) {
    if (e.key === 'F2') { e.preventDefault(); scanIn && scanIn.focus(); }
    else if (e.key === 'F5') { e.preventDefault(); if (!chargeBtn.disabled) chargeBtn.click(); }
    else if (e.key === 'F6') { e.preventDefault(); clearBtn && clearBtn.click(); }
    else if (e.key === 'F7') { e.preventDefault(); var b = document.querySelector('.pay-btn[data-method="cash"]'); if (b) b.click(); }
    else if (e.key === 'F8') { e.preventDefault(); var b = document.querySelector('.pay-btn[data-method="card"]'); if (b) b.click(); }
    else if (e.key === 'F9') { e.preventDefault(); var b = document.querySelector('.pay-btn[data-method="easypaisa"]'); if (b) b.click(); }
  });
})();
