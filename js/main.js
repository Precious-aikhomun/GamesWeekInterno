// js/main.js
(function () {
  var el = document.getElementById('cd');
  if (!el) return;

  var t = Date.parse(el.dataset.target || '2027-01-18T12:00:00+01:00');
  var dd = document.getElementById('dd');
  var hh = document.getElementById('hh');
  var mm = document.getElementById('mm');
  var ss = document.getElementById('ss');

  function pad(n) {
    return String(n).padStart(2, '0');
  }

  function tick() {
    var s = Math.max(0, Math.floor((t - Date.now()) / 1000));
    if (dd) dd.textContent = pad(Math.floor(s / 86400));
    if (hh) hh.textContent = pad(Math.floor((s % 86400) / 3600));
    if (mm) mm.textContent = pad(Math.floor((s % 3600) / 60));
    if (ss) ss.textContent = pad(s % 60);
  }

  tick();
  setInterval(tick, 1000);
})();
