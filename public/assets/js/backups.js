(function ($, bootstrap) {
  'use strict';
  $(document).on('click', '[data-restore]', function () {
    var el = document.getElementById('restore-modal');
    if (!el) { return; }
    document.getElementById('restore-form').setAttribute('action', this.getAttribute('data-action'));
    document.getElementById('restore-name').textContent = this.getAttribute('data-name');
    document.getElementById('restore-password').value = '';
    document.getElementById('restore-confirm').value = '';
    bootstrap.Modal.getOrCreateInstance(el).show();
  });
})(window.jQuery, window.bootstrap);
