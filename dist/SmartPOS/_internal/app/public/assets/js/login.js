(function () {
  var pin   = '';
  var dots  = document.querySelectorAll('.pin-dot');
  var errEl = document.getElementById('pin-error');

  function renderDots() { dots.forEach(function(d,i){ d.classList.toggle('filled', i < pin.length); }); }
  function shake() {
    var box = document.querySelector('.login-box');
    box.style.transform = 'translateX(-7px)';
    setTimeout(function(){ box.style.transform = 'translateX(7px)'; }, 70);
    setTimeout(function(){ box.style.transform = ''; }, 140);
  }
  function submit() {
    fetch('/login', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ pin: pin }) })
      .then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
      .then(function(res) {
        if (res.ok && res.d.ok) { window.location.href = res.d.redirect; }
        else { errEl.textContent = res.d.msg || 'Incorrect PIN'; shake(); pin=''; renderDots(); }
      })
      .catch(function(){ errEl.textContent = 'Server error'; pin=''; renderDots(); });
  }
  document.querySelectorAll('.key[data-k]').forEach(function(k) {
    k.addEventListener('click', function() {
      if (pin.length >= 4) return;
      errEl.textContent = ''; pin += k.getAttribute('data-k'); renderDots();
      if (pin.length === 4) setTimeout(submit, 100);
    });
  });
  var bksp = document.getElementById('key-back');
  if (bksp) bksp.addEventListener('click', function() { pin = pin.slice(0,-1); renderDots(); });
  document.addEventListener('keydown', function(e) {
    if (e.key >= '0' && e.key <= '9' && pin.length < 4) {
      errEl.textContent=''; pin += e.key; renderDots();
      if (pin.length === 4) setTimeout(submit, 100);
    } else if (e.key === 'Backspace') { pin = pin.slice(0,-1); renderDots(); }
  });
})();
