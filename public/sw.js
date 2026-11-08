/* عامل الخدمة: يحفظ ملفات التصميم فقط، ولا يحفظ صفحات الدرجات (بيانات خاصة) */
const CACHE = 'learntobe-v4';
const ASSETS = ['/css/app.css', '/js/sheet.js', '/js/pwa.js', '/images/logo.png', '/icons/icon-192.png', '/offline.html'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET' || new URL(req.url).origin !== location.origin) return;

  // الصفحات: من الشبكة دائماً. صفحة "لا يوجد اتصال" فقط إن كان الجهاز فعلاً بدون إنترنت،
  // وإلا يظهر خطأ المتصفح الحقيقي (مثل توقف الخادم) حتى لا يُخفى سبب المشكلة.
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
  if (/\.(css|js|png|svg|woff2?)$/.test(new URL(req.url).pathname)) {
    e.respondWith(caches.match(req).then((hit) => {
      const net = fetch(req).then((res) => {
        if (res.ok) caches.open(CACHE).then((c) => c.put(req, res.clone()));
        return res;
      }).catch(() => hit);
      return hit || net;
    }));
  }
});
