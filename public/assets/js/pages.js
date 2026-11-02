/* Skrip kecil untuk halaman kategori & peran. */
(function ($) {
  'use strict';

  // ---- Kategori: modal tambah / ubah ------------------------------------
  var modalEl = document.getElementById('category-modal');
  if (modalEl) {
    var modal = new bootstrap.Modal(modalEl);
    var f = {
      id: $('#cat-id'), name: $('#cat-name'), desc: $('#cat-desc'), icon: $('#cat-icon'), active: $('#cat-active'), title: $('#category-modal-title')
    };

    $(document).on('click', '[data-category-new]', function () {
      f.id.val(''); f.name.val(''); f.desc.val(''); f.icon.val('bi-folder'); f.active.prop('checked', true);
      f.title.text('Tambah Kategori');
      modal.show();
    });

    $(document).on('click', '[data-category-edit]', function () {
      var d = this.dataset;
      f.id.val(d.id); f.name.val(d.name); f.desc.val(d.description || ''); f.icon.val(d.icon || 'bi-folder');
      f.active.prop('checked', d.active === '1');
      f.title.text('Ubah Kategori');
      modal.show();
    });

    modalEl.addEventListener('shown.bs.modal', function () { f.name.trigger('focus'); });
  }

  // ---- Peran: pilih semua per grup --------------------------------------
  $(document).on('change', '[data-check-group]', function () {
    var group = this.getAttribute('data-check-group'), on = this.checked;
    $('input[type=checkbox][data-group="' + group + '"]:not(:disabled)').prop('checked', on);
  });
})(jQuery);
