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
  const read = () => { try { return JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { return {}; } };
  const write = (v) => { try { localStorage.setItem(KEY, JSON.stringify(Object.assign(read(), v))); } catch (e) {} };

  // يعمل كتطبيق مثبت؟ (أندرويد/كمبيوتر/آيفون)
  const isApp = () => ['standalone', 'fullscreen', 'minimal-ui', 'window-controls-overlay'].some((m) => window.matchMedia('(display-mode: ' + m + ')').matches)
    || window.navigator.standalone === true || document.referrer.startsWith('android-app://') || /[?&]source=app\b/.test(location.search);
  if (isApp()) {
    document.documentElement.classList.add('is-app');
    write({ installed: true }); // التخزين مشترك مع المتصفح، فلا تظهر الرسالة فيه أيضاً
  }

  const box = document.getElementById('install-prompt');
  if (!box) return;

  const ua = navigator.userAgent;
  const isIOS = /iphone|ipad|ipod/i.test(ua) || (/macintosh/i.test(ua) && 'ontouchend' in document);
  const isMobile = isIOS || /android|mobile/i.test(ua);
  const state = read();
  const snoozed = state.until && Date.now() < state.until;

  // لا تظهر: إذا كان مثبتاً، أو على الكمبيوتر، أو أُجّلت مؤخراً
  if (isApp() || !isMobile || state.installed || snoozed) return;

  let deferred = null;
  let gone = false;
  const show = (mode) => {
    if (gone || read().installed) return;
    box.querySelector('[data-mode="android"]').hidden = mode !== 'android';
    box.querySelector('[data-mode="ios"]').hidden = mode !== 'ios';
    box.querySelector('[data-mode="other"]').hidden = mode !== 'other';
    box.hidden = false;
  };
  const hide = () => { box.hidden = true; };
  const installed = () => { gone = true; write({ installed: true }); hide(); };

  // أندرويد (Chrome): إن كان التطبيق مثبتاً على الجهاز نعرف ذلك ولا نعرض الرسالة
  if (navigator.getInstalledRelatedApps) {
    navigator.getInstalledRelatedApps().then((apps) => { if (apps && apps.length) installed(); }).catch(() => {});
  }
  // إذا فُتح التطبيق المثبت أثناء عرض الصفحة
  window.matchMedia('(display-mode: standalone)').addEventListener?.('change', (e) => { if (e.matches) installed(); });

  // أندرويد (Chrome / Edge / Samsung): زر تثبيت مباشر — لا يُرسل هذا الحدث إذا كان التطبيق مثبتاً أصلاً
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferred = e;
    show('android');
  });

  window.addEventListener('appinstalled', installed);

  if (isIOS) {
    // آيفون: Safari لا يخبرنا إن كان مثبتاً، فنعرض الخطوات مرة ثم لا نزعج
    setTimeout(() => show('ios'), 1500);
  } else if (!('onbeforeinstallprompt' in window)) {
    // متصفح لا يدعم زر التثبيت (مثل Firefox): نعرض الطريقة اليدوية
    setTimeout(() => show('other'), 3500);
  }
  // في Chrome: غياب حدث التثبيت يعني غالباً أن التطبيق مثبت، فلا نعرض شيئاً

  box.querySelector('[data-action="install"]').addEventListener('click', async () => {
    if (!deferred) return;
    deferred.prompt();
    const { outcome } = await deferred.userChoice;
    deferred = null;
    if (outcome === 'accepted') installed();
    else { write({ until: Date.now() + 7 * 864e5 }); hide(); }
  });

  box.querySelectorAll('[data-action="later"]').forEach((b) => b.addEventListener('click', () => {
    write({ until: Date.now() + (isIOS ? 30 : 7) * 864e5 }); // تذكير لاحقاً
    hide();
  }));
  box.querySelectorAll('[data-action="have"]').forEach((b) => b.addEventListener('click', installed));
})();
