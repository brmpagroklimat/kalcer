/*! KALCER: tombol "Beri masukan" untuk modul. Pasang:
 *  <script src="https://kalcer.agroklimat.id/event/assets/masukan.js" data-modul="banjir" defer></script>
 *  Opsi: data-posisi="kiri|kanan" (bawaan kanan), data-bawah="20" (jarak dari bawah, piksel).
 */
(function () {
  'use strict';
  var s = document.currentScript || document.querySelector('script[src*="/assets/masukan.js"]');
  if (!s || window.__kalcerMasukan) return;
  window.__kalcerMasukan = true;
  var modul = (s.getAttribute('data-modul') || '').toLowerCase().replace(/[^a-z,]/g, '');
  var kiri = s.getAttribute('data-posisi') === 'kiri';
  var bawah = parseInt(s.getAttribute('data-bawah'), 10);
  if (!(bawah >= 0)) bawah = 20;
  var base = s.src.replace(/\/assets\/masukan\.js.*$/, '');
  var href = base + '/masukan?src=tombol' + (modul ? '&modul=' + encodeURIComponent(modul) : '');
  var KEY = 'kalcer-masukan-tutup';
  try { if (sessionStorage.getItem(KEY)) return; } catch (e) { /* abaikan */ }

  function pasang() {
    var host = document.createElement('div');
    host.setAttribute('data-kalcer-masukan', '');
    var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
    var sisi = kiri ? 'left' : 'right';
    root.innerHTML =
      '<style>' +
      ':host,.w{all:initial}' +
      '.w{position:fixed;' + sisi + ':16px;bottom:' + bawah + 'px;z-index:2147483000;display:flex;align-items:center;gap:6px;' +
      'font:600 14px/1.2 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;flex-direction:' + (kiri ? 'row' : 'row-reverse') + '}' +
      'a{display:inline-flex;align-items:center;gap:8px;background:#1d4d63;color:#fff;text-decoration:none;padding:11px 16px;border-radius:999px;' +
      'box-shadow:0 6px 20px rgba(20,40,50,.28);transition:transform .2s,box-shadow .2s;font:inherit}' +
      'a:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(20,40,50,.34)}' +
      'a:focus-visible,button:focus-visible{outline:3px solid #f5c451;outline-offset:2px}' +
      'svg{width:18px;height:18px;flex:none}' +
      'button{all:unset;cursor:pointer;width:24px;height:24px;border-radius:50%;background:rgba(29,77,99,.85);color:#fff;font:700 15px/24px system-ui,sans-serif;text-align:center;opacity:0;transition:opacity .2s}' +
      '.w:hover button,button:focus-visible{opacity:1}' +
      '@media (hover:none){button{opacity:1}}' +
      '@media (max-width:480px){a{padding:10px 13px;font-size:13px}}' +
      '@media print{.w{display:none}}' +
      '</style>' +
      '<div class="w"><a target="_blank" rel="noopener" href="' + href.replace(/"/g, '&quot;') + '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M8.5 12h.01M12 12h.01M15.5 12h.01"/></svg>' +
      '<span>Beri masukan</span></a>' +
      '<button type="button" aria-label="Sembunyikan tombol masukan" title="Sembunyikan">×</button></div>';
    root.querySelector('button').addEventListener('click', function () {
      host.remove();
      try { sessionStorage.setItem(KEY, '1'); } catch (e) { /* abaikan */ }
    });
    document.body.appendChild(host);
  }
  if (document.body) pasang(); else document.addEventListener('DOMContentLoaded', pasang);
})();
