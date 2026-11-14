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

  // Batas ukuran file di browser (mis. materi maks. 2 MB), sebelum upload dimulai.
  document.querySelectorAll('input[type=file][data-max]').forEach(function (input) {
    input.addEventListener('change', function () {
      var max = +input.dataset.max, big = Array.prototype.filter.call(input.files, function (f) { return f.size > max; });
      input.setCustomValidity(big.length ? big[0].name + ' berukuran ' + (big[0].size / 1048576).toFixed(1).replace('.', ',') + ' MB. Maksimal ' + Math.round(max / 1048576) + ' MB per file.' : '');
      if (big.length) input.reportValidity();
    });
  });

  var errs = document.getElementById('form-errors');
  if (errs) errs.focus();
})();
