/* عامل الخدمة: يحفظ ملفات التصميم، وصفحة تسجيل الحضور فقط لتعمل بدون إنترنت. لا يحفظ صفحات الدرجات (بيانات خاصة) */
const CACHE = 'learntobe-v9';
const ASSETS = ['/css/app.css', '/js/sheet.js', '/js/pwa.js', '/js/attendance.js', '/images/logo.png', '/icons/icon-192.png', '/offline.html'];
const JSQR = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';
const OFFLINE_PAGES = ['/attendance']; // صفحات تعمل بدون إنترنت من آخر نسخة محفوظة

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  // قارئ QR الخارجي: يُحفظ بعد أول تحميل ليعمل بدون إنترنت
  if (req.url === JSQR) {
    e.respondWith(caches.match(JSQR).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) caches.open(CACHE).then((c) => c.put(JSQR, res.clone()));
      return res;
    })));
    return;
  }
  if (url.origin !== location.origin) return;

  // صفحة تسجيل الحضور: من الشبكة وتُحفظ، وبدون إنترنت تُعرض آخر نسخة محفوظة
  if (req.mode === 'navigate' && OFFLINE_PAGES.includes(url.pathname.replace(/\/$/, ''))) {
    e.respondWith(fetch(req).then((res) => {
      if (res.ok && !res.redirected) caches.open(CACHE).then((c) => c.put(url.pathname.replace(/\/$/, ''), res.clone()));
      return res;
    }).catch(async (err) => {
      // صفحة «بدون إنترنت» تعرض الشاشة لآخر مدرس سجّل الدخول (أحدث من النسخة المحفوظة)
      const off = await caches.match('/offline.html') || await caches.match(url.pathname.replace(/\/$/, ''));
      if (off) return off;
      throw err;
    }));
    return;
  }

  // باقي الصفحات: من الشبكة دائماً. إذا تعذّر الاتصال تظهر صفحة «بدون إنترنت»
  // وفيها تسجيل الحضور لآخر مدرس سجّل الدخول على الجهاز
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(async (err) => {
      const page = await caches.match('/offline.html');
      if (page) return page;
      throw err;
    }));
    return;
  }

  // ملفات التصميم والصور: من الذاكرة ثم تحديثها في الخلفية
  if (/\.(css|js|png|svg|woff2?)$/.test(url.pathname)) {
    // إصدار مطابق محفوظ → فوراً. إصدار جديد (?v=) → من الشبكة أولاً، والقديم فقط بدون إنترنت
    e.respondWith(caches.match(req).then((hit) => {
      const net = fetch(req).then((res) => {
        if (res.ok) caches.open(CACHE).then((c) => c.put(req, res.clone()));
        return res;
      });
      if (hit) { net.catch(() => {}); return hit; }
      return net.catch(() => caches.match(req, { ignoreSearch: true }));
    }));
  }
});

// تجهيز صفحة الحضور مسبقاً (تُرسلها الصفحات للمدرس المسجّل دخوله) لتعمل بدون إنترنت حتى لو لم يفتحها من قبل
self.addEventListener('message', (e) => {
  if (!e.data || e.data.type !== 'prime-attendance') return;
  e.waitUntil(Promise.all([
    caches.open(CACHE).then((c) => c.addAll(['/offline.html', '/js/attendance.js', '/css/app.css'])).catch(() => {}),
    fetch('/attendance', { credentials: 'same-origin', headers: { Accept: 'text/html' } }).then((res) => {
      if (res.ok && !res.redirected) return caches.open(CACHE).then((c) => c.put('/attendance', res));
    }).catch(() => {}),
    caches.match(JSQR).then((hit) => hit || fetch(JSQR, { mode: 'cors' }).then((res) => res.ok && caches.open(CACHE).then((c) => c.put(JSQR, res)))).catch(() => {}),
  ]));
});
