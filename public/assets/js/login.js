/* Ceklis "Saya bukan robot" (driver builtin) + UX form login. */
(function () {
  'use strict';

  var form = document.getElementById('login-form');
  if (!form) { return; }

  var box = document.getElementById('captcha-box');
  var submit = document.getElementById('login-submit');

  // Tampilkan/sembunyikan password
  var pw = document.getElementById('password'), toggle = document.getElementById('toggle-pw');
  if (pw && toggle) {
    toggle.addEventListener('click', function () {
      var show = pw.type === 'password';
      pw.type = show ? 'text' : 'password';
      toggle.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  }

  form.addEventListener('submit', function () {
    // cegah kirim ganda
    setTimeout(function () { submit.disabled = true; }, 0);
  });

  if (!box || box.getAttribute('data-driver') !== 'builtin') {
    return; // reCAPTCHA / Turnstile menangani widget-nya sendiri
  }

  var check = document.getElementById('captcha-check');
  var tokenInput = document.getElementById('captcha_token');
  var status = document.getElementById('captcha-status');
  var nonce = box.getAttribute('data-nonce');
  var csrfName = document.querySelector('meta[name="csrf-name"]').content;
  var csrfHeader = document.querySelector('meta[name="csrf-header"]').content;
  var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  // Bukti interaksi manusia: gerakan pointer, sentuhan, atau papan ketik.
  var interactions = 0;
  ['pointermove', 'pointerdown', 'touchstart', 'keydown', 'click'].forEach(function (evt) {
    document.addEventListener(evt, function (e) { if (e.isTrusted) { interactions++; } }, { passive: true });
  });

  function setStatus(html, cls) {
    status.innerHTML = html;
    box.classList.remove('is-verified', 'is-error');
    if (cls) { box.classList.add(cls); }
  }

  function lock() { submit.disabled = !tokenInput.value; }
  lock();

  var busy = false;
  function verify() {
    if (busy) { return; }
    busy = true;
    check.disabled = true;
    setStatus('<span class="spinner-border spinner-border-sm"></span> Memverifikasi…', null);

    var headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    headers[csrfHeader] = csrfToken;

    fetch(form.getAttribute('data-captcha-url'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers,
      body: JSON.stringify({ nonce: nonce, human: interactions > 0, webdriver: navigator.webdriver === true })
    }).then(function (r) { return r.json(); }).then(function (res) {
      busy = false;
      if (res.ok) {
        tokenInput.value = res.token;
        check.checked = true;
        check.disabled = true;
        setStatus('<i class="bi bi-check-circle-fill text-success"></i> Terverifikasi', 'is-verified');
        lock();
      } else if (res.retry_after) {
        // Server meminta menunggu (anti bot terlalu cepat); ulangi otomatis.
        setTimeout(verify, res.retry_after * 1000);
      } else {
        check.checked = false;
        check.disabled = false;
        setStatus('<i class="bi bi-exclamation-triangle-fill text-danger"></i> ' + (res.message || 'Gagal'), 'is-error');
      }
    }).catch(function () {
      busy = false;
      check.checked = false;
      check.disabled = false;
      setStatus('<i class="bi bi-wifi-off text-danger"></i> Gagal terhubung', 'is-error');
    });
  }

  check.addEventListener('change', function () {
    if (check.checked) { verify(); }
  });
})();
