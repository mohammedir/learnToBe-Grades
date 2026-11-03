/* محرر أسئلة الاختبار: أنواع الأسئلة، المعادلات، الصيغ الكيميائية، الصور، المعاينة الحية */
(function () {
  const box = document.getElementById('questions');
  if (!box) return;
  const tpl = document.getElementById('q-tpl');
  const initial = JSON.parse(document.getElementById('quiz-initial').textContent || '[]');
  const UNITS = ['m', 'cm', 'km', 's', 'min', 'h', 'kg', 'g', 'N', 'J', 'W', 'Pa', 'm/s', 'm/s²', 'km/h', 'V', 'A', 'Ω', 'Hz', 'C', '°C', 'K', 'mol', 'mol/L', 'L', 'mL', 'cm²', 'm²', 'cm³', 'm³', '%'];
  let activeField = null; // آخر حقل كتب فيه المدرس داخل السؤال
  let activeCard = null;

  /* ---------- أدوات ---------- */
  const el = (html) => { const t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstElementChild; };
  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  function insertAtCaret(field, text) {
    if (!field) return;
    const s = field.selectionStart ?? field.value.length, e = field.selectionEnd ?? field.value.length;
    field.value = field.value.slice(0, s) + text + field.value.slice(e);
    field.focus();
    field.selectionStart = field.selectionEnd = s + text.length;
    field.dispatchEvent(new Event('input', { bubbles: true }));
  }
  let renderTimer;
  function preview(card) {
    clearTimeout(renderTimer);
    renderTimer = setTimeout(() => {
      const p = card.querySelector('.q-preview');
      const body = card.querySelector('.q-body').value;
      const hasMath = /\$|\\\(/.test(body) || card.querySelectorAll('.q-opt-text').length;
      p.hidden = !body.trim() || !hasMath;
      if (p.hidden) return;
      let html = '<div class="pv-label">معاينة كما يراها الطالب:</div><div class="pv-body">' + esc(body).replace(/\n/g, '<br>') + '</div>';
      const opts = [...card.querySelectorAll('.q-opt-text')].map((i) => i.value).filter(Boolean);
      if (opts.length) html += '<ol class="pv-opts">' + opts.map((o) => '<li>' + esc(o) + '</li>').join('') + '</ol>';
      p.innerHTML = html;
      if (window.renderMath) window.renderMath(p);
    }, 250);
  }

  /* ---------- مناطق الإجابة حسب النوع ---------- */
  function renderAnswer(card, q) {
    const a = card.querySelector('.q-answer');
    const type = card.dataset.type;
    const meta = q.meta || {};
    if (type === 'mcq') {
      a.innerHTML = '<div class="ans-label">الخيارات <span class="hint">· اضغط الدائرة بجانب الإجابة الصحيحة</span></div><div class="q-opt-list"></div><button class="btn sm q-add-opt" type="button">+ خيار</button>';
      const list = q.options && q.options.length ? q.options : ['', '', '', ''];
      list.forEach((t, i) => addOption(card, t, String(i) === String(q.correct)));
      a.querySelector('.q-add-opt').addEventListener('click', () => { if (card.querySelectorAll('.q-opt').length < 6) { addOption(card, '', false); rename(); } });
    } else if (type === 'tf') {
      a.innerHTML = '<div class="ans-label">الإجابة الصحيحة</div><div class="tf-pick"><label><input type="radio" class="q-correct" value="true"><span>✓ صح</span></label><label><input type="radio" class="q-correct" value="false"><span>✗ خطأ</span></label></div>';
      a.querySelectorAll('.q-correct').forEach((r) => (r.checked = r.value === String(q.correct)));
    } else if (type === 'number') {
      const v = q.value ?? meta.value ?? '', tol = q.tolerance ?? meta.tolerance ?? '', unit = q.unit ?? meta.unit ?? '';
      a.innerHTML = '<div class="ans-label">الإجابة الصحيحة</div><div class="num-row">'
        + '<label>القيمة<input type="text" class="q-value" dir="ltr" inputmode="decimal" placeholder="9.8 أو 3×10^8 أو 1/2"></label>'
        + '<label>هامش الخطأ ±<input type="text" class="q-tol" dir="ltr" inputmode="decimal" placeholder="0.1"></label>'
        + '<label>الوحدة<input type="text" class="q-unit" dir="ltr" list="units-list" placeholder="m/s²"></label></div>'
        + '<span class="hint">يُقبل جواب الطالب إن كان ضمن هامش الخطأ. الطالب يكتب الرقم فقط والوحدة تظهر بجانبه.</span>';
      a.querySelector('.q-value').value = v; a.querySelector('.q-tol').value = tol || ''; a.querySelector('.q-unit').value = unit;
    } else {
      const acc = q.accepted ?? (meta.accepted || []).join('\n'), model = q.model ?? meta.model ?? '';
      a.innerHTML = '<div class="ans-label">الإجابات المقبولة (اختياري) <span class="hint">· إجابة في كل سطر، تُصحَّح تلقائياً إن طابقت</span></div>'
        + '<textarea class="q-accepted" rows="2" placeholder="الميتوكوندريا\nالمتقدرات"></textarea>'
        + '<div class="ans-label">نموذج الإجابة (يراه الطالب بعد نشر النتائج)</div><textarea class="q-model" rows="2" placeholder="الإجابة النموذجية…"></textarea>'
        + '<span class="hint">✎ الإجابات التي لا تطابق تنتظر تصحيحك من صفحة النتائج، وتعطيها الدرجة التي تراها.</span>';
      a.querySelector('.q-accepted').value = acc; a.querySelector('.q-model').value = model;
    }
    a.querySelectorAll('textarea,input[type=text]').forEach(watchField(card));
    rename();
  }

  function addOption(card, text, checked) {
    const row = el('<div class="q-opt"><label class="opt-mark"><input type="radio" class="q-correct" aria-label="الإجابة الصحيحة"><span></span></label><input type="text" class="q-opt-text"><button type="button" class="icon-btn q-opt-del" aria-label="حذف الخيار">✕</button></div>');
    row.querySelector('.q-opt-text').value = text || '';
    row.querySelector('.q-correct').checked = !!checked;
    row.querySelector('.q-opt-del').addEventListener('click', () => { if (card.querySelectorAll('.q-opt').length > 2) { row.remove(); rename(); preview(card); } });
    watchField(card)(row.querySelector('.q-opt-text'));
    card.querySelector('.q-opt-list').appendChild(row);
  }

  const watchField = (card) => (f) => {
    f.addEventListener('focus', () => { activeField = f; activeCard = card; });
    f.addEventListener('input', () => preview(card));
  };

  /* ---------- بطاقة سؤال ---------- */
  function add(q, focus) {
    const card = tpl.content.firstElementChild.cloneNode(true);
    card.dataset.type = q.type || 'mcq';
    card.querySelector('.q-type-select').value = card.dataset.type;
    const body = card.querySelector('.q-body');
    body.value = q.body || '';
    card.querySelector('.q-points').value = q.points || 1;
    watchField(card)(body);
    autoGrow(body);

    // الصورة الحالية
    const path = q.image_path || '';
    card.querySelector('.q-image-path').value = path;
    if (path) showImage(card, q.image_url || ('/' + path));

    card.querySelector('.q-type-select').addEventListener('change', (e) => {
      const old = collectAnswer(card);
      card.dataset.type = e.target.value;
      renderAnswer(card, old);
    });
    card.querySelector('.q-del').addEventListener('click', () => { if (confirm('حذف هذا السؤال؟')) { card.remove(); rename(); } });
    card.querySelector('.q-up').addEventListener('click', () => { if (card.previousElementSibling) { box.insertBefore(card, card.previousElementSibling); rename(); } });
    card.querySelector('.q-down').addEventListener('click', () => { if (card.nextElementSibling) { box.insertBefore(card.nextElementSibling, card); rename(); } });
    card.querySelector('.q-points').addEventListener('input', total);

    card.querySelectorAll('[data-ins]').forEach((b) => b.addEventListener('click', () => insertAtCaret(activeCard === card && activeField ? activeField : body, b.dataset.ins)));
    card.querySelector('[data-tool="eq"]').addEventListener('click', () => openEq(activeCard === card && activeField ? activeField : body));
    card.querySelector('[data-tool="chem"]').addEventListener('click', () => openChem(activeCard === card && activeField ? activeField : body));
    const file = card.querySelector('.q-file');
    card.querySelector('[data-tool="image"]').addEventListener('click', () => file.click());
    file.addEventListener('change', () => {
      const f = file.files[0];
      if (!f) return;
      if (f.size > 4 * 1024 * 1024) { alert('حجم الصورة أكبر من 4 ميغابايت. اختر صورة أصغر.'); file.value = ''; return; }
      card.querySelector('.q-remove-image').value = '0';
      showImage(card, URL.createObjectURL(f));
    });
    card.querySelector('.q-img-remove').addEventListener('click', () => {
      file.value = ''; card.querySelector('.q-image-path').value = ''; card.querySelector('.q-remove-image').value = '1';
      card.querySelector('.q-image').hidden = true;
    });

    box.appendChild(card);
    renderAnswer(card, q);
    preview(card);
    if (focus) { body.focus(); card.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  }

  function showImage(card, src) {
    const w = card.querySelector('.q-image');
    w.querySelector('img').src = src;
    w.hidden = false;
  }
  function autoGrow(t) { const f = () => { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight + 2, 400) + 'px'; }; t.addEventListener('input', f); setTimeout(f); }

  function collectAnswer(card) {
    const g = (s) => card.querySelector(s)?.value;
    return {
      options: [...card.querySelectorAll('.q-opt-text')].map((i) => i.value),
      correct: card.querySelector('.q-correct:checked')?.value,
      value: g('.q-value'), tolerance: g('.q-tol'), unit: g('.q-unit'),
      accepted: g('.q-accepted'), model: g('.q-model'),
    };
  }

  /* أسماء الحقول حسب الترتيب الحالي */
  function rename() {
    box.querySelectorAll('.q-card').forEach((card, i) => {
      const p = 'questions[' + i + ']';
      card.querySelector('.q-num').textContent = 'السؤال ' + (i + 1);
      const set = (sel, name) => card.querySelectorAll(sel).forEach((f) => (f.name = p + name));
      set('.q-body', '[body]'); set('.q-points', '[points]'); set('.q-file', '[image]');
      set('.q-image-path', '[image_path]'); set('.q-remove-image', '[remove_image]');
      set('.q-type-select', '[type]'); set('.q-correct', '[correct]'); set('.q-opt-text', '[options][]');
      set('.q-value', '[value]'); set('.q-tol', '[tolerance]'); set('.q-unit', '[unit]');
      set('.q-accepted', '[accepted]'); set('.q-model', '[model]');
      if (card.dataset.type === 'mcq') card.querySelectorAll('.q-correct').forEach((r, j) => (r.value = String(j)));
    });
    total();
  }
  function total() {
    const pts = [...box.querySelectorAll('.q-points')].reduce((s, i) => s + (parseFloat(i.value) || 0), 0);
    document.getElementById('q-total').textContent = box.querySelectorAll('.q-card').length + ' سؤال · المجموع ' + pts + ' درجة';
  }

  document.querySelectorAll('[data-add]').forEach((b) => b.addEventListener('click', () => add({ type: b.dataset.add }, true)));
  (initial.length ? initial : [{ type: 'mcq' }]).forEach((q) => add(q, false));
  const dl = document.createElement('datalist'); dl.id = 'units-list'; dl.innerHTML = UNITS.map((u) => '<option value="' + u + '">').join(''); document.body.appendChild(dl);

  document.getElementById('quiz-form').addEventListener('submit', (e) => {
    rename();
    const cards = [...box.querySelectorAll('.q-card')];
    for (let i = 0; i < cards.length; i++) {
      const c = cards[i], t = c.dataset.type, n = i + 1;
      let msg = '';
      if (!c.querySelector('.q-body').value.trim()) msg = 'اكتب نص السؤال ' + n;
      else if ((t === 'mcq' || t === 'tf') && !c.querySelector('.q-correct:checked')) msg = 'حدّد الإجابة الصحيحة في السؤال ' + n;
      else if (t === 'mcq' && [...c.querySelectorAll('.q-opt-text')].filter((x) => x.value.trim()).length < 2) msg = 'اكتب خيارين على الأقل في السؤال ' + n;
      else if (t === 'number' && !c.querySelector('.q-value').value.trim()) msg = 'اكتب الإجابة الرقمية الصحيحة للسؤال ' + n;
      if (msg) { e.preventDefault(); alert(msg); c.scrollIntoView({ behavior: 'smooth', block: 'center' }); c.classList.add('q-error'); setTimeout(() => c.classList.remove('q-error'), 2000); return; }
    }
  });

  /* ---------- نافذة المعادلات ---------- */
  const PALETTES = {
    math: [['\\frac{a}{b}', 'كسر'], ['x^{2}', 'أس'], ['x_{1}', 'دليل'], ['\\sqrt{x}', 'جذر'], ['\\sqrt[3]{x}', 'جذر تكعيبي'], ['\\pm', '±'], ['\\times', '×'], ['\\div', '÷'], ['\\neq', '≠'], ['\\leq', '≤'], ['\\geq', '≥'], ['\\approx', '≈'], ['\\pi', 'π'], ['\\infty', '∞'], ['|x|', 'قيمة مطلقة'], ['\\sum_{i=1}^{n}', 'مجموع'], ['\\int_{a}^{b}', 'تكامل'], ['\\lim_{x\\to 0}', 'نهاية'], ['\\log_{b}', 'لوغاريتم'], ['\\sin', 'sin'], ['\\cos', 'cos'], ['\\tan', 'tan'], ['^{\\circ}', 'درجة'], ['\\begin{pmatrix}a&b\\\\c&d\\end{pmatrix}', 'مصفوفة'], ['\\overline{AB}', 'قطعة'], ['\\angle', 'زاوية'], ['\\triangle', 'مثلث'], ['\\parallel', 'يوازي'], ['\\perp', 'يعامد'], ['\\in', '∈'], ['\\cup', '∪'], ['\\cap', '∩']],
    physics: [['\\vec{F}', 'متجه'], ['\\Delta', 'Δ'], ['v=\\frac{\\Delta x}{\\Delta t}', 'السرعة'], ['a=\\frac{\\Delta v}{\\Delta t}', 'التسارع'], ['F=ma', 'نيوتن الثاني'], ['E_k=\\frac{1}{2}mv^{2}', 'طاقة حركية'], ['E_p=mgh', 'طاقة وضع'], ['W=Fd\\cos\\theta', 'الشغل'], ['P=\\frac{W}{t}', 'القدرة'], ['V=IR', 'أوم'], ['\\rho=\\frac{m}{V}', 'الكثافة'], ['F=G\\frac{m_1m_2}{r^{2}}', 'الجذب'], ['\\lambda', 'λ'], ['\\theta', 'θ'], ['\\Omega', 'Ω'], ['\\mu', 'μ'], ['\\omega', 'ω'], ['\\text{m/s}^{2}', 'm/s²'], ['\\times10^{n}', '×10ⁿ'], ['\\propto', '∝'], ['\\rightarrow', '→']],
    greek: [['\\alpha', 'α'], ['\\beta', 'β'], ['\\gamma', 'γ'], ['\\delta', 'δ'], ['\\epsilon', 'ε'], ['\\theta', 'θ'], ['\\lambda', 'λ'], ['\\mu', 'μ'], ['\\pi', 'π'], ['\\rho', 'ρ'], ['\\sigma', 'σ'], ['\\tau', 'τ'], ['\\phi', 'φ'], ['\\omega', 'ω'], ['\\Delta', 'Δ'], ['\\Sigma', 'Σ'], ['\\Omega', 'Ω'], ['\\Phi', 'Φ']],
  };
  const eqDlg = document.getElementById('eq-dialog');
  const eqLatex = document.getElementById('eq-latex');
  const eqPrev = document.getElementById('eq-preview');
  const eqVisual = document.getElementById('eq-visual');
  let eqTarget = null, mf = null, mlTried = false;

  function drawPalette(name) {
    const pal = document.getElementById('eq-palette');
    pal.innerHTML = '';
    PALETTES[name].forEach(([tex, label]) => {
      const b = el('<button type="button" class="pal-btn" title="' + esc(label) + '"></button>');
      try { window.katex.render(tex.replace(/\{[a-z]\}/g, '{\\square}'), b, { throwOnError: false }); } catch (e) { b.textContent = label; }
      b.addEventListener('click', () => {
        if (mf) { mf.executeCommand(['insert', tex.replace(/\{([a-z0-9]+)\}/gi, '{#?}')]); mf.focus(); }
        else insertAtCaret(eqLatex, tex);
        updateEqPreview();
      });
      pal.appendChild(b);
    });
    document.querySelectorAll('[data-palette]').forEach((t) => t.classList.toggle('active', t.dataset.palette === name));
  }
  document.querySelectorAll('[data-palette]').forEach((t) => t.addEventListener('click', () => drawPalette(t.dataset.palette)));

  function updateEqPreview() {
    const tex = mf ? mf.value : eqLatex.value;
    if (mf) eqLatex.value = tex;
    try { window.katex.render(tex || '\\square', eqPrev, { throwOnError: false, displayMode: true }); } catch (e) { eqPrev.textContent = tex; }
  }
  eqLatex.addEventListener('input', () => { if (mf) mf.value = eqLatex.value; updateEqPreview(); });

  // المحرر المرئي (MathLive) يُحمَّل عند الحاجة؛ إن تعذّر يبقى حقل LaTeX مع المعاينة
  function loadMathLive() {
    if (mlTried) return; mlTried = true;
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/mathlive@0.98.6/dist/mathlive.min.js';
    s.onload = () => customElements.whenDefined('math-field').then(() => {
      mf = document.createElement('math-field');
      mf.setAttribute('math-virtual-keyboard-policy', 'manual');
      mf.value = eqLatex.value;
      mf.addEventListener('input', updateEqPreview);
      eqVisual.appendChild(mf);
      eqDlg.classList.add('has-visual');
      setTimeout(() => mf.focus(), 50);
    });
    document.head.appendChild(s);
  }

  function openEq(target) {
    eqTarget = target;
    eqLatex.value = ''; if (mf) mf.value = '';
    document.getElementById('eq-block').checked = false;
    drawPalette('math');
    updateEqPreview();
    eqDlg.showModal();
    loadMathLive();
    setTimeout(() => (mf || eqLatex).focus(), 50);
  }
  document.getElementById('eq-insert').addEventListener('click', () => {
    const tex = (mf ? mf.value : eqLatex.value).trim();
    if (tex) {
      const block = document.getElementById('eq-block').checked;
      insertAtCaret(eqTarget, block ? '\n$$' + tex + '$$\n' : ' $' + tex + '$ ');
    }
    eqDlg.close();
  });

  /* ---------- نافذة الصيغ الكيميائية ---------- */
  const chDlg = document.getElementById('chem-dialog');
  const chIn = document.getElementById('chem-input');
  const chPrev = document.getElementById('chem-preview');
  let chTarget = null;
  const CHEM = [['H2O', 'ماء'], ['CO2', 'ثاني أكسيد الكربون'], ['O2', 'أكسجين'], ['C6H12O6', 'جلوكوز'], ['NaCl', 'ملح'], ['H2SO4', 'حمض الكبريتيك'], ['ATP', 'ATP'], ['->', 'سهم'], ['<=>', 'اتزان'], ['^+', 'شحنة +'], ['^-', 'شحنة −'], ['^2+', 'شحنة 2+'], ['(aq)', 'محلول'], ['(s)', 'صلب'], ['(g)', 'غاز'], ['(l)', 'سائل'], ['->[\\text{ضوء}]', 'سهم بشرط']];
  CHEM.forEach(([v, label]) => {
    const b = el('<button type="button" class="pal-btn" title="' + esc(label) + '"></button>');
    try { window.katex.render('\\ce{' + v + '}', b, { throwOnError: false }); } catch (e) { b.textContent = v; }
    b.addEventListener('click', () => { insertAtCaret(chIn, v); });
    document.getElementById('chem-palette').appendChild(b);
  });
  function updateChem() { try { window.katex.render('\\ce{' + (chIn.value || 'H2O') + '}', chPrev, { throwOnError: false, displayMode: true }); } catch (e) { chPrev.textContent = chIn.value; } }
  chIn.addEventListener('input', updateChem);
  function openChem(target) { chTarget = target; chIn.value = ''; updateChem(); chDlg.showModal(); setTimeout(() => chIn.focus(), 50); }
  document.getElementById('chem-insert').addEventListener('click', () => {
    if (chIn.value.trim()) insertAtCaret(chTarget, ' $\\ce{' + chIn.value.trim() + '}$ ');
    chDlg.close();
  });

  document.querySelectorAll('dialog [data-close]').forEach((b) => b.addEventListener('click', () => b.closest('dialog').close()));
  // KaTeX يُحمّل مؤجلاً؛ نعيد رسم المعاينات عند جاهزيته
  window.addEventListener('load', () => box.querySelectorAll('.q-card').forEach(preview));
})();
