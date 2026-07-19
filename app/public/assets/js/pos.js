(function () {
  var cart = {};
  var sym = window.POS_SYM || 'Rs';
  var payMethod = 'cash';
  var heldSale = null;

  var grid      = document.getElementById('product-grid');
  var pillRow   = document.getElementById('pill-row');
  var scanIn    = document.getElementById('scan-input');
  var cartBody  = document.getElementById('cart-body');
  var emptyMsg  = document.getElementById('cart-empty');
  var custSel   = document.getElementById('cust-sel');
  var outSub    = document.getElementById('out-sub');
  var outTax    = document.getElementById('out-tax');
  var outTotal  = document.getElementById('out-total');
  var discIn    = document.getElementById('disc-input');
  var paidIn    = document.getElementById('paid-input');
  var changeOut = document.getElementById('change-out');
  var chargeBtn = document.getElementById('charge-btn');
  var chargeLbl = document.getElementById('charge-lbl');
  var msgEl     = document.getElementById('checkout-msg');

  function fmt(n) { return sym + ' ' + Math.round(n).toLocaleString(); }

  function addItem(card) {
    var id = card.dataset.id;
    if (!cart[id]) {
      cart[id] = { id: id, name: card.dataset.name, price: parseFloat(card.dataset.price), taxRate: parseFloat(card.dataset.tax || 0), qty: 0 };
    }
    cart[id].qty++;
    render();
  }
  function changeQty(id, d) { if (!cart[id]) return; cart[id].qty += d; if (cart[id].qty <= 0) delete cart[id]; render(); }
  function removeItem(id) { delete cart[id]; render(); }

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
        var it = cart[id];
        var line = it.price * it.qty;
        var taxAmt = line * (it.taxRate / 100);
        subtotal += line; totalTax += taxAmt;
        var div = document.createElement('div');
        div.className = 'cart-line';
        div.innerHTML =
          '<span class="cart-line-name">' + it.name + '</span>' +
          '<div class="qty-ctrl"><button class="qty-btn" data-act="dec" data-id="' + id + '">-</button>' +
          '<span class="qty-num">' + it.qty + '</span>' +
          '<button class="qty-btn" data-act="inc" data-id="' + id + '">+</button></div>' +
          '<span class="cart-line-total">' + fmt(line) + '</span>' +
          '<button class="remove-btn" data-act="remove" data-id="' + id + '">&times;</button>';
        cartBody.appendChild(div);
      });
    }

    var discAmt = parseFloat(discIn.value) || 0;
    var total = Math.max(0, subtotal - discAmt + totalTax);
    var paid  = parseFloat(paidIn.value) || 0;
    var change = Math.max(0, paid - total);

    outSub.textContent   = fmt(subtotal);
    outTax.textContent   = fmt(totalTax);
    outTotal.textContent = fmt(total);
    changeOut.textContent = fmt(change);
    chargeLbl.textContent = fmt(total);
  }

  grid.addEventListener('click', function(e) {
    var card = e.target.closest('.product-card');
    if (card && !card.disabled) addItem(card);
  });

  cartBody.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-act]');
    if (!btn) return;
    var id = btn.dataset.id, act = btn.dataset.act;
    if (act === 'inc') changeQty(id, 1);
    if (act === 'dec') changeQty(id, -1);
    if (act === 'remove') removeItem(id);
  });

  discIn.addEventListener('input', render);
  paidIn.addEventListener('input', render);

  pillRow.addEventListener('click', function(e) {
    var pill = e.target.closest('.pill');
    if (!pill) return;
    pillRow.querySelectorAll('.pill').forEach(function(p){ p.classList.remove('active'); });
    pill.classList.add('active');
    var cat = pill.dataset.cat;
    document.querySelectorAll('.product-card').forEach(function(c) {
      c.style.display = (cat === 'all' || c.dataset.cat === cat) ? '' : 'none';
    });
  });

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
      return (c.dataset.sku||'').toLowerCase() === q || (c.dataset.barcode||'').toLowerCase() === q;
    });
    if (match && !match.disabled) { addItem(match); scanIn.value=''; document.querySelectorAll('.product-card').forEach(function(c){c.style.display='';}); }
  });

  document.querySelectorAll('.pay-btn').forEach(function(b) {
    b.addEventListener('click', function() {
      document.querySelectorAll('.pay-btn').forEach(function(x){ x.classList.remove('active'); });
      b.classList.add('active');
      payMethod = b.dataset.method;
    });
  });

  var clearBtn = document.getElementById('clear-cart');
  if (clearBtn) clearBtn.addEventListener('click', function() {
    if (!Object.keys(cart).length) return;
    if (!confirm('Cancel this sale and clear the cart?')) return;
    cart = {}; discIn.value = 0; paidIn.value = 0; render();
  });

  var holdBtn = document.getElementById('hold-btn');
  if (holdBtn) holdBtn.addEventListener('click', function() {
    if (!Object.keys(cart).length) return;
    heldSale = { cart: JSON.parse(JSON.stringify(cart)), discount: discIn.value };
    msgEl.textContent = 'Sale held. You can resume it anytime before closing.';
    msgEl.style.color = 'var(--yellow)';
    cart = {}; discIn.value = 0; paidIn.value = 0; render();
  });

  var resumeBtn = document.getElementById('resume-btn');
  if (resumeBtn) resumeBtn.addEventListener('click', function() {
    if (!heldSale) { msgEl.textContent = 'No held sale to resume.'; msgEl.style.color = 'var(--text3)'; return; }
    cart = heldSale.cart; discIn.value = heldSale.discount; heldSale = null; render();
  });

  function showReceipt(data) {
    var modal = document.getElementById('receipt-modal');
    if (!modal) return;
    data.shop_name = window.POS_SHOP || 'My Shop';
    data.shop_address = window.POS_ADDR || '';
    data.shop_phone = window.POS_PHONE || '';
    data.logo_url = window.POS_LOGO || '';
    data.footer = window.POS_FOOTER || 'Thank you!';
    data.currency_symbol = sym;
    data.date = new Date().toLocaleString();
    document.getElementById('receipt-preview').innerHTML = renderReceiptHTML(data);
    modal.classList.add('open');
  }

  window.POS_closeReceipt = function() {
    var m = document.getElementById('receipt-modal');
    if (m) m.classList.remove('open');
    chargeBtn.disabled = false;
  };

  window.POS_printReceipt = function() {
    var prev = document.getElementById('receipt-preview').innerHTML;
    var w = window.open('', '_blank', 'width=440,height=650');
    var css = '<link rel="stylesheet" href="/assets/css/style.css">';
    w.document.write('<html><head>' + css + '</head><body>' + prev + '</body></html>');
    w.document.close();
    setTimeout(function(){ w.focus(); w.print(); w.close(); }, 300);
  };

  chargeBtn.addEventListener('click', function() {
    var ids = Object.keys(cart);
    if (!ids.length) return;
    var discAmt = parseFloat(discIn.value) || 0;
    var paid = parseFloat(paidIn.value) || 0;

    chargeBtn.disabled = true;
    msgEl.textContent = 'Processing...'; msgEl.style.color = 'var(--text2)';

    fetch('/api/checkout', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        items: ids.map(function(id){ return { product_id: id, qty: cart[id].qty }; }),
        discount: discAmt, paid_amount: paid, customer_id: custSel ? custSel.value : null,
        payment_method: payMethod
      })
    })
    .then(function(r){ return r.json().then(function(d){ return {ok:r.ok, d:d}; }); })
    .then(function(res) {
      if (res.ok && res.d.ok) {
        msgEl.textContent = '\u2713 ' + res.d.invoice_no + ' completed';
        msgEl.style.color = 'var(--green)';
        showReceipt(res.d);
        cart = {}; discIn.value = 0; paidIn.value = 0; render();
      } else {
        msgEl.textContent = '\u2717 ' + (res.d.msg || 'Checkout failed');
        msgEl.style.color = 'var(--red)';
        chargeBtn.disabled = false;
      }
    })
    .catch(function() { msgEl.textContent = 'Server error'; msgEl.style.color = 'var(--red)'; chargeBtn.disabled = false; });
  });

  /* Keyboard shortcuts matching reference: F2 checkout(focus/charge), F3 complete, F4 hold, Esc cancel */
  document.addEventListener('keydown', function(e) {
    if (e.key === 'F2') { e.preventDefault(); scanIn.focus(); }
    else if (e.key === 'F3') { e.preventDefault(); if (!chargeBtn.disabled) chargeBtn.click(); }
    else if (e.key === 'F4') { e.preventDefault(); if (holdBtn) holdBtn.click(); }
    else if (e.key === 'Escape') { e.preventDefault(); if (clearBtn) clearBtn.click(); }
  });

  render();
})();
