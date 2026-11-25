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

  // Menu panitia berkelompok: klik untuk buka/tutup (sentuh & keyboard), Esc atau klik di luar menutup.
  var groups = document.querySelectorAll('.navgroup');
  function closeGroups(except) {
    groups.forEach(function (g) { if (g !== except) { g.classList.remove('open'); g.querySelector('.navbtn').setAttribute('aria-expanded', 'false'); } });
  }
  groups.forEach(function (g) {
    var b = g.querySelector('.navbtn');
    b.addEventListener('click', function () {
      var open = !g.classList.contains('open');
      closeGroups(g);
      g.classList.toggle('open', open);
      b.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    g.addEventListener('focusout', function (e) { if (!g.contains(e.relatedTarget)) closeGroups(); });
  });
  if (groups.length) {
    document.addEventListener('click', function (e) { if (!e.target.closest('.navgroup')) closeGroups(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeGroups(); });
  }

  // Saring baris tabel sambil mengetik (mis. daftar undangan).
  document.querySelectorAll('input[data-filter]').forEach(function (inp) {
    var scope = document.querySelector(inp.dataset.filter);
    inp.addEventListener('input', function () {
      var q = inp.value.trim().toLowerCase();
      scope.querySelectorAll('tr[data-row]').forEach(function (tr) { tr.hidden = q !== '' && tr.textContent.toLowerCase().indexOf(q) === -1; });
    });
  });

  // QR (halaman QR & tombol modul): dibuat di browser, bisa diunduh sebagai PNG untuk slide.
  var qrBox = document.querySelector('[data-qr]');
  if (qrBox && window.qrcode) {
    var qr = window.qrcode(0, 'M');
    qr.addData(qrBox.dataset.qr);
    qr.make();
    var n = qr.getModuleCount(), cell = 12, pad = 4 * cell, size = n * cell + pad * 2;
    var cv = document.createElement('canvas');
    cv.width = cv.height = size;
    var cx = cv.getContext('2d');
    cx.fillStyle = '#fff'; cx.fillRect(0, 0, size, size);
    cx.fillStyle = '#1f2d33';
    for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (qr.isDark(r, c)) cx.fillRect(pad + c * cell, pad + r * cell, cell, cell);
    cv.setAttribute('role', 'img');
    cv.setAttribute('aria-label', 'QR menuju ' + qrBox.dataset.qr);
    qrBox.appendChild(cv);
    var unduh = document.querySelector('[data-qr-unduh]');
    if (unduh) unduh.href = cv.toDataURL('image/png');
  }

  // Centang semua + hitung jumlah tercentang (hapus banyak instansi).
  var cekSemua = document.querySelector('[data-cek-semua]');
  if (cekSemua) {
    var boxes = document.querySelectorAll('input[type=checkbox][name="' + cekSemua.dataset.cekSemua + '"]');
    var jml = document.querySelector('[data-jml-cek]');
    var hitung = function () { if (jml) jml.textContent = Array.prototype.filter.call(boxes, function (b) { return b.checked; }).length; };
    cekSemua.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = cekSemua.checked; }); hitung(); });
    boxes.forEach(function (b) { b.addEventListener('change', hitung); });
    hitung();
  }

  // Form masukan: tampilkan bagian penilaian hanya untuk modul yang dicentang.
  var modulCek = document.querySelectorAll('[data-modul-toggle]');
  if (modulCek.length) {
    var syncModul = function () {
      modulCek.forEach(function (c) {
        var blok = document.querySelector('.modul-blok[data-modul="' + c.value + '"]');
        if (blok) blok.hidden = !c.checked;
      });
    };
    modulCek.forEach(function (c) { c.addEventListener('change', syncModul); });
    syncModul();
  }
  // Instansi dari dropdown; "Lainnya" memunculkan isian bebas. Provinsi ikut terisi dari pilihan.
  var instPilih = document.querySelector('[data-instansi-pilih]');
  if (instPilih) {
    var instLain = document.querySelector('[data-instansi-lain]');
    instPilih.addEventListener('change', function () {
      var o = instPilih.options[instPilih.selectedIndex];
      instLain.hidden = instPilih.value !== '__lain';
      if (!instLain.hidden) instLain.focus();
      var prov = instPilih.form.querySelector('[name=provinsi]');
      if (o && o.dataset.provinsi && prov) prov.value = o.dataset.provinsi;
    });
  }
  var bersedia = document.querySelector('[data-bersedia]');
  if (bersedia) {
    var hpBox = document.querySelector('[data-hp-bersedia]');
    bersedia.addEventListener('change', function () { hpBox.hidden = !bersedia.checked; if (bersedia.checked) hpBox.querySelector('input').focus(); });
  }

  // Mode undangan: tanggal mengikuti nama instansi yang dipilih (ditetapkan panitia).
  var pilihU = document.querySelector('[data-undangan]');
  if (pilihU) {
    var boxU = document.querySelector('[data-jadwal-u]');
    var syncU = function () {
      var o = pilihU.options[pilihU.selectedIndex], t = (o && o.dataset.tanggal) || '';
      document.querySelectorAll('.day-block').forEach(function (b) { b.hidden = b.dataset.day !== t; });
      boxU.hidden = !t;
      if (t) boxU.querySelector('[data-jadwal-tgl]').textContent = o.dataset.label;
    };
    pilihU.addEventListener('change', syncU);
    syncU();
  }

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

  // Izin berbagi kontak hanya relevan bila peserta menginap.
  document.querySelectorAll('.share-contact').forEach(function (box) {
    var scope = box.closest('.person, form');
    var radios = scope.querySelectorAll('input[type=radio][name$="[menginap]"], input[type=radio][name="menginap"]');
    function sync() {
      var ya = Array.prototype.some.call(radios, function (r) { return r.checked && r.value === 'ya'; });
      box.hidden = !ya;
      if (!ya) box.querySelector('input').checked = false;
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
  });

  // Centang "pilih semua" (mis. publikasi kamar).
  document.querySelectorAll('[data-check-all]').forEach(function (all) {
    var boxes = function () { return all.form.querySelectorAll('input[type=checkbox][name="' + all.dataset.checkAll + '"]:not(:disabled)'); };
    var refresh = function () { var b = boxes(); all.checked = b.length > 0 && Array.prototype.every.call(b, function (x) { return x.checked; }); };
    all.addEventListener('change', function () { boxes().forEach(function (x) { x.checked = all.checked; }); });
    all.form.addEventListener('change', function (e) { if (e.target !== all) refresh(); });
    refresh();
  });

  // Tambah baris kosong (mis. Info untuk instansi di Pengaturan).
  document.querySelectorAll('[data-add-row]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var wrap = document.querySelector(btn.dataset.addRow);
      var row = wrap.lastElementChild.cloneNode(true);
      row.querySelectorAll('input, textarea').forEach(function (el) { el.value = ''; });
      wrap.appendChild(row);
      row.querySelector('input').focus();
    });
  });

  // Checklist penumpang shuttle: pilih semua per instansi + hitung kursi.
  document.querySelectorAll('.isi-form').forEach(function (form) {
    var cap = +form.dataset.kapasitas, out = form.querySelector('[data-count]'), kursi = form.querySelector('.kursi');
    function hitung() {
      var n = form.querySelectorAll('input[name="orang[]"]:checked').length;
      out.textContent = n;
      kursi.classList.toggle('over', n > cap);
      form.querySelectorAll('.grup').forEach(function (g) {
        var boxes = g.querySelectorAll('input[name="orang[]"]:not(:disabled)'), all = g.querySelector('[data-group-toggle]');
        all.disabled = boxes.length === 0;
        all.checked = boxes.length > 0 && Array.prototype.every.call(boxes, function (b) { return b.checked; });
      });
    }
    form.addEventListener('change', function (e) {
      if (e.target.matches('[data-group-toggle]')) {
        e.target.closest('.grup').querySelectorAll('input[name="orang[]"]:not(:disabled)').forEach(function (b) { b.checked = e.target.checked; });
      }
      hitung();
    });
    hitung();
  });

  // Form masukan per langkah: satu bagian per halaman, progres, simpan draf di perangkat.
  var wf = document.querySelector('[data-langkah-form]');
  if (wf) {
    var steps = Array.prototype.slice.call(wf.querySelectorAll('[data-langkah]'));
    var kunci = 'kalcer-masukan:' + location.pathname + location.search;
    var store = function (fn) { try { return fn(window.localStorage); } catch (e) { return null; } };
    var errBox = document.getElementById('form-errors');
    var skip = { _csrf: 1, _stamp: 1, sumber: 1, website: 1, 'modul[]': 1 };

    // Draf: simpan isian supaya aman saat berpindah tab membuka modul (HP kadang memuat ulang halaman).
    var simpan = function () {
      var d = {};
      Array.prototype.forEach.call(wf.elements, function (el) {
        if (!el.name || skip[el.name] || el.type === 'hidden' || el.type === 'submit' || el.type === 'button') return;
        if ((el.type === 'radio' || el.type === 'checkbox')) { if (el.checked) d[el.name] = el.value; else if (el.type === 'checkbox' && !(el.name in d)) d[el.name] = null; return; }
        d[el.name] = el.value;
      });
      d.__langkah = cur;
      store(function (ls) { ls.setItem(kunci, JSON.stringify(d)); });
    };
    var pulih = function () {
      var raw = store(function (ls) { return ls.getItem(kunci); });
      if (!raw) return 0;
      var d; try { d = JSON.parse(raw); } catch (e) { return 0; }
      Array.prototype.forEach.call(wf.elements, function (el) {
        if (!el.name || !(el.name in d) || skip[el.name] || el.type === 'hidden') return;
        var v = d[el.name];
        if (el.type === 'radio') el.checked = v !== null && el.value === v;
        else if (el.type === 'checkbox') el.checked = v !== null && el.value === v;
        else if (v !== null) el.value = v;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      });
      return Math.min(+d.__langkah || 0, steps.length - 1);
    };

    // Kepala: "Langkah n dari N" + bar bersegmen.
    var head = document.createElement('div');
    head.className = 'langkah-head';
    head.innerHTML = '<div class="langkah-info"><span><b data-l-no></b> <span data-l-judul></span></span>' +
      '<button type="button" class="langkah-mode" data-l-mode></button></div><div class="langkah-bar" aria-hidden="true"></div>';
    wf.insertBefore(head, steps[0]);
    var bar = head.querySelector('.langkah-bar');
    steps.forEach(function () { bar.appendChild(document.createElement('i')); });

    var aksi = wf.querySelector('.actions');
    var kembaliAkhir = document.createElement('button');
    kembaliAkhir.type = 'button'; kembaliAkhir.className = 'btn secondary'; kembaliAkhir.textContent = '← Kembali';
    aksi.classList.add('langkah-nav');
    aksi.insertBefore(kembaliAkhir, aksi.firstChild);
    var hint = aksi.querySelector('.hint');
    if (hint) aksi.parentNode.insertBefore(hint, aksi.nextSibling);

    steps.forEach(function (st, i) {
      if (i === steps.length - 1) { st.appendChild(aksi); return; }
      var nav = document.createElement('div');
      nav.className = 'langkah-nav';
      nav.innerHTML = (i ? '<button type="button" class="btn secondary" data-kembali>← Kembali</button>' : '') +
        '<button type="button" class="btn" data-lanjut>' + (i ? 'Lanjut →' : 'Mulai menilai →') + '</button>';
      st.appendChild(nav);
    });

    var cur = 0;
    var satuHalaman = store(function (ls) { return ls.getItem('kalcer-masukan-satu-halaman'); }) === '1';
    var tampil = function (i, gulir) {
      cur = i;
      steps.forEach(function (st, j) { st.hidden = !satuHalaman && j !== i; });
      head.querySelector('[data-l-no]').textContent = satuHalaman ? 'Semua pertanyaan' : 'Langkah ' + (i + 1) + ' dari ' + steps.length;
      head.querySelector('[data-l-judul]').textContent = satuHalaman ? '' : '· ' + steps[i].dataset.judul;
      head.querySelector('[data-l-mode]').textContent = satuHalaman ? 'Tampilkan per langkah' : 'Tampilkan semua';
      Array.prototype.forEach.call(bar.children, function (seg, j) { seg.className = satuHalaman ? 'done' : (j < i ? 'done' : (j === i ? 'now' : '')); });
      wf.querySelectorAll('.langkah-nav').forEach(function (n) { n.hidden = satuHalaman && n !== aksi; });
      kembaliAkhir.hidden = satuHalaman;
      if (gulir) window.scrollTo({ top: steps[i].getBoundingClientRect().top + window.scrollY - head.offsetHeight - 8, behavior: 'smooth' });
      simpan();
    };

    var cekLangkah = function (st) {
      var kurang = [];
      var radioKosong = function (name) { return !st.querySelector('[name="' + name + '"]:checked'); };
      st.querySelectorAll('.perlu-isi').forEach(function (e) { e.classList.remove('perlu-isi'); });
      var tandai = function (el, pesan) { if (el) el.classList.add('perlu-isi'); kurang.push(pesan); };
      if (!st.classList.contains('modul-blok') && st.querySelector('[name=provinsi]')) {
        var sel = st.querySelector('[name=instansi_pilih]'), teks = st.querySelector('[name=instansi]');
        if (sel ? (!sel.value || (sel.value === '__lain' && !teks.value.trim())) : (teks && !teks.value.trim())) tandai((sel || teks).closest('.field') || sel || teks, 'pilih atau isi instansi');
        if (radioKosong('peran')) tandai(st.querySelector('[name=peran]').closest('.field'), 'pilih peran');
        if (!st.querySelector('[name=provinsi]').value) tandai(st.querySelector('[name=provinsi]').closest('.field'), 'pilih provinsi');
      } else if (st.classList.contains('modul-blok')) {
        var slug = st.dataset.modul;
        if (radioKosong('m[' + slug + '][sesuai]')) tandai(st.querySelector('[name="m[' + slug + '][sesuai]"]').closest('.field'), 'jawab apakah isinya sesuai kondisi lapangan');
        var ui = Array.prototype.some.call(st.querySelectorAll('.ui-butir input:checked'), function (r) { return r.value !== ''; });
        if (!ui) tandai(st.querySelector('.ui-butir'), 'beri nilai minimal satu butir tampilan (pilih angka 1–5)');
      }
      var lama = st.querySelector('.langkah-err');
      if (lama) lama.remove();
      if (kurang.length) {
        var p = document.createElement('p');
        p.className = 'langkah-err'; p.setAttribute('role', 'alert');
        p.textContent = 'Sebelum lanjut, mohon ' + kurang.join(', ') + '.';
        st.querySelector('.langkah-nav').before(p);
        var first = st.querySelector('.perlu-isi');
        if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
      }
      return true;
    };

    wf.addEventListener('click', function (e) {
      var t = e.target.closest('[data-lanjut], [data-kembali]');
      if (t) {
        if (t.hasAttribute('data-lanjut')) { if (cekLangkah(steps[cur])) tampil(cur + 1, true); }
        else tampil(cur - 1, true);
      }
      if (e.target === kembaliAkhir) tampil(cur - 1, true);
      if (e.target.closest('[data-l-mode]')) {
        satuHalaman = !satuHalaman;
        store(function (ls) { ls.setItem('kalcer-masukan-satu-halaman', satuHalaman ? '1' : '0'); });
        tampil(cur, false);
      }
    });
    wf.addEventListener('change', simpan);
    wf.addEventListener('input', simpan);
    wf.addEventListener('submit', function () { store(function (ls) { ls.removeItem(kunci); }); });

    var mulai = 0;
    if (errBox) {
      // Kesalahan dari server: buka langkah yang bermasalah dan pindahkan pesannya ke sana.
      var pesan = errBox.textContent;
      mulai = 0;
      steps.forEach(function (st, i) { if (st.dataset.modul && pesan.indexOf(st.dataset.judul + ':') !== -1) mulai = i; });
      if (/HP\/WA/.test(pesan)) mulai = steps.length - 1;
      var h2 = steps[mulai].querySelector('h2');
      h2.parentNode.insertBefore(errBox, h2.nextSibling);
    } else {
      mulai = pulih();
    }
    tampil(mulai, false);
  }

  var errs = document.getElementById('form-errors');
  if (errs) errs.focus();
})();
