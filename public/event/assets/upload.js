/* KALCER Event: upload foto dokumentasi peserta, struk biaya, dan galeri panitia.
 * Foto peserta dikirim apa adanya (watermark dibuat peserta). Struk & galeri
 * dikompres di browser; kalau gambar tidak bisa diproses (mis. HEIC di Chrome),
 * file asli dikirim dan server yang memprosesnya. */
(function () {
  'use strict';
  var MAX_SIDE = 2000;

  function loadImage(file) {
    if (window.createImageBitmap) {
      return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return loadViaImg(file); });
    }
    return loadViaImg(file);
  }
  function loadViaImg(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('decode')); };
      img.src = url;
    });
  }

  function render(file, side, quality) {
    return loadImage(file).then(function (img) {
      var w = img.width, h = img.height, s = Math.min(1, (side || MAX_SIDE) / Math.max(w, h));
      w = Math.round(w * s); h = Math.round(h * s);
      var c = document.createElement('canvas');
      c.width = w; c.height = h;
      c.getContext('2d').drawImage(img, 0, 0, w, h);
      return new Promise(function (resolve) { c.toBlob(resolve, 'image/jpeg', quality || 0.85); });
    });
  }

  function mb(n) { return (n / 1048576).toFixed(1).replace('.', ','); }

  function send(form, fields, blob, filename, status) {
    return new Promise(function (resolve, reject) {
      var fd = new FormData();
      Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
      fd.append(form.dataset.fileField || 'foto', blob, filename);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.action);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.upload.onprogress = function (e) {
        if (e.lengthComputable && status.bar) status.bar.style.width = Math.round(e.loaded / e.total * 100) + '%';
      };
      xhr.onload = function () {
        var res = {};
        try { res = JSON.parse(xhr.responseText); } catch (e) { /* bukan JSON */ }
        if (xhr.status >= 200 && xhr.status < 300 && res.ok) resolve(res);
        else reject(new Error(res.error || (xhr.status === 413 ? 'File terlalu besar.' : 'Upload gagal (' + xhr.status + '). Coba lagi.')));
      };
      xhr.onerror = function () { reject(new Error('Koneksi terputus. Periksa sinyal lalu coba lagi.')); };
      xhr.send(fd);
    });
  }

  function baseFields(form) {
    var f = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.type === 'file' || el.type === 'button' || el.type === 'submit') return;
      if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) return;
      f[el.name] = el.value;
    });
    return f;
  }

  function statusUi(form) {
    var s = form.querySelector('.upload-status');
    var prog = form.querySelector('.progress');
    if (!prog) {
      prog = document.createElement('div');
      prog.className = 'progress';
      prog.innerHTML = '<i></i>';
      form.appendChild(prog);
    }
    return {
      el: s, bar: prog.firstChild,
      set: function (msg, err) { s.textContent = msg; s.classList.toggle('err', !!err); },
      lock: function (on) {
        Array.prototype.forEach.call(form.querySelectorAll('input[type=file], button'), function (b) { b.disabled = on; });
        form.querySelectorAll('label.btn').forEach(function (l) { l.toggleAttribute('aria-disabled', on); l.style.pointerEvents = on ? 'none' : ''; l.style.opacity = on ? '.5' : ''; });
      }
    };
  }

  // ---- foto dokumentasi peserta ----
  // Watermark tanggal/jam/lokasi dibuat peserta dengan aplikasi kamera, jadi foto dikirim
  // apa adanya (tanpa diubah di browser). Wajib centang konfirmasi; maks. 1 MB per foto.
  var konfirmasi = document.getElementById('konfirmasi-watermark');
  document.querySelectorAll('form.js-photo').forEach(function (form) {
    form.addEventListener('change', function (e) {
      if (e.target.type !== 'file' || !e.target.files.length) return;
      var st = statusUi(form);
      if (konfirmasi && !konfirmasi.checked) {
        st.set('Centang dulu konfirmasi bahwa foto sudah ber-watermark tanggal, jam, dan lokasi (di atas).', true);
        e.target.value = '';
        konfirmasi.focus();
        return;
      }
      var max = +form.dataset.max || 1048576;
      var all = Array.prototype.slice.call(e.target.files);
      var big = all.filter(function (f) { return f.size > max; });
      var files = all.filter(function (f) { return f.size <= max; });
      var tooBig = big.length ? big.map(function (f) { return f.name + ' (' + mb(f.size) + ' MB)'; }).join(', ') +
        ' melebihi ' + mb(max) + ' MB. Kecilkan foto dulu lalu unggah lagi.' : '';
      if (!files.length) {
        st.set(tooBig, true);
        e.target.value = '';
        return;
      }
      st.lock(true);
      var done = 0;
      files.reduce(function (p, file, i) {
        return p.then(function () {
          st.set('Mengunggah foto ' + (i + 1) + ' dari ' + files.length + '…');
          st.bar.style.width = '0';
          var fields = baseFields(form);
          fields.watermark = '1';
          return send(form, fields, file, file.name, st).then(function () { done++; });
        });
      }, Promise.resolve())
        .then(function () {
          if (tooBig) {
            st.set(done + ' foto terunggah. ' + tooBig, true);
            st.lock(false);
            e.target.value = '';
            setTimeout(function () { location.reload(); }, 6000);
            return;
          }
          st.set(done + ' foto terunggah. Memuat ulang…');
          location.reload();
        })
        .catch(function (err) {
          st.set(err.message + (done ? ' (' + done + ' foto sudah terunggah)' : ''), true);
          st.lock(false);
          e.target.value = '';
          if (done) setTimeout(function () { location.reload(); }, 2500);
        });
    });
  });

  // Kompres foto sampai di bawah batas: turunkan kualitas, lalu resolusi.
  var MAX_KONTEN = 2 * 1024 * 1024;
  function shrink(file, max) {
    var tries = [[2000, 0.85], [2000, 0.72], [1600, 0.72], [1280, 0.7]];
    function attempt(i) {
      return render(file, tries[i][0], tries[i][1]).then(function (blob) {
        if (blob.size <= max || i === tries.length - 1) return [blob, 'foto.jpg'];
        return attempt(i + 1);
      });
    }
    return attempt(0).catch(function () { return [file, file.name]; });
  }
  // ---- galeri panitia: banyak foto, dikompres, dikirim satu per satu ----
  document.querySelectorAll('form.js-gallery').forEach(function (form) {
    form.dataset.fileField = 'foto';
    form.addEventListener('submit', function (e) {
      var input = form.querySelector('input[type=file]');
      var files = input ? Array.prototype.slice.call(input.files) : [];
      if (!files.length || !form.checkValidity()) return;
      e.preventDefault();
      var st = statusUi(form);
      st.lock(true);
      var done = 0;
      files.reduce(function (p, file, i) {
        return p.then(function () {
          st.set('Mengunggah foto ' + (i + 1) + ' dari ' + files.length + '…');
          st.bar.style.width = '0';
          return shrink(file, MAX_KONTEN)
            .then(function (b) { return send(form, baseFields(form), b[0], b[1], st); })
            .then(function () { done++; });
        });
      }, Promise.resolve())
        .then(function () { st.set(done + ' foto terunggah. Memuat ulang…'); location.reload(); })
        .catch(function (err) {
          st.set(err.message + (done ? ' (' + done + ' foto sudah terunggah)' : ''), true);
          st.lock(false);
          if (done) setTimeout(function () { location.reload(); }, 2500);
        });
    });
  });

  // ---- struk biaya ----
  document.querySelectorAll('form.js-receipt').forEach(function (form) {
    form.dataset.fileField = 'struk';
    form.addEventListener('submit', function (e) {
      var input = form.querySelector('input[type=file]');
      var file = input && input.files[0];
      if (!file || !form.checkValidity()) return;
      e.preventDefault();
      var st = statusUi(form);
      st.lock(true);
      st.set('Memproses…');
      var prep = file.type === 'application/pdf'
        ? Promise.resolve([file, file.name])
        : render(file).then(function (b) { return [b, 'struk.jpg']; }, function () { return [file, file.name]; });
      prep.then(function (b) {
        st.set('Mengunggah…');
        return send(form, baseFields(form), b[0], b[1], st);
      }).then(function () {
        st.set('Tersimpan. Memuat ulang…');
        location.reload();
      }).catch(function (err) {
        st.set(err.message, true);
        st.lock(false);
      });
    });
  });
})();
