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

  // Camera scanning (optional; needs HTTPS or localhost)
  var camBtn = document.getElementById('btn-camera');
  if (camBtn) {
    var scanner = null;
    var setLabel = function (t) { var l = camBtn.querySelector('.lbl'); if (l) l.textContent = t; };
    camBtn.addEventListener('click', function () {
      var box = document.getElementById('reader');
      if (scanner) {
        scanner.stop().then(function () { scanner.clear(); scanner = null; box.hidden = true; setLabel('Use camera'); });
        return;
      }
      if (typeof Html5Qrcode === 'undefined') { alert('Camera scanner is not available.'); return; }
      box.hidden = false;
      scanner = new Html5Qrcode('reader');
      scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 240 }, function (text) {
        input.value = text;
        scanner.stop().finally(function () { document.getElementById('scan-form').submit(); });
      }).then(function () { setLabel('Stop camera'); })
        .catch(function (err) {
          alert('Could not start the camera: ' + err);
          scanner = null; box.hidden = true;
        });
    });
  }
})();
