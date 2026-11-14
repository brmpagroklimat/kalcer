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

  // ---------- validasi langsung saat mengisi ----------
  var kabkotaList = null;
  function loadKabkota(url) {
    if (kabkotaList) return Promise.resolve(kabkotaList);
    return fetch(url).then(function (r) { return r.json(); }).then(function (d) { kabkotaList = d; return d; });
  }
  var RULES = {
    hp: { clean: /\D/g, test: function (v) { return /^(0|62)\d{8,13}$/.test(v); },
      msg: 'Hanya angka, 10–15 digit, diawali 0 atau 62 (mis. 081234567890).' },
    nip: { clean: /\D/g, test: function (v) { return v === '' || /^\d{18}$/.test(v); },
      msg: 'NIP harus 18 digit angka. Kosongkan bila tidak punya NIP.' },
    email: { test: function (v) { return v === '' || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); },
      msg: 'Format email belum benar (mis. nama@instansi.go.id).' },
    kabkota: { test: function (v) { return !kabkotaList || kabkotaList.indexOf(v) !== -1; },
      msg: 'Pilih kabupaten/kota dari daftar: ketik minimal 3 huruf, lalu pilih.' }
  };
  function ruleOf(el) { return el.dataset.validate || (el.hasAttribute('data-kabkota') ? 'kabkota' : null); }
  function errEl(el) {
    var id = (el.id || el.name.replace(/\W+/g, '-')) + '-err';
    var e = document.getElementById(id);
    if (!e) {
      e = document.createElement('small');
      e.className = 'field-err'; e.id = id;
      var host = el.closest('.field') || el.parentNode;
      host.appendChild(e);
      el.setAttribute('aria-describedby', id);
    }
    return e;
  }
  function check(el) {
    var r = RULES[ruleOf(el)];
    if (!r || el.closest('[hidden]') || el.readOnly) return true;
    var v = el.value.trim();
    var ok = v === '' ? !el.required : r.test(v);
    el.setAttribute('aria-invalid', ok ? 'false' : 'true');
    errEl(el).textContent = ok ? '' : (v === '' ? 'Wajib diisi.' : r.msg);
    return ok;
  }
  document.addEventListener('input', function (e) {
    var el = e.target, r = RULES[ruleOf(el)];
    if (!r) return;
    if (r.clean) {
      var c = el.value.replace(r.clean, '');
      if (c !== el.value) el.value = c;
    }
    if (el.dataset.touched) check(el);
  });
  document.addEventListener('focusout', function (e) {
    var el = e.target;
    if (!RULES[ruleOf(el)]) return;
    if (ruleOf(el) === 'kabkota') return;   // ditangani combobox
    el.dataset.touched = '1';
    check(el);
  });
  document.querySelectorAll('form').forEach(function (f) {
    if (!f.querySelector('[data-validate], [data-kabkota]')) return;
    f.addEventListener('submit', function (e) {
      var bad = null;
      f.querySelectorAll('[data-validate], [data-kabkota]').forEach(function (el) {
        el.dataset.touched = '1';
        if (!check(el) && !bad) bad = el;
      });
      if (bad) { e.preventDefault(); e.stopImmediatePropagation(); bad.focus(); }
    }, true);
  });

  // ---------- combobox kabupaten/kota (muncul setelah 3 huruf) ----------
  document.querySelectorAll('[data-kabkota]').forEach(function (input) {
    var list = document.getElementById(input.getAttribute('aria-controls'));
    var items = [], active = -1;
    function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; input.removeAttribute('aria-activedescendant'); }
    function choose(v) { input.value = v; close(); input.dataset.touched = '1'; check(input); }
    function render() {
      var q = input.value.trim().toLowerCase();
      if (q.length < 3 || !kabkotaList) { close(); return; }
      // Urutan: nama diawali huruf yang diketik, lalu kata yang diawali, lalu sisanya.
      items = kabkotaList.map(function (k) {
        var nama = k.toLowerCase().replace(/^(kab\.|kota)\s+(adm\.\s+)?/, ''), all = k.toLowerCase();
        var score = nama.indexOf(q) === 0 ? 0 : (new RegExp('(^|[\\s,.(])' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).test(all) ? 1 : (all.indexOf(q) !== -1 ? 2 : 9));
        return [score, k];
      }).filter(function (x) { return x[0] < 9; }).sort(function (a, b) { return a[0] - b[0]; }).slice(0, 8).map(function (x) { return x[1]; });
      list.innerHTML = '';
      if (!items.length) {
        list.innerHTML = '<li class="combo-empty">Tidak ditemukan. Coba ejaan lain.</li>';
      }
      items.forEach(function (k, i) {
        var li = document.createElement('li');
        li.id = list.id + '-' + i; li.setAttribute('role', 'option'); li.textContent = k;
        li.addEventListener('mousedown', function (ev) { ev.preventDefault(); choose(k); });
        list.appendChild(li);
      });
      list.hidden = false; input.setAttribute('aria-expanded', 'true'); active = -1;
    }
    function move(d) {
      if (list.hidden || !items.length) return;
      active = (active + d + items.length) % items.length;
      Array.prototype.forEach.call(list.children, function (li, i) { li.setAttribute('aria-selected', i === active ? 'true' : 'false'); });
      input.setAttribute('aria-activedescendant', list.id + '-' + active);
    }
    input.addEventListener('focus', function () { loadKabkota(input.dataset.kabkota); });
    input.addEventListener('input', function () { loadKabkota(input.dataset.kabkota).then(render); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
      else if (e.key === 'Enter' && !list.hidden && active >= 0) { e.preventDefault(); choose(items[active]); }
      else if (e.key === 'Escape') close();
    });
    input.addEventListener('blur', function () {
      setTimeout(function () {
        close();
        // satu-satunya hasil yang cocok langsung dipakai
        if (kabkotaList && kabkotaList.indexOf(input.value) === -1 && items.length === 1 && input.value.trim().length >= 3) input.value = items[0];
        input.dataset.touched = '1'; check(input);
      }, 120);
    });
    loadKabkota(input.dataset.kabkota);
  });

  // ---------- peserta 1 = PIC instansi ----------
  document.querySelectorAll('[data-same-pic]').forEach(function (cb) {
    var person = cb.closest('.person');
    var picNama = document.querySelector('[data-pic="nama"]'), picHp = document.querySelector('[data-pic="hp"]');
    var nama = person.querySelector('[data-role="nama"]'), hp = person.querySelector('[data-validate="hp"]');
    function sync() {
      if (!cb.checked) return;
      nama.value = picNama.value; hp.value = picHp.value;
      if (hp.dataset.touched) check(hp);
    }
    cb.addEventListener('change', function () {
      nama.readOnly = hp.readOnly = cb.checked;
      if (cb.checked) sync();
    });
    [picNama, picHp].forEach(function (el) { el.addEventListener('input', sync); });
  });

  var errs = document.getElementById('form-errors');
  if (errs) errs.focus();
})();
