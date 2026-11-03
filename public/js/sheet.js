/* حفظ الدرجات تلقائياً في كشف المدرس */
(function () {
  const table = document.querySelector('[data-sheet]');
  if (!table) return;
  const url = table.dataset.saveUrl;
  const token = document.querySelector('meta[name="csrf-token"]').content;
  const status = document.getElementById('save-status');
  const counter = document.getElementById('completed-count');
  const timers = new Map();
  let inflight = 0;

  const toLatin = (v) => v.replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace('٫', '.').replace(',', '.').trim();
  const setStatus = (cls, text) => { status.className = 'status ' + cls; status.textContent = text; };

  function validate(input) {
    const v = toLatin(input.value);
    const max = parseFloat(input.dataset.max);
    if (v === '') return { ok: true, value: null };
    if (!/^\d+(\.\d+)?$/.test(v)) return { ok: false, msg: 'اكتب رقماً فقط' };
    const n = parseFloat(v);
    if (n < 0 || n > max) return { ok: false, msg: 'الدرجة من 0 إلى ' + max };
    return { ok: true, value: n };
  }

  async function save(input) {
    const r = validate(input);
    if (!r.ok) return;
    inflight++;
    input.classList.add('saving');
    setStatus('saving', 'جارٍ الحفظ…');
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({ student_id: +input.dataset.student, component_id: +input.dataset.component, score: r.value }),
      });
      const data = await res.json().catch(() => ({}));
      if (res.status === 419) throw new Error('انتهت الجلسة، أعد تحميل الصفحة');
      if (res.status === 423) { input.disabled = true; input.closest('td').classList.add('locked'); }
      if (!res.ok || !data.ok) throw new Error(data.message || 'لم تُحفظ الدرجة');
      const total = document.getElementById('total-' + input.dataset.student);
      total.textContent = data.total === null ? '' : (+data.total).toString();
      total.classList.toggle('full', !!data.complete);
      if (counter) counter.textContent = data.completed_count;
      input.classList.remove('saving');
      input.classList.add('saved');
      setTimeout(() => input.classList.remove('saved'), 1200);
      if (--inflight === 0) setStatus('saved', 'تم الحفظ ' + data.saved_at);
    } catch (e) {
      inflight--;
      input.classList.remove('saving');
      input.classList.add('invalid');
      setStatus('error', e.message);
    }
  }

  table.addEventListener('input', (e) => {
    const input = e.target.closest('input[data-component]');
    if (!input) return;
    const r = validate(input);
    input.classList.toggle('invalid', !r.ok);
    if (!r.ok) { setStatus('error', r.msg); return; }
    clearTimeout(timers.get(input));
    timers.set(input, setTimeout(() => save(input), 700));
  });

  table.addEventListener('keydown', (e) => {
    const input = e.target.closest('input[data-component]');
    if (!input || !['Enter', 'ArrowDown', 'ArrowUp'].includes(e.key)) return;
    e.preventDefault();
    const col = [...table.querySelectorAll('input[data-component="' + input.dataset.component + '"]')];
    const next = col[col.indexOf(input) + (e.key === 'ArrowUp' ? -1 : 1)];
    if (next) { next.focus(); next.select(); }
  });

  window.addEventListener('beforeunload', (e) => {
    if (inflight > 0 || [...timers.values()].length && document.querySelector('td.mark input.saving')) { e.preventDefault(); e.returnValue = ''; }
  });
})();
