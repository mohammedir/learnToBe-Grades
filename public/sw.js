/* عامل الخدمة: يحفظ ملفات التصميم، وصفحة تسجيل الحضور فقط لتعمل بدون إنترنت. لا يحفظ صفحات الدرجات (بيانات خاصة) */
const CACHE = 'learntobe-v7';
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
      const page = await caches.match(url.pathname.replace(/\/$/, ''));
      if (page) return page;
      const off = await caches.match('/offline.html');
      if (off) return off;
      throw err;
    }));
    return;
  }

  // باقي الصفحات: من الشبكة دائماً. صفحة "لا يوجد اتصال" فقط إن كان الجهاز فعلاً بدون إنترنت
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(async (err) => {
      if (self.navigator && self.navigator.onLine === false) {
        const page = await caches.match('/offline.html');
        if (page) return page;
      }
      throw err;
    }));
    return;
  }

  // ملفات التصميم والصور: من الذاكرة ثم تحديثها في الخلفية
  if (/\.(css|js|png|svg|woff2?)$/.test(url.pathname)) {
    e.respondWith(caches.match(req).then((hit) => hit || caches.match(req, { ignoreSearch: true })).then((hit) => {
      const net = fetch(req).then((res) => {
        if (res.ok) caches.open(CACHE).then((c) => c.put(req, res.clone()));
        return res;
      }).catch(() => hit);
      return hit || net;
    }));
  }
});

// تجهيز صفحة الحضور مسبقاً (تُرسلها الصفحات للمدرس المسجّل دخوله) لتعمل بدون إنترنت حتى لو لم يفتحها من قبل
self.addEventListener('message', (e) => {
  if (!e.data || e.data.type !== 'prime-attendance') return;
  e.waitUntil(Promise.all([
    fetch('/attendance', { credentials: 'same-origin', headers: { Accept: 'text/html' } }).then((res) => {
      if (res.ok && !res.redirected) return caches.open(CACHE).then((c) => c.put('/attendance', res));
    }).catch(() => {}),
    caches.match(JSQR).then((hit) => hit || fetch(JSQR, { mode: 'cors' }).then((res) => res.ok && caches.open(CACHE).then((c) => c.put(JSQR, res)))).catch(() => {}),
  ]));
});
