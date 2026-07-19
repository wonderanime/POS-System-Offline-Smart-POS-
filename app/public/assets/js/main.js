(function () {
  var root = document.documentElement;
  var btn  = document.getElementById('themeBtn');
  var sun  = document.getElementById('ico-sun');
  var moon = document.getElementById('ico-moon');

  function applyTheme(t) {
    root.setAttribute('data-theme', t);
    localStorage.setItem('pos_theme', t);
    if (sun)  sun.style.display  = t === 'dark' ? 'inline-flex' : 'none';
    if (moon) moon.style.display = t === 'dark' ? 'none' : 'inline-flex';
  }
  applyTheme(localStorage.getItem('pos_theme') || 'dark');

  if (btn) btn.addEventListener('click', function () {
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    fetch('/api/save-theme', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ theme: next })
    }).catch(function(){});
  });

  var clk = document.getElementById('clock');
  function tick() {
    if (!clk) return;
    var d = new Date();
    clk.textContent = d.toLocaleDateString([], { weekday:'short', day:'2-digit', month:'short' }) + '  ·  ' + d.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
  }
  tick(); setInterval(tick, 10000);

  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (!el) return;
    if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault();
  });
})();
