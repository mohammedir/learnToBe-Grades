/* تسجيل التطبيق، ورسالة "أضف إلى الشاشة الرئيسية" عند أول فتح من الجوال */
(function () {
  const local = /^(localhost|127\.0\.0\.1|\[::1\]|192\.168\.|10\.)/.test(location.hostname);
  if ('serviceWorker' in navigator) {
    if (local) {
      // أثناء التطوير على الجهاز: لا عامل خدمة، وإلغاء أي نسخة قديمة مسجّلة
      navigator.serviceWorker.getRegistrations().then((regs) => regs.forEach((r) => r.unregister())).catch(() => {});
      if (window.caches) caches.keys().then((keys) => keys.forEach((k) => caches.delete(k))).catch(() => {});
    } else {
      window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
  }

  const KEY = 'rasd-install-prompt';
  const box = document.getElementById('install-prompt');
  if (!box) return;

  const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  const ua = navigator.userAgent;
  const isIOS = /iphone|ipad|ipod/i.test(ua) || (/macintosh/i.test(ua) && 'ontouchend' in document);
  const isMobile = isIOS || /android|mobile/i.test(ua);

  const read = () => { try { return JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { return {}; } };
  const write = (v) => { try { localStorage.setItem(KEY, JSON.stringify(v)); } catch (e) {} };
  const state = read();
  const snoozed = state.until && Date.now() < state.until;

  // لا تظهر: إذا كان مثبتاً، أو على الكمبيوتر، أو أُجّلت مؤخراً
  if (standalone || !isMobile || state.installed || snoozed) return;

  let deferred = null;
  const show = (mode) => {
    box.querySelector('[data-mode="android"]').hidden = mode !== 'android';
    box.querySelector('[data-mode="ios"]').hidden = mode !== 'ios';
    box.querySelector('[data-mode="other"]').hidden = mode !== 'other';
    box.hidden = false;
  };
  const hide = () => { box.hidden = true; };

  // أندرويد (Chrome / Edge / Samsung): زر تثبيت مباشر
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferred = e;
    show('android');
  });

  window.addEventListener('appinstalled', () => { write({ installed: true }); hide(); });

  // آيفون: Safari لا يدعم زر التثبيت، فنعرض الخطوات
  if (isIOS) {
    setTimeout(() => show('ios'), 1200);
  } else {
    // متصفح لا يرسل حدث التثبيت (مثلاً موقع بدون HTTPS): نعرض الطريقة اليدوية
    setTimeout(() => { if (!deferred) show('other'); }, 3500);
  }

  box.querySelector('[data-action="install"]').addEventListener('click', async () => {
    if (!deferred) return;
    deferred.prompt();
    const { outcome } = await deferred.userChoice;
    deferred = null;
    if (outcome === 'accepted') write({ installed: true });
    else write({ until: Date.now() + 7 * 864e5 });
    hide();
  });

  box.querySelectorAll('[data-action="later"]').forEach((b) => b.addEventListener('click', () => {
    write({ until: Date.now() + 7 * 864e5 }); // تذكير بعد أسبوع
    hide();
  }));
})();
