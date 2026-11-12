(function () {
  // Render QR codes (offline, via bundled qrcode.js)
  document.querySelectorAll('[data-qr]').forEach(function (el) {
    try {
      var qr = qrcode(0, 'M');
      qr.addData(el.getAttribute('data-qr'));
      qr.make();
      el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
    } catch (e) { el.textContent = el.getAttribute('data-qr'); }
  });

  // Live clock and date in the sidebar
  var clock = document.querySelector('[data-clock]');
  var dateEl = document.querySelector('[data-date]');
  if (clock) {
    var tick = function () {
      var now = new Date();
      clock.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      if (dateEl) dateEl.textContent = now.toLocaleDateString([], { weekday: 'long', month: 'short', day: 'numeric' });
    };
    tick(); setInterval(tick, 15000);
  }

  // Confirm dialogs on risky forms
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!confirm(f.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });


  // Photo upload: live preview + friendly size/type check before sending
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    var maxMb = parseFloat(inp.getAttribute('data-max-mb') || '5');
    var current = inp.getAttribute('data-current');
    var box = document.createElement('div');
    box.className = 'photo-preview'; box.hidden = true;
    box.innerHTML = '<img alt="Preview of the chosen photo"><div><strong></strong><small class="muted"></small>' +
                    '<button type="button" class="btn btn-ghost btn-sm">Remove photo</button></div>';
    var err = document.createElement('div');
    err.className = 'field-error'; err.hidden = true; err.setAttribute('role', 'alert');
    inp.insertAdjacentElement('afterend', box);
    box.insertAdjacentElement('afterend', err);
    var img = box.querySelector('img'), nameEl = box.querySelector('strong'),
        metaEl = box.querySelector('small'), clearBtn = box.querySelector('button'), url = null;

    function drop() { if (url) { URL.revokeObjectURL(url); url = null; } }
    function showCurrent() {
      drop();
      if (current) {
        img.src = current; nameEl.textContent = 'Current photo';
        metaEl.textContent = 'Choose a file to replace it.'; clearBtn.hidden = true; box.hidden = false;
      } else { box.hidden = true; }
    }
    function fail(msg) { inp.value = ''; err.textContent = msg; err.hidden = false; showCurrent(); }

    inp.addEventListener('change', function () {
      err.hidden = true;
      var f = inp.files && inp.files[0];
      if (!f) { showCurrent(); return; }
      if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { fail('Please choose a JPG, PNG or WEBP image.'); return; }
      if (f.size > maxMb * 1024 * 1024) {
        fail('That photo is ' + (f.size / 1048576).toFixed(1) + ' MB. The limit is ' + maxMb + ' MB. Please choose a smaller one.'); return;
      }
      drop(); url = URL.createObjectURL(f);
      img.src = url; nameEl.textContent = f.name;
      metaEl.textContent = (f.size / 1048576).toFixed(2) + ' MB. This is how it will look.';
      clearBtn.hidden = false; box.hidden = false;
    });
    clearBtn.addEventListener('click', function () { inp.value = ''; err.hidden = true; showCurrent(); });
    showCurrent();
  });

  // Print button
  document.querySelectorAll('[data-print]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });

  // Auto-return to gate screen after a successful action
  var res = document.querySelector('[data-redirect-after]');
  if (res) {
    var left = parseInt(res.getAttribute('data-redirect-after'), 10);
    var to = res.getAttribute('data-redirect-to');
    var note = res.querySelector('[data-countdown]');
    var t = setInterval(function () {
      left--;
      if (note) note.textContent = 'Returning to the gate screen in ' + left + ' seconds.';
      if (left <= 0) { clearInterval(t); window.location = to; }
    }, 1000);
  }

  // Keep the scan box focused (USB scanners type like a keyboard)
  var input = document.getElementById('scan-input');
  if (input) {
    document.addEventListener('click', function (e) {
      if (!e.target.closest('a,button,select,input,summary')) input.focus();
    });
  }

  // ---------------------------------------------------------------------------
  // Camera scanning (optional; needs https:// or http://localhost)
  //
  // The browser stores the visitor's Allow / Block choice for this website. A web page is not allowed to
  // ask again or to change it, so a blocked camera stays blocked, even after a restart. What we CAN do is
  // notice it, explain how to turn it back on, and pick the camera up again as soon as the setting changes.
  // ---------------------------------------------------------------------------
  var camBtn = document.getElementById('btn-camera');
  if (camBtn) (function () {
    var panel = document.getElementById('camera-panel'),
        reader = document.getElementById('reader'),
        hint = document.getElementById('camera-hint'),
        form = document.getElementById('scan-form'),
        scanner = null, running = false, starting = false, scanned = false,
        perm = null, shown = '', waitTimer = null;

    function label(t) { var l = camBtn.querySelector('.lbl'); if (l) l.textContent = t; }
    function esc(x) { return String(x).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    var ua = navigator.userAgent;
    var touch = /Android|iPhone|iPad|iPod/.test(ua) || (navigator.maxTouchPoints > 1 && /Macintosh/.test(ua));
    var brand = /Edg\//.test(ua) ? 'edge' : (/Firefox\/|FxiOS/.test(ua) ? 'firefox' : (/Chrome\/|CriOS|OPR\//.test(ua) ? 'chrome' : (/Safari\//.test(ua) ? 'safari' : 'other')));
    var settingsAddress = brand === 'chrome' && !touch ? 'chrome://settings/content/siteDetails?site=' + encodeURIComponent(location.origin)
                        : (brand === 'edge' && !touch ? 'edge://settings/content/siteDetails?site=' + encodeURIComponent(location.origin) : null);

    function steps() {
      if (brand === 'firefox') return [
        'Click the camera icon with a line through it, or the lock icon, at the left end of the address bar.',
        'Next to <b>Use the Camera</b>, click the <b>X</b> to remove the block.',
        'Press <b>Try again</b> below. Firefox asks again: choose <b>Allow</b>.'];
      if (brand === 'safari') return touch ? [
        'Tap the <b>aA</b> icon in the address bar, then <b>Website Settings</b>.',
        'Set <b>Camera</b> to <b>Allow</b>. (Also check <b>Settings › Safari › Camera</b>.)',
        'Come back here and press <b>Try again</b>.'] : [
        'In the menu bar, choose <b>Safari › Settings for This Website…</b>',
        'Set <b>Camera</b> to <b>Allow</b>.',
        'Press <b>Try again</b> below.'];
      var first = brand === 'edge'
        ? 'Click the lock icon (or the camera icon with a red mark) at the left end of the address bar.'
        : 'Click the icon at the left end of the address bar: a camera with a red mark, or a small settings icon.';
      var out = [first,
        'Next to <b>Camera</b>, choose <b>Allow</b>. If you only see <b>Reset permission</b>, click that instead.',
        'Press <b>Try again</b> below. If the browser says the page must be reloaded, reload it.'];
      if (touch) out.push('On Android, also check <b>Settings › Apps › ' + (brand === 'edge' ? 'Edge' : 'Chrome') + ' › Permissions › Camera</b>.');
      return out;
    }

    function btns(list) {
      var names = { retry: 'Try again', reload: 'Reload page', copy: 'Copy settings address', search: 'Use the search box instead' };
      return '<div class="row-gap wrap cam-actions">' + list.map(function (a, i) {
        return '<button type="button" class="btn ' + (i === 0 ? 'btn-primary' : 'btn-ghost') + '" data-act="' + a + '">' + names[a] + '</button>';
      }).join('') + '</div>';
    }

    var MORE = '<details class="cam-more"><summary>Still not working?</summary><ul>' +
      '<li>Open the site as <code>http://localhost/sgvms/</code> or with <code>https://</code>. Browsers refuse the camera on other plain-http addresses.</li>' +
      '<li>Windows: <b>Settings › Privacy &amp; security › Camera</b>. Turn on <b>Camera access</b> and <b>Let desktop apps access your camera</b>.</li>' +
      '<li>Close other apps or browser tabs that use the camera (Teams, Zoom, the Camera app).</li>' +
      '<li>You can always type a name or LRN in the search box, or use a USB scanner.</li></ul></details>';

    function card(kind, extra) {
      var tone = 'cam-warn', title, body, acts;
      if (kind === 'blocked' || kind === 'dismissed') {
        title = kind === 'dismissed' ? 'Camera permission was not given' : 'Camera access is blocked';
        body = kind === 'dismissed'
          ? '<p>The permission box was closed without choosing. Press <b>Try again</b> and choose <b>Allow</b>. If the box does not appear again, your browser has blocked the camera; turn it on like this:</p>'
          : '<p>Your browser saved a “Block” choice for this website. It remembers that even after you restart the computer, and this page is not allowed to ask again. Turn the camera back on like this:</p>';
        body += '<ol class="cam-steps"><li>' + steps().join('</li><li>') + '</li></ol>' + MORE;
        acts = ['retry', 'reload'].concat(settingsAddress ? ['copy'] : [], ['search']);
      } else if (kind === 'waiting') {
        tone = 'cam-info'; title = 'Waiting for your permission';
        body = '<p>Your browser is asking to use the camera. Look near the address bar and choose <b>Allow</b>.</p>'; acts = [];
      } else if (kind === 'granted') {
        tone = 'cam-info'; title = 'Permission granted'; body = '<p>Starting the camera…</p>'; acts = [];
      } else if (kind === 'reset') {
        tone = 'cam-info'; title = 'The setting was changed';
        body = '<p>Press <b>Try again</b>. Your browser will ask for permission; choose <b>Allow</b>.</p>'; acts = ['retry'];
      } else if (kind === 'nocamera') {
        title = 'No camera found';
        body = '<p>This device has no camera, or the browser cannot see it. Plug one in and press <b>Try again</b>, or search by name or LRN, or use a USB scanner.</p>'; acts = ['retry', 'search'];
      } else if (kind === 'inuse') {
        title = 'The camera is busy';
        body = '<p>Another app or browser tab is using the camera (for example Teams, Zoom or the Camera app). Close it, then press <b>Try again</b>.</p>'; acts = ['retry', 'search'];
      } else if (kind === 'insecure') {
        title = 'The camera needs a secure address';
        body = '<p>Browsers only allow the camera on <code>https://</code> addresses and on <code>http://localhost</code>. This page was opened as <code>' + esc(location.origin) + '</code>.</p>' +
               '<p>On this computer, open <code>http://localhost/sgvms/</code>. On other devices, the school needs to set up HTTPS (see the README).</p>' + MORE;
        acts = ['search'];
      } else {
        title = 'The camera could not start';
        body = '<p>Something unexpected stopped the camera. You can try again, reload the page, or use the search box.</p><p class="small muted">Technical detail: <code>' + esc(extra || 'unknown error') + '</code></p>' + MORE;
        acts = ['retry', 'reload', 'search'];
      }
      shown = kind;
      panel.className = 'cam-panel';
      panel.innerHTML = '<div class="cam-card ' + tone + '"><h3>' + title + '</h3>' + body + (acts.length ? btns(acts) : '') + '</div>';
      panel.hidden = false;
      if (kind !== 'waiting' && kind !== 'granted') { try { panel.focus(); } catch (e) {} }
    }
    function hideCard() { shown = ''; panel.hidden = true; panel.innerHTML = ''; }

    panel.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-act]'); if (!b) return;
      var act = b.getAttribute('data-act');
      if (act === 'retry') start();
      else if (act === 'reload') location.reload();
      else if (act === 'search') { hideCard(); input.focus(); }
      else if (act === 'copy' && settingsAddress) {
        var ok = function () { b.textContent = 'Copied. Paste it into the address bar and press Enter.'; };
        var manual = function () { window.prompt('Copy this address, paste it into the address bar and press Enter:', settingsAddress); };
        if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(settingsAddress).then(ok, manual); else manual();
      }
    });

    // ---- what the browser has saved (Permissions API; not every browser supports it) ----
    function queryState() {
      if (!navigator.permissions || !navigator.permissions.query) return Promise.resolve('unknown');
      return navigator.permissions.query({ name: 'camera' }).then(function (s) {
        if (!perm) { perm = s; s.onchange = function () { onPermChange(s.state); }; }
        return s.state;
      }, function () { return 'unknown'; });
    }
    function showHint(state) {
      if (state === 'denied') { hint.innerHTML = 'Camera access is <b>blocked</b> for this site. Click <b>Use camera</b> to see how to turn it back on.'; hint.hidden = false; }
      else if (state === 'granted') { hint.hidden = true; }
      else { hint.innerHTML = 'The first time, your browser asks for permission. Choose <b>Allow</b>.'; hint.hidden = false; }
    }
    function onPermChange(state) {
      showHint(state);
      if (shown !== 'blocked' && shown !== 'dismissed') return;
      if (state === 'granted') { card('granted'); setTimeout(start, 300); }       // started again without a reload
      else if (state === 'prompt') card('reset');
    }
    document.addEventListener('visibilitychange', function () {       // back from the browser's settings
      if (document.visibilityState === 'visible' && (shown === 'blocked' || shown === 'dismissed')) {
        queryState().then(function (st) { if (st !== 'denied' && st !== 'unknown') onPermChange(st); });
      }
    });

    // ---- start / stop ----
    function onScan(text) {
      if (scanned) return; scanned = true;
      input.value = text;
      stop().then(function () { form.submit(); });
    }
    function begin() {
      var cfg = { fps: 10, qrbox: 240 };
      reader.hidden = false;
      scanner = new Html5Qrcode('reader');
      return scanner.start({ facingMode: 'environment' }, cfg, onScan, function () {}).catch(function (e) {
        if (/NotAllowed|Permission/i.test(String(e && (e.name || e)))) throw e;
        // some laptops reject facingMode: try a specific camera instead
        scanner = new Html5Qrcode('reader');
        return Html5Qrcode.getCameras().then(function (cams) {
          if (!cams || !cams.length) throw { name: 'NotFoundError' };
          return scanner.start(cams[0].id, cfg, onScan, function () {});
        });
      }).then(function () { running = true; starting = false; camBtn.disabled = false; label('Stop camera'); hideCard(); });
    }
    function fail(err) {
      clearTimeout(waitTimer);
      starting = false; running = false; camBtn.disabled = false; label('Use camera');
      reader.hidden = true;
      if (scanner) { try { scanner.clear(); } catch (e) {} scanner = null; }
      var t = String(err && (err.name || '') ? (err.name + ' ' + (err.message || '')) : err);
      if (/dismissed/i.test(t)) return card('dismissed');
      if (/NotAllowed|PermissionDenied|Permission denied/i.test(t)) return card('blocked');
      if (/NotFound|DevicesNotFound|Requested device not found/i.test(t)) return card('nocamera');
      if (/NotReadable|TrackStart|Could not start video source|AbortError/i.test(t)) return card('inuse');
      if (/Security/i.test(t)) return card('insecure');
      card('generic', t);
    }
    function start() {
      if (starting || running) return;
      hideCard();
      if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return card('insecure');
      if (typeof Html5Qrcode === 'undefined') return card('generic', 'The camera scanner file did not load. Reload the page.');
      starting = true; scanned = false; camBtn.disabled = true; label('Starting…');
      waitTimer = setTimeout(function () { if (starting && !shown) card('waiting'); }, 700);   // the browser's question is on screen
      queryState().then(function (state) {
        showHint(state);
        // Already blocked: asking again would fail at once, so go straight to the instructions.
        if (state === 'denied') return Promise.reject({ name: 'NotAllowedError', message: 'blocked in the browser settings' });
        // A plain check first: it gives exact error names and triggers the browser's question.
        return navigator.mediaDevices.getUserMedia({ video: true, audio: false });
      }).then(function (stream) {
        stream.getTracks().forEach(function (t) { t.stop(); });
        clearTimeout(waitTimer); hideCard();
        return begin();
      }).catch(fail);
    }
    function stop() {
      if (!scanner) return Promise.resolve();
      var s = scanner;
      return s.stop().catch(function () {}).then(function () {
        try { s.clear(); } catch (e) {}
        scanner = null; running = false; reader.hidden = true; label('Use camera');
      });
    }

    camBtn.addEventListener('click', function () { if (running) stop(); else start(); });
    window.addEventListener('pagehide', function () { if (scanner) { try { scanner.stop(); } catch (e) {} } });
    queryState().then(showHint);
  })();
})();
