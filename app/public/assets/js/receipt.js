function renderReceiptHTML(d) {
    var sym = d.currency_symbol || 'Rs';
    var fmt = function(n) { return sym + ' ' + Math.round(n || 0).toLocaleString(); };
    var logoHtml = d.logo_url
        ? '<img src="' + d.logo_url + '" class="rcpt-logo" alt="">'
        : '<div class="rcpt-logo rcpt-logo-fallback">' + (d.shop_name || 'S').charAt(0).toUpperCase() + '</div>';

    var html = '<div class="rcpt-card">';
    html += '<div class="rcpt-header">' + logoHtml + '<div>';
    html += '<div class="rcpt-biz-name">' + (d.shop_name || 'My Shop') + '</div>';
    if (d.shop_address) html += '<div class="rcpt-biz-line">' + d.shop_address + '</div>';
    if (d.shop_phone)   html += '<div class="rcpt-biz-line">Tel: ' + d.shop_phone + '</div>';
    html += '</div></div>';
    html += '<div class="rcpt-divider"></div>';
    html += '<div class="rcpt-meta">';
    html += '<div><span class="rcpt-label">Invoice</span><span class="rcpt-value">' + d.invoice_no + '</span></div>';
    html += '<div><span class="rcpt-label">Date</span><span class="rcpt-value">' + (d.date || new Date().toLocaleString()) + '</span></div>';
    if (d.customer) html += '<div><span class="rcpt-label">Customer</span><span class="rcpt-value">' + d.customer + '</span></div>';
    html += '</div>';

    html += '<table class="rcpt-items"><thead><tr><th>Item</th><th class="rcpt-num">Qty</th><th class="rcpt-num">Total</th></tr></thead><tbody>';
    (d.items || []).forEach(function(it) {
        html += '<tr><td>' + it.name + '</td><td class="rcpt-num">' + it.qty + '</td><td class="rcpt-num">' + fmt(it.total) + '</td></tr>';
    });
    html += '</tbody></table>';

    html += '<div class="rcpt-totals">';
    html += '<div class="rcpt-trow"><span>Subtotal</span><span>' + fmt(d.subtotal) + '</span></div>';
    if (d.discount > 0) html += '<div class="rcpt-trow rcpt-discount"><span>Discount</span><span>- ' + fmt(d.discount) + '</span></div>';
    if (d.tax > 0)      html += '<div class="rcpt-trow"><span>Tax</span><span>' + fmt(d.tax) + '</span></div>';
    html += '<div class="rcpt-trow rcpt-grand"><span>TOTAL</span><span>' + fmt(d.total) + '</span></div>';
    html += '</div>';

    html += '<div class="rcpt-payment-block">';
    html += '<div class="rcpt-trow"><span>Payment</span><span class="rcpt-pay-method">' + (d.payment_method||'cash').toUpperCase() + '</span></div>';
    html += '<div class="rcpt-trow"><span>Paid</span><span>' + fmt(d.paid_amount) + '</span></div>';
    if (d.change_due > 0) html += '<div class="rcpt-trow"><span>Change</span><span>' + fmt(d.change_due) + '</span></div>';
    html += '</div>';

    html += '<div class="rcpt-footer">' + (d.footer || 'Thank you for your visit!') + '</div>';
    html += '<div class="rcpt-powered">Powered by SmartPOS</div>';
    html += '</div>';
    return html;
}
