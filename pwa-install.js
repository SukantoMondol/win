(function () {
  if (window.__WCB_PWA_INSTALL_LOADED__) return;
  window.__WCB_PWA_INSTALL_LOADED__ = true;

  let deferredPrompt = null;
  let installHideTimer = null;
  const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

  function ensureInstallPopup() {
    let popup = document.getElementById('wcbPwaInstallPopup');
    if (popup) return popup;
    popup = document.createElement('div');
    popup.id = 'wcbPwaInstallPopup';
    popup.innerHTML = '<div class="wcb-pwa-modal" role="status" aria-live="polite">' +
      '<div class="wcb-pwa-spinner" aria-hidden="true"></div>' +
      '<div class="wcb-pwa-title">Installing...</div>' +
      '<div class="wcb-pwa-msg">Please follow the browser install prompt.</div>' +
      '<button type="button" class="wcb-pwa-close" style="display:none">OK</button>' +
      '</div>';
    const style = document.createElement('style');
    style.textContent = '#wcbPwaInstallPopup{position:fixed;inset:0;z-index:2147483000;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;padding:18px;box-sizing:border-box}#wcbPwaInstallPopup.show{display:flex}.wcb-pwa-modal{width:100%;max-width:320px;border-radius:24px;background:linear-gradient(180deg,#083a2f,#041f19);color:#fff;text-align:center;padding:28px 22px;box-shadow:0 24px 80px rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.12);font-family:Arial,sans-serif}.wcb-pwa-spinner{width:54px;height:54px;border-radius:50%;border:5px solid rgba(255,255,255,.18);border-top-color:#1de9b6;margin:0 auto 16px;animation:wcbPwaSpin .8s linear infinite}.wcb-pwa-title{font-size:20px;font-weight:900;margin-bottom:8px}.wcb-pwa-msg{font-size:13px;line-height:1.5;color:#c9f8ec}.wcb-pwa-close{margin-top:18px;border:0;border-radius:999px;background:#1de9b6;color:#073a2d;font-weight:900;padding:10px 26px;cursor:pointer}@keyframes wcbPwaSpin{to{transform:rotate(360deg)}}.wcb-pwa-success .wcb-pwa-spinner{animation:none;border-color:#1de9b6;position:relative}.wcb-pwa-success .wcb-pwa-spinner:after{content:"✓";position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:900;color:#1de9b6}.wcb-pwa-error .wcb-pwa-spinner{animation:none;border-color:#ffca28;position:relative}.wcb-pwa-error .wcb-pwa-spinner:after{content:"!";position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:900;color:#ffca28}@media(max-width:768px){.wcb-pwa-modal{max-width:290px;padding:24px 18px}.wcb-pwa-spinner{width:48px;height:48px}}';
    document.head.appendChild(style);
    document.body.appendChild(popup);
    popup.querySelector('.wcb-pwa-close').addEventListener('click', hideInstallPopup);
    return popup;
  }

  function showInstallPopup(state, title, message, autoHide) {
    const popup = ensureInstallPopup();
    const modal = popup.querySelector('.wcb-pwa-modal');
    modal.classList.remove('wcb-pwa-success', 'wcb-pwa-error');
    if (state === 'success') modal.classList.add('wcb-pwa-success');
    if (state === 'error') modal.classList.add('wcb-pwa-error');
    popup.querySelector('.wcb-pwa-title').textContent = title || 'Installing...';
    popup.querySelector('.wcb-pwa-msg').textContent = message || 'Please follow the browser install prompt.';
    popup.querySelector('.wcb-pwa-close').style.display = state === 'loading' ? 'none' : 'inline-block';
    popup.classList.add('show');
    clearTimeout(installHideTimer);
    if (autoHide) installHideTimer = setTimeout(hideInstallPopup, autoHide);
  }

  function hideInstallPopup() {
    const popup = document.getElementById('wcbPwaInstallPopup');
    if (popup) popup.classList.remove('show');
  }

  function showFallbackMessage() {
    const ua = navigator.userAgent || '';
    if (/iPhone|iPad|iPod/i.test(ua)) {
      showInstallPopup('error', 'Manual Install', 'iPhone/iPad: Share button চাপুন, তারপর Add to Home Screen নির্বাচন করুন।', 6500);
    } else if (isStandalone()) {
      showInstallPopup('success', 'Already Installed', 'App already installed/opened as web app.', 3500);
    } else {
      showInstallPopup('error', 'Install Not Available', 'Install prompt এখন available না। Chrome menu থেকে Install app / Add to Home screen নির্বাচন করুন।', 6500);
    }
  }

  async function triggerInstall(e) {
    if (e) e.preventDefault();
    if (isStandalone()) { showFallbackMessage(); return false; }
    if (!deferredPrompt) { showFallbackMessage(); return false; }
    const promptEvent = deferredPrompt;
    deferredPrompt = null;
    showInstallPopup('loading', 'Installing...', 'Browser install prompt খুলছে। Install চাপুন এবং কয়েক সেকেন্ড অপেক্ষা করুন।');
    try {
      await promptEvent.prompt();
      const choice = await promptEvent.userChoice;
      if (choice && choice.outcome === 'accepted') {
        showInstallPopup('success', 'Install Started', 'App install process started successfully.', 4500);
      } else {
        showInstallPopup('error', 'Install Cancelled', 'You cancelled the install prompt.', 4500);
      }
    } catch (err) {
      showInstallPopup('error', 'Install Failed', 'Install prompt failed. Please try again from Chrome menu.', 5500);
    }
    return false;
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    document.querySelectorAll('.js-pwa-install, [data-pwa-install="1"], a[href$="download.php"], a[href="/download.php"], a[href="../download.php"]').forEach(el => {
      el.dataset.pwaInstallReady = '1';
    });
  });

  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
    try { localStorage.setItem('wcb_pwa_installed', '1'); } catch (e) {}
    showInstallPopup('success', 'Installed Successfully', 'App installation complete.', 4200);
  });

  document.addEventListener('click', function (e) {
    const el = e.target.closest('.js-pwa-install, [data-pwa-install="1"], a[href$="download.php"], a[href="/download.php"], a[href="../download.php"]');
    if (!el) return;
    triggerInstall(e);
  }, true);

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
    });
  }

  window.WCBPWAInstall = triggerInstall;
})();
