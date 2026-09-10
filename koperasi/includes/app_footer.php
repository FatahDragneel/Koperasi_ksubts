  </div>
</div>
<div id="imgpop" onclick="this.classList.remove('show')">
  <div>
    <img id="imgpop-img" alt="">
    <p id="imgpop-cap"></p>
  </div>
</div>
<script>
document.addEventListener('contextmenu', function (e) {
  if (e.target.closest('.docs, #imgpop, figure.zoom') || (e.target.tagName === 'IMG' && String(e.target.src || '').indexOf('berkas.php') !== -1)) {
    e.preventDefault();
  }
});
document.addEventListener('dragstart', function (e) {
  if (e.target.closest('.docs, #imgpop, figure.zoom') || e.target.tagName === 'IMG') {
    e.preventDefault();
  }
});
function nilaiUrut(td) {
  var t = (td.getAttribute('data-sort') || td.textContent || '').replace(/\s+/g, ' ').trim();
  var rp = t.replace(/Rp\s?/gi, '').replace(/\./g, '').replace(',', '.');
  if (/^-?\d+(\.\d+)?$/.test(rp)) return { n: parseFloat(rp), s: t.toLowerCase() };
  var d = t.match(/(\d{1,2})\s+(Jan|Feb|Mar|Apr|Mei|Jun|Jul|Agu|Sep|Okt|Nov|Des)\s+(\d{4})/i);
  if (d) {
    var bl = {jan:1,feb:2,mar:3,apr:4,mei:5,jun:6,jul:7,agu:8,sep:9,okt:10,nov:11,des:12};
    return { n: parseInt(d[3],10)*10000 + (bl[d[2].toLowerCase()]||0)*100 + parseInt(d[1],10), s: t.toLowerCase() };
  }
  return { n: null, s: t.toLowerCase() };
}
function urutTabel(table, col) {
  var tb = table.tBodies[0];
  if (!tb) return;
  var rows = Array.prototype.slice.call(tb.querySelectorAll('tr'));
  var dir = table.getAttribute('data-dir') === 'asc' && table.getAttribute('data-col') === String(col) ? 'desc' : 'asc';
  table.setAttribute('data-dir', dir);
  table.setAttribute('data-col', String(col));
  rows.sort(function (a, b) {
    var ca = a.children[col], cb = b.children[col];
    if (!ca || !cb) return 0;
    var va = nilaiUrut(ca), vb = nilaiUrut(cb);
    var cmp = 0;
    if (va.n !== null && vb.n !== null) cmp = va.n - vb.n;
    else cmp = va.s < vb.s ? -1 : (va.s > vb.s ? 1 : 0);
    return dir === 'asc' ? cmp : -cmp;
  });
  rows.forEach(function (r) { tb.appendChild(r); });
  table.querySelectorAll('thead th').forEach(function (th, i) {
    th.classList.remove('sorted-asc', 'sorted-desc');
    if (i === col) th.classList.add(dir === 'asc' ? 'sorted-asc' : 'sorted-desc');
  });
}
document.querySelectorAll('.table-wrap table, .card table').forEach(function (table) {
  if (!table.tHead) return;
  table.querySelectorAll('thead th').forEach(function (th, i) {
    if (th.querySelector('a.th-sort')) return;
    if (!(th.textContent || '').trim()) return;
    th.classList.add('th-click');
    th.title = 'Klik untuk mengurutkan';
    th.addEventListener('click', function () { urutTabel(table, i); });
  });
});
function openModal(id){ document.getElementById(id).classList.add('show'); }
function closeModal(id){ document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-bg').forEach(el => el.addEventListener('click', e => { if(e.target===el) el.classList.remove('show'); }));
document.querySelectorAll('.docs figure, .docs img, figure.zoom').forEach(el => {
  el.addEventListener('click', function (e) {
    e.stopPropagation();
    const fig = this.closest('figure') || this;
    const img = fig.querySelector('img') || this;
    const src = fig.dataset.src || img.src;
    document.getElementById('imgpop-img').src = src;
    document.getElementById('imgpop-cap').textContent = fig.dataset.cap || (fig.querySelector('figcaption')||{}).textContent || '';
    document.getElementById('imgpop').classList.add('show');
  });
});
</script>
</body>
</html>
