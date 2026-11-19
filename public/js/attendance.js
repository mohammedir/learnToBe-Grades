/*
 * تسجيل حضور المدرسين بمسح رمز QR — يعمل بدون إنترنت.
 * كل تسجيل يُحفظ أولاً في الجهاز (localStorage) بوقته الحقيقي، ثم يُرفع للخادم عند توفر الاتصال.
 * على باقي الصفحات يعمل هذا الملف للرفع فقط إن وُجدت تسجيلات معلّقة.
 */
(function () {
  'use strict';
  const QKEY = 'ltb-att-queue', PKEY = 'ltb-att-points', FKEY = 'ltb-att-failed';
  const JSQR = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';
  const STATE_URL = '/attendance/state', SYNC_URL = '/attendance/sync';
  const app = document.getElementById('att-app');

  const load = (k, d) => { try { return JSON.parse(localStorage.getItem(k) || '') || d; } catch (e) { return d; } };
  const save = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} };
  const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2));
  const hhmm = (ms) => new Date(ms).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

  // ===== الرفع للخادم =====
  let syncing = false;
  async function sync() {
    const queue = load(QKEY, []);
    if (syncing || !queue.length || navigator.onLine === false) { render(); return; }
    syncing = true;
    try {
      const st = await fetch((app && app.dataset.stateUrl) || STATE_URL, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!st.ok || !(st.headers.get('content-type') || '').includes('json')) throw new Error('auth');
      const state = await st.json();
      if (state.points) save(PKEY, state.points.map((p) => ({ t: p.token, n: p.name })));
      const mine = queue.filter((r) => String(r.user) === String(state.user));
      if (!mine.length) return;
      const res = await fetch((app && app.dataset.syncUrl) || SYNC_URL, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': state.csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ now: Date.now(), records: mine.map((r) => ({ uuid: r.uuid, type: r.type, token: r.token, at: r.at, lat: r.lat, lng: r.lng, acc: r.acc })) }),
      });
      if (!res.ok) throw new Error('sync ' + res.status);
      const out = await res.json();
      const done = new Set(), failed = load(FKEY, []);
      (out.results || []).forEach((x) => {
        done.add(x.uuid);
        if (!x.ok) {
          const r = mine.find((m) => m.uuid === x.uuid);
          failed.push({ at: r ? r.at : Date.now(), type: r ? r.type : '', error: x.error });
        }
      });
      save(FKEY, failed.slice(-10));
      save(QKEY, load(QKEY, []).filter((r) => !done.has(r.uuid)));
      if (app && done.size) setTimeout(() => location.reload(), 1200); // تحديث السجل من الخادم
    } catch (e) {
      // يبقى في الجهاز وتُعاد المحاولة لاحقاً
    } finally {
      syncing = false;
      render();
    }
  }

  window.addEventListener('online', () => { render(); sync(); });
  window.addEventListener('offline', render);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });
  setInterval(sync, 60000);

  // تجهيز صفحة الحضور للعمل بدون إنترنت (مرة كل 6 ساعات)
  function prime() {
    if (navigator.onLine === false) return;
    const last = +load('ltb-att-primed', 0);
    if (Date.now() - last < 3 * 3600e3) return;
    save('ltb-att-primed', Date.now());
    // رموز QR الفعّالة: للتحقق من الرمز عند المسح بدون إنترنت
    fetch(STATE_URL, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then((r) => (r.ok && (r.headers.get('content-type') || '').includes('json') ? r.json() : null))
      .then((st) => { if (st && st.points) save(PKEY, st.points.map((p) => ({ t: p.token, n: p.name }))); })
      .catch(() => {});
    // صفحة الحضور وصفحة «بدون إنترنت» وقارئ الرموز في ذاكرة التطبيق
    if (navigator.serviceWorker && navigator.serviceWorker.controller) navigator.serviceWorker.controller.postMessage({ type: 'prime-attendance' });
  }

  if (!app) { sync(); prime(); return; } // باقي الصفحات: رفع فقط

  // ===== صفحة التسجيل =====
  const $ = (id) => document.getElementById(id);
  const embedded = JSON.parse(app.dataset.points || '[]');
  if (embedded.length) save(PKEY, embedded);

  function render() {
    if (!app) return;
    const on = navigator.onLine !== false, net = $('att-net');
    net.textContent = on ? net.dataset.on : net.dataset.off;
    net.classList.toggle('off', !on);
    const q = load(QKEY, []).filter((r) => String(r.user) === app.dataset.user);
    $('att-pending').hidden = !q.length;
    $('pending-n').textContent = q.length;
    $('pending-list').innerHTML = q.map((r) => '<li>' + (r.type === 'in' ? 'حضور' : 'مغادرة') + ' · <span dir="ltr">' + new Date(r.at).toLocaleDateString('en-GB') + ' ' + hhmm(r.at) + '</span></li>').join('');
    const f = load(FKEY, []);
    $('att-failed').hidden = !f.length;
    if (f.length) {
      $('att-failed').innerHTML = '<b>لم يُقبل بعض التسجيلات:</b><br>' + f.map((x) => (x.type === 'in' ? 'حضور ' : 'مغادرة ') + hhmm(x.at) + ' — ' + x.error).join('<br>') + ' <button class="btn sm" type="button" id="clear-failed">إخفاء</button>';
      $('clear-failed').onclick = () => { save(FKEY, []); render(); };
    }
    // أوقات اليوم من التسجيلات المعلّقة
    const today = new Date().toDateString();
    q.filter((r) => new Date(r.at).toDateString() === today).forEach((r) => {
      const cell = $(r.type === 'in' ? 'today-in' : 'today-out');
      if (r.type === 'out' || cell.textContent.trim() === '—') cell.textContent = hhmm(r.at) + ' ⏳';
    });
  }

  // ===== الموقع (اختياري، يعمل بدون إنترنت عبر GPS) =====
  let pos = null;
  function locate() {
    pos = null;
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition((p) => { pos = p.coords; }, () => {}, { enableHighAccuracy: true, timeout: 15000, maximumAge: 120000 });
  }

  // ===== قارئ QR =====
  let mode = 'in', stream = null, raf = 0, detector = null, busy = false;
  const video = $('scan-video'), screen = $('scan-screen'), err = $('scan-err');
  const canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });

  function loadJsQR() {
    if (window.jsQR) return Promise.resolve(true);
    return new Promise((ok) => {
      const s = document.createElement('script');
      s.src = JSQR; s.crossOrigin = 'anonymous';
      s.onload = () => ok(!!window.jsQR); s.onerror = () => ok(false);
      document.head.appendChild(s);
    });
  }
  async function decoder() {
    if ('BarcodeDetector' in window) {
      try {
        const f = await BarcodeDetector.getSupportedFormats();
        if (f.includes('qr_code')) { detector = new BarcodeDetector({ formats: ['qr_code'] }); return true; }
      } catch (e) {}
    }
    return loadJsQR();
  }
  async function decodeSource(src, w, h) {
    if (detector) {
      try { const r = await detector.detect(src); return r[0] ? r[0].rawValue : null; } catch (e) { return null; }
    }
    if (!window.jsQR) return null;
    const scale = Math.min(1, 640 / Math.max(w, h));
    canvas.width = Math.round(w * scale); canvas.height = Math.round(h * scale);
    ctx.drawImage(src, 0, 0, canvas.width, canvas.height);
    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const c = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'attemptBoth' });
    return c ? c.data : null;
  }

  function showErr(t) { err.textContent = t; err.hidden = !t; }

  async function open(type) {
    mode = type; busy = false; showErr('');
    $('scan-title').textContent = type === 'in' ? 'تسجيل حضور' : 'تسجيل مغادرة';
    screen.classList.toggle('out', type === 'out');
    screen.hidden = false;
    locate();
    const ok = await decoder();
    if (!ok) { showErr('قارئ الرموز غير متوفر على هذا الجهاز بدون إنترنت. اتصل بالإنترنت مرة واحدة وافتح هذه الصفحة ليُحفظ القارئ.'); return; }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      showErr('الكاميرا غير متاحة هنا (تحتاج فتح التطبيق من رابط آمن https). استخدم «التقاط صورة للرمز».');
      return;
    }
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } }, audio: false });
      video.srcObject = stream;
      await video.play();
      loop();
    } catch (e) {
      showErr('تعذّر فتح الكاميرا. اسمح للتطبيق باستخدام الكاميرا من إعدادات الجهاز، أو استخدم «التقاط صورة للرمز».');
    }
  }
  function close() {
    cancelAnimationFrame(raf);
    if (stream) stream.getTracks().forEach((t) => t.stop());
    stream = null; video.srcObject = null; screen.hidden = true;
  }
  async function loop() {
    if (!stream || busy) return;
    if (video.readyState >= 2) {
      const text = await decodeSource(video, video.videoWidth, video.videoHeight);
      if (text && handle(text)) return;
    }
    raf = requestAnimationFrame(() => setTimeout(loop, 120));
  }

  // التحقق من الرمز وحفظ التسجيل في الجهاز
  function handle(text) {
    const m = /^LTB-ATT:([a-z0-9]{16,64})$/i.exec(String(text).trim());
    if (!m) { showErr('هذا ليس رمز حضور المدرسة.'); return false; }
    const token = m[1].toLowerCase(), points = load(PKEY, []);
    const point = points.find((p) => p.t === token);
    if (points.length && !point) { showErr('هذا الرمز قديم أو موقوف. امسح الرمز المعلّق حالياً في المدرسة.'); return false; }
    busy = true;
    const rec = { uuid: uuid(), user: app.dataset.user, type: mode, token, at: Date.now() };
    if (pos) { rec.lat = +pos.latitude.toFixed(6); rec.lng = +pos.longitude.toFixed(6); rec.acc = Math.round(pos.accuracy); }
    const q = load(QKEY, []); q.push(rec); save(QKEY, q);
    close();
    if (navigator.vibrate) navigator.vibrate([60, 40, 60]);
    $('done-title').textContent = mode === 'in' ? 'تم تسجيل الحضور بنجاح' : 'تم تسجيل المغادرة بنجاح';
    $('done-time').textContent = hhmm(rec.at);
    $('done-place').textContent = point ? point.n : '';
    $('done-sync').textContent = navigator.onLine === false ? 'أنت بدون إنترنت: حُفظ التسجيل في جهازك بوقته، وسيُرفع تلقائياً عند الاتصال.' : 'جارٍ رفع التسجيل…';
    $('att-done').hidden = false;
    $('att-done').classList.toggle('out', mode === 'out');
    render();
    sync().then(() => {
      if (!load(QKEY, []).some((r) => r.uuid === rec.uuid)) $('done-sync').textContent = '✓ تم الرفع إلى المدرسة';
      else if (navigator.onLine !== false) $('done-sync').textContent = 'حُفظ في جهازك وسيُرفع عند استقرار الاتصال.';
    });
    return true;
  }

  document.querySelectorAll('[data-scan]').forEach((b) => b.addEventListener('click', () => open(b.dataset.scan)));
  $('scan-cancel').addEventListener('click', close);
  $('done-ok').addEventListener('click', () => { $('att-done').hidden = true; });
  $('sync-now').addEventListener('click', sync);
  $('scan-photo').addEventListener('change', async (e) => {
    const file = e.target.files[0]; e.target.value = '';
    if (!file) return;
    const img = new Image();
    img.onload = async () => {
      const text = await decodeSource(img, img.naturalWidth, img.naturalHeight);
      URL.revokeObjectURL(img.src);
      if (!text) { showErr('لم يُقرأ رمز في الصورة. صوّر الرمز من قريب وبوضوح.'); return; }
      handle(text);
    };
    img.src = URL.createObjectURL(file);
  });

  // تجهيز القارئ مسبقاً ليُحفظ في الجهاز ويعمل بدون إنترنت لاحقاً
  if (!('BarcodeDetector' in window) && navigator.onLine !== false) loadJsQR();
  if (navigator.serviceWorker && navigator.serviceWorker.controller && navigator.onLine !== false) {
    navigator.serviceWorker.controller.postMessage({ type: 'prime-attendance' });
  }
  render();
  sync();
})();
