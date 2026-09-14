/* Koperasi App JS — AJAX tanpa reload + toast.
   Pakai: <form method="post" data-ajax="masuk|keluar|jabatan"
     data-tbody="#idTbody" data-count="#idCount" data-select="#idSelect" data-opt="remove|mark"> */
(function () {
  'use strict';

  function toastBox() {
    let box = document.getElementById('toastBox');
    if (!box) {
      box = document.createElement('div');
      box.id = 'toastBox';
      box.setAttribute('aria-live', 'polite');
      document.body.appendChild(box);
    }
    return box;
  }
  function toast(msg, ok) {
    const box = toastBox();
    const el = document.createElement('div');
    el.className = 'toast-msg ' + (ok === false ? 'err' : 'ok');
    const ikon = document.createElement('i');
    ikon.className = 'fa-solid ' + (ok === false ? 'fa-circle-exclamation' : 'fa-circle-check');
    const teks = document.createElement('span');
    teks.textContent = msg || (ok === false ? 'Gagal memproses.' : 'Berhasil.');
    el.appendChild(ikon);
    el.appendChild(teks);
    box.appendChild(el);
    setTimeout(function () { el.classList.add('tampil'); }, 10);
    setTimeout(function () { el.classList.remove('tampil'); setTimeout(function () { el.remove(); }, 350); }, 3200);
  }

  function bumpCount(sel, d) {
    if (!sel) return;
    const el = document.querySelector(sel);
    if (!el) return;
    const n = parseInt(el.textContent, 10);
    el.textContent = String((isNaN(n) ? 0 : n) + d);
    el.classList.remove('bump');
    void el.offsetWidth;
    el.classList.add('bump');
  }

  function kelolaKosong(tbody) {
    if (!tbody) return;
    const rows = tbody.querySelectorAll('tr:not(.empty-row)');
    let empty = tbody.querySelector('tr.empty-row');
    if (rows.length === 0 && !empty) {
      empty = document.createElement('tr');
      empty.className = 'empty-row';
      const td = document.createElement('td');
      td.colSpan = parseInt(tbody.dataset.emptyCols || '4', 10);
      td.textContent = tbody.dataset.emptyText || 'Belum ada data.';
      empty.appendChild(td);
      tbody.appendChild(empty);
    } else if (rows.length > 0 && empty) {
      empty.remove();
    }
  }

  function opsiSelect(sel, val) {
    const s = sel && document.querySelector(sel);
    return s ? s.querySelector('option[value="' + val + '"]') : null;
  }

  document.addEventListener('submit', function (e) {
    const form = e.target && e.target.closest ? e.target.closest('form[data-ajax]') : null;
    if (!form || e.defaultPrevented) return;
    e.preventDefault();
    const mode = form.dataset.ajax;
    const btn = form.querySelector('button[type="submit"], button:not([type])');
    const btnHtml = btn ? btn.innerHTML : null;
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Proses...';
    }
    let tbody = form.dataset.tbody ? document.querySelector(form.dataset.tbody) : null;
    if (!tbody && mode === 'keluar') {
      const tbl = form.closest('table');
      tbody = tbl ? tbl.querySelector('tbody') : null;
    }
    const fd = new FormData(form);
    fd.append('ajax', '1');
    fetch(form.action || window.location.href, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd,
      credentials: 'same-origin',
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.ok) {
          toast((res && res.msg) || 'Gagal memproses.', false);
          return;
        }
        toast(res.msg || 'Berhasil.');
        if (mode === 'masuk' && res.html && tbody) {
          tbody.insertAdjacentHTML('beforeend', res.html);
          kelolaKosong(tbody);
          bumpCount(form.dataset.count, 1);
          if (form.dataset.opt === 'mark') {
            const opt = opsiSelect(form.dataset.select, res.id);
            if (opt && opt.textContent.indexOf('(sudah di kelompok ini)') === -1) {
              opt.textContent += ' (sudah di kelompok ini)';
            }
          } else {
            const opt = opsiSelect(form.dataset.select, res.id);
            if (opt) opt.remove();
          }
          form.reset();
        } else if (mode === 'keluar') {
          const tr = form.closest('tr');
          if (tr) tr.remove();
          if (tbody) kelolaKosong(tbody);
          bumpCount(form.dataset.count, -1);
          if (form.dataset.opt === 'mark') {
            const opt = opsiSelect(form.dataset.select, res.id);
            if (opt) opt.textContent = opt.textContent.replace(' (sudah di kelompok ini)', '');
          } else if (res.opt && form.dataset.select) {
            const s = document.querySelector(form.dataset.select);
            if (s && !s.querySelector('option[value="' + res.opt.value + '"]')) {
              const opt = document.createElement('option');
              opt.value = res.opt.value;
              opt.textContent = res.opt.text;
              s.appendChild(opt);
            }
          }
        }
      })
      .catch(function () { toast('Jaringan bermasalah. Coba lagi.', false); })
      .finally(function () {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = btnHtml;
        }
      });
  });

  window.kopToast = toast;
})();
