/* Daftar berkas: DataTables server-side. */
(function ($) {
  'use strict';

  var tableEl = document.getElementById('files-table');
  if (!tableEl) { return; }

  var iconFor = function (ext) {
    switch (String(ext).toLowerCase()) {
      case 'pdf': return 'bi-file-earmark-pdf text-danger';
      case 'doc': case 'docx': case 'odt': return 'bi-file-earmark-word text-primary';
      case 'xls': case 'xlsx': case 'ods': case 'csv': return 'bi-file-earmark-excel text-success';
      case 'ppt': case 'pptx': case 'odp': return 'bi-file-earmark-slides text-warning';
      case 'zip': return 'bi-file-earmark-zip text-secondary';
      case 'jpg': case 'jpeg': case 'png': return 'bi-file-earmark-image text-info';
      default: return 'bi-file-earmark text-secondary';
    }
  };

  var months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  var fmtDate = function (s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || '');
    return m ? parseInt(m[3], 10) + ' ' + months[parseInt(m[2], 10) - 1] + ' ' + m[1] : '';
  };

  var table = $(tableEl).DataTable({
    serverSide: true,
    processing: true,
    language: Repo.dtLanguage,
    pageLength: 10,
    order: [[6, 'desc']],
    ajax: {
      url: tableEl.getAttribute('data-url'),
      type: 'GET',
      data: function (d) {
        d.category_id = $('#f-category').val();
        d.year = $('#f-year').val();
        d.visibility = $('#f-visibility').val();
      },
      error: function (xhr) {
        var msg = xhr.status === 401 ? 'Sesi Anda berakhir. Muat ulang halaman lalu login kembali.' : 'Gagal memuat data.';
        Swal.fire({ icon: 'error', text: msg, confirmButtonColor: '#1f6feb' });
      }
    },
    columns: [
      { data: 'extension', orderable: false, searchable: false, className: 'text-center', width: '48px',
        render: function (d) { return '<i class="bi ' + iconFor(d) + ' file-ico"></i>'; } },
      { data: 'title',
        render: function (d, t, row) {
          var lock = row.visibility === 'terbatas' ? ' <i class="bi bi-lock-fill text-warning" title="Terbatas"></i>' : '';
          return '<a class="title-link" href="' + Repo.url('files/' + row.id) + '">' + Repo.esc(d) + '</a>' + lock +
            '<div class="small text-muted">' + Repo.esc(String(row.extension).toUpperCase()) + ' · oleh ' + Repo.esc(row.uploader || '-') + '</div>';
        } },
      { data: 'category', render: function (d) { return d ? '<span class="badge badge-soft">' + Repo.esc(d) + '</span>' : ''; } },
      { data: 'author', render: function (d) { return Repo.esc(d || '—'); } },
      { data: 'year', className: 'text-center', render: function (d) { return Repo.esc(d || '—'); } },
      { data: 'size_raw', className: 'text-nowrap', render: function (d, t, row) { return t === 'display' ? Repo.esc(row.size) : d; } },
      { data: 'created_at', className: 'text-nowrap', render: function (d, t) { return t === 'display' ? Repo.esc(fmtDate(d)) : d; } },
      { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (d, t, row) {
        var h = '<a class="btn btn-sm btn-outline-secondary btn-icon" title="Detail" href="' + Repo.url('files/' + row.id) + '"><i class="bi bi-eye"></i></a> ';
        if (row.can_download) {
          h += '<a class="btn btn-sm btn-outline-primary btn-icon" title="Unduh" href="' + Repo.url('files/' + row.id + '/download') + '"><i class="bi bi-download"></i></a> ';
        }
        if (row.can_edit) {
          h += '<a class="btn btn-sm btn-outline-secondary btn-icon" title="Ubah" href="' + Repo.url('files/' + row.id + '/edit') + '"><i class="bi bi-pencil"></i></a> ';
        }
        if (row.can_delete) {
          h += '<a href="#" class="btn btn-sm btn-outline-danger btn-icon" title="Hapus" data-post="' + Repo.url('files/' + row.id + '/delete') + '"' +
            ' data-confirm-title="Pindahkan ke tempat sampah?" data-confirm="' + Repo.esc('“' + row.title + '” akan dipindahkan ke tempat sampah.') + '"' +
            ' data-confirm-button="Ya, hapus"><i class="bi bi-trash"></i></a>';
        }
        return h;
      } }
    ]
  });

  $('#f-category, #f-year, #f-visibility').on('change', function () { table.ajax.reload(); });
  $('#f-reset').on('click', function () {
    $('#f-category, #f-year, #f-visibility').val('');
    table.search('').ajax.reload();
  });
})(jQuery);
