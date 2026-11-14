/* KALCER Event: interaksi kecil (salin link, konfirmasi, blok tanggal pendaftaran). */
(function () {
  'use strict';
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.querySelector(btn.dataset.copy);
      input.select();
      var done = function () { var t = btn.textContent; btn.textContent = 'Tersalin'; setTimeout(function () { btn.textContent = t; }, 1800); };
      if (navigator.clipboard) navigator.clipboard.writeText(input.value).then(done, function () { document.execCommand('copy'); done(); });
      else { document.execCommand('copy'); done(); }
    });
  });

  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  // Pendaftaran: tampilkan blok peserta hanya untuk tanggal yang dicentang.
  var toggles = document.querySelectorAll('[data-day-toggle]');
  function sync() {
    toggles.forEach(function (t) {
      var block = document.querySelector('.day-block[data-day="' + t.dataset.dayToggle + '"]');
      if (block) block.hidden = !t.checked;
    });
  }
  toggles.forEach(function (t) { t.addEventListener('change', sync); });
  if (toggles.length) sync();

  var errs = document.getElementById('form-errors');
  if (errs) errs.focus();
})();
