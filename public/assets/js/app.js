/* E-Repository — skrip umum. Tanpa inline script agar kompatibel dengan CSP ketat. */
(function ($) {
  'use strict';

  var meta = function (name) {
    var el = document.querySelector('meta[name="' + name + '"]');
    return el ? el.getAttribute('content') : '';
  };

  window.Repo = {
    baseUrl: meta('base-url'),
    csrfName: meta('csrf-name'),
    csrfHeader: meta('csrf-header'),
    csrfToken: meta('csrf-token'),

    esc: function (value) {
      return String(value === null || value === undefined ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    },

    url: function (path) {
      return this.baseUrl.replace(/\/+$/, '') + '/' + String(path).replace(/^\/+/, '');
    },

    dtLanguage: {
      emptyTable: 'Belum ada data.',
      info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
      infoEmpty: 'Tidak ada data',
      infoFiltered: '(disaring dari _MAX_ data)',
      lengthMenu: 'Tampilkan _MENU_ data',
      loadingRecords: 'Memuat...',
      processing: 'Memproses...',
      search: 'Cari:',
      searchPlaceholder: 'Ketik untuk mencari...',
      zeroRecords: 'Data tidak ditemukan.',
      paginate: { first: '«', last: '»', next: '›', previous: '‹' }
    },

    confirm: function (opts) {
      return Swal.fire($.extend({
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, lanjutkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#1f6feb',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
        focusCancel: true
      }, opts));
    },

    /** Kirim POST (dengan token CSRF) lewat form sementara. */
    post: function (url) {
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = url;
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = this.csrfName;
      input.value = this.csrfToken;
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    }
  };

  // ---- Notifikasi flash -------------------------------------------------
  $(function () {
    var flash = document.getElementById('flash');
    if (flash && window.Swal) {
      ['success', 'error', 'warning', 'info'].forEach(function (type) {
        var msg = flash.getAttribute('data-' + type);
        if (!msg) { return; }
        if (type === 'error' || type === 'warning') {
          Swal.fire({ icon: type, text: msg, confirmButtonText: 'Mengerti', confirmButtonColor: '#1f6feb' });
        } else {
          Swal.fire({ toast: true, position: 'top-end', icon: type, title: msg, showConfirmButton: false, timer: 3500, timerProgressBar: true });
        }
      });
    }

    // ---- Sidebar mobile -------------------------------------------------
    var sidebar = $('#sidebar'), backdrop = $('#sidebar-backdrop');
    $('#sidebar-toggle').on('click', function () { sidebar.toggleClass('open'); backdrop.toggleClass('show'); });
    backdrop.on('click', function () { sidebar.removeClass('open'); backdrop.removeClass('show'); });

    // ---- Konfirmasi sebelum submit form --------------------------------
    $(document).on('submit', 'form[data-confirm]', function (e) {
      var form = this;
      if (form.getAttribute('data-confirmed') === '1') { return; }
      e.preventDefault();
      Repo.confirm({
        title: form.getAttribute('data-confirm-title') || 'Konfirmasi',
        text: form.getAttribute('data-confirm'),
        icon: form.getAttribute('data-confirm-icon') || 'warning',
        confirmButtonText: form.getAttribute('data-confirm-button') || 'Ya, lanjutkan'
      }).then(function (r) {
        if (r.isConfirmed) { form.setAttribute('data-confirmed', '1'); form.submit(); }
      });
    });

    // ---- Tombol aksi POST dinamis (mis. di DataTables) ------------------
    $(document).on('click', '[data-post]', function (e) {
      e.preventDefault();
      var el = this, url = el.getAttribute('data-post');
      Repo.confirm({
        title: el.getAttribute('data-confirm-title') || 'Konfirmasi',
        text: el.getAttribute('data-confirm') || 'Lanjutkan aksi ini?',
        icon: el.getAttribute('data-confirm-icon') || 'warning',
        confirmButtonText: el.getAttribute('data-confirm-button') || 'Ya, lanjutkan'
      }).then(function (r) { if (r.isConfirmed) { Repo.post(url); } });
    });

    // ---- Cegah kirim ganda ---------------------------------------------
    $(document).on('submit', 'form[data-once]', function () {
      var form = this;
      setTimeout(function () {
        $(form).find('button[type=submit]').prop('disabled', true)
          .prepend('<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>');
      }, 0);
    });

    // ---- Tampil/sembunyikan password -----------------------------------
    $(document).on('click', '[data-toggle-password]', function () {
      var input = $($(this).attr('data-toggle-password'));
      var show = input.attr('type') === 'password';
      input.attr('type', show ? 'text' : 'password');
      $(this).find('i').toggleClass('bi-eye', !show).toggleClass('bi-eye-slash', show);
    });

    // ---- Validasi ukuran berkas di sisi klien --------------------------
    $(document).on('change', 'input[type=file][data-max-bytes]', function () {
      var max = parseInt(this.getAttribute('data-max-bytes'), 10), f = this.files && this.files[0];
      if (f && max && f.size > max) {
        Swal.fire({ icon: 'error', text: 'Ukuran berkas melebihi batas maksimum ' + this.getAttribute('data-max-label') + '.', confirmButtonColor: '#1f6feb' });
        this.value = '';
      }
    });

    // ---- DataTables sederhana (sisi klien) -----------------------------
    if ($.fn.DataTable) {
      $('table.datatable').each(function () {
        $(this).DataTable({
          language: Repo.dtLanguage,
          order: [],
          pageLength: 10,
          columnDefs: [{ targets: 'no-sort', orderable: false }]
        });
      });
    }
  });
})(jQuery);
