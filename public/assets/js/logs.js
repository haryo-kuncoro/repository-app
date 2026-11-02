/* Log aktivitas: DataTables server-side. */
(function ($) {
  'use strict';

  var el = document.getElementById('logs-table');
  if (!el) { return; }

  var badge = function (action) {
    var cls = 'badge-soft-gray';
    if (/fail|locked|forbidden|delete|purge/.test(action)) { cls = 'bg-danger-subtle text-danger-emphasis'; }
    else if (/login$|create|upload|restore/.test(action)) { cls = 'bg-success-subtle text-success-emphasis'; }
    else if (/update|download|preview|password/.test(action)) { cls = 'badge-soft'; }
    return '<span class="badge ' + cls + '">' + Repo.esc(action) + '</span>';
  };

  var table = $(el).DataTable({
    serverSide: true,
    processing: true,
    language: Repo.dtLanguage,
    pageLength: 25,
    order: [[0, 'desc']],
    ajax: {
      url: el.getAttribute('data-url'),
      type: 'GET',
      data: function (d) { d.action = $('#l-action').val(); }
    },
    columns: [
      { data: 'created_at', className: 'text-nowrap' },
      { data: 'username', render: function (d) { return Repo.esc(d || '—'); } },
      { data: 'action', render: function (d) { return badge(String(d)); } },
      { data: 'description', render: function (d) { return Repo.esc(d); } },
      { data: 'ip_address', className: 'text-nowrap', render: function (d) { return Repo.esc(d || ''); } }
    ]
  });

  $('#l-action').on('change', function () { table.ajax.reload(); });
})(jQuery);
