<?php

namespace App\Controllers;

use App\Libraries\Uploader;
use App\Models\CategoryModel;
use App\Models\FileModel;

class Files extends BaseController
{
    private FileModel $files;
    private Uploader $uploader;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->files    = new FileModel();
        $this->uploader = new Uploader();
    }

    // ------------------------------------------------------------------
    // Daftar
    // ------------------------------------------------------------------
    public function index()
    {
        $me = $this->me();

        return view('files/index', [
            'categories' => (new CategoryModel())->active(),
            'years'      => $this->files->years((int) $me['id'], can('files.manage_all')),
        ]);
    }

    /** JSON untuk DataTables server-side. */
    public function data()
    {
        $me        = $this->me();
        $manageAll = can('files.manage_all');
        $result    = $this->files->datatable($this->request->getGet(), (int) $me['id'], $manageAll);

        $canDownload = can('files.download');
        $rows        = [];
        foreach ($result['data'] as $r) {
            $own    = (int) $r['uploaded_by'] === (int) $me['id'];
            $rows[] = [
                'id'            => (int) $r['id'],
                'title'         => $r['title'],
                'category'      => $r['category_name'],
                'author'        => $r['author'],
                'year'          => $r['doc_year'],
                'extension'     => $r['extension'],
                'size'          => format_bytes($r['file_size']),
                'size_raw'      => (int) $r['file_size'],
                'visibility'    => $r['visibility'],
                'uploader'      => $r['uploader_name'],
                'created_at'    => $r['created_at'],
                'can_download'  => $canDownload,
                'can_edit'      => $manageAll || ($own && can('files.edit')),
                'can_delete'    => $manageAll || ($own && can('files.delete')),
            ];
        }
        $result['data'] = $rows;

        return $this->response->setJSON($result);
    }

    // ------------------------------------------------------------------
    // Detail
    // ------------------------------------------------------------------
    public function show(int $id)
    {
        $file = $this->accessible($id);

        return view('files/show', [
            'f'          => $file,
            'canEdit'    => $this->canModify($file, 'files.edit'),
            'canDelete'  => $this->canModify($file, 'files.delete'),
            'canPreview' => can('files.download') && in_array($file['extension'], ['pdf', 'jpg', 'jpeg', 'png'], true),
        ]);
    }

    // ------------------------------------------------------------------
    // Tambah
    // ------------------------------------------------------------------
    public function create()
    {
        return view('files/form', [
            'f'          => null,
            'categories' => (new CategoryModel())->active(),
        ]);
    }

    public function store()
    {
        $me = $this->me();

        if (($errors = $this->validateMeta()) !== []) {
            return $this->failForm('files/create', $errors);
        }

        $up = $this->uploader->store($this->request->getFile('file'));
        if (! $up['ok']) {
            return $this->failForm('files/create', ['file' => $up['error']]);
        }

        $row = $this->metaFromPost() + $up['data'] + [
            'uploaded_by'    => (int) $me['id'],
            'download_count' => 0,
        ];

        try {
            $id = $this->files->insert($row, true);
        } catch (\Throwable $e) {
            $this->uploader->delete($up['data']['stored_name']);
            log_message('error', 'Simpan berkas gagal: ' . $e->getMessage());

            return $this->failForm('files/create', ['file' => 'Berkas gagal disimpan ke database.']);
        }

        log_activity('file_upload', 'Mengunggah berkas #' . $id . ' "' . $row['title'] . '" (' . format_bytes($row['file_size']) . ')');

        return $this->flash('files/' . $id, 'success', 'Berkas berhasil diunggah.');
    }

    // ------------------------------------------------------------------
    // Ubah
    // ------------------------------------------------------------------
    public function edit(int $id)
    {
        $file = $this->accessible($id);
        $this->requireModify($file, 'files.edit');

        return view('files/form', [
            'f'          => $file,
            'categories' => (new CategoryModel())->active(),
        ]);
    }

    public function update(int $id)
    {
        $file = $this->accessible($id);
        $this->requireModify($file, 'files.edit');

        if (($errors = $this->validateMeta()) !== []) {
            return $this->failForm('files/' . $id . '/edit', $errors);
        }

        $row    = $this->metaFromPost();
        $oldRef = null;
        $new    = $this->request->getFile('file');

        // Ganti berkas fisik (opsional)
        if ($new !== null && $new->isValid() && $new->getSize() > 0) {
            $up = $this->uploader->store($new);
            if (! $up['ok']) {
                return $this->failForm('files/' . $id . '/edit', ['file' => $up['error']]);
            }
            $row   += $up['data'];
            $oldRef = $file['stored_name'];
        } elseif ($new !== null && $new->getError() !== UPLOAD_ERR_NO_FILE && ! $new->isValid()) {
            return $this->failForm('files/' . $id . '/edit', ['file' => 'Berkas gagal diunggah.']);
        }

        try {
            $this->files->update($id, $row);
        } catch (\Throwable $e) {
            if (isset($up['data'])) {
                $this->uploader->delete($up['data']['stored_name']);
            }
            log_message('error', 'Ubah berkas gagal: ' . $e->getMessage());

            return $this->failForm('files/' . $id . '/edit', ['title' => 'Perubahan gagal disimpan.']);
        }

        if ($oldRef !== null) {
            $this->uploader->delete($oldRef);
        }
        log_activity('file_update', 'Mengubah berkas #' . $id . ' "' . $row['title'] . '"' . ($oldRef !== null ? ' (berkas diganti)' : ''));

        return $this->flash('files/' . $id, 'success', 'Berkas berhasil diperbarui.');
    }

    // ------------------------------------------------------------------
    // Hapus (soft delete) / tempat sampah
    // ------------------------------------------------------------------
    public function delete(int $id)
    {
        $file = $this->accessible($id);
        $this->requireModify($file, 'files.delete');

        $this->files->delete($id); // soft delete
        log_activity('file_delete', 'Memindahkan berkas #' . $id . ' "' . $file['title'] . '" ke tempat sampah');

        return $this->flash('files', 'success', 'Berkas dipindahkan ke tempat sampah.');
    }

    public function trash()
    {
        return view('files/trash', ['rows' => $this->files->trashed()]);
    }

    public function restore(int $id)
    {
        $file = $this->files->detail($id, true);
        if ($file === null || $file['deleted_at'] === null) {
            $this->notFound();
        }

        db_connect()->table('files')->where('id', $id)->update(['deleted_at' => null]);
        log_activity('file_restore', 'Memulihkan berkas #' . $id . ' "' . $file['title'] . '"');

        return $this->flash('files/trash', 'success', 'Berkas dipulihkan.');
    }

    public function purge(int $id)
    {
        $file = $this->files->detail($id, true);
        if ($file === null || $file['deleted_at'] === null) {
            $this->notFound();
        }

        db_connect()->table('files')->where('id', $id)->delete();
        $this->uploader->delete($file['stored_name']);
        log_activity('file_purge', 'Menghapus permanen berkas #' . $id . ' "' . $file['title'] . '"');

        return $this->flash('files/trash', 'success', 'Berkas dihapus permanen.');
    }

    // ------------------------------------------------------------------
    // Unduh / pratinjau (selalu lewat controller: login + hak akses)
    // ------------------------------------------------------------------
    public function download(int $id)
    {
        $file = $this->accessible($id);
        $path = $this->uploader->path($file['stored_name']);
        if ($path === null) {
            log_message('error', 'Berkas fisik hilang untuk id ' . $id);

            return $this->flash('files/' . $id, 'error', 'Berkas fisik tidak ditemukan di server. Hubungi administrator.');
        }

        db_connect()->table('files')->set('download_count', 'download_count + 1', false)->where('id', $id)->update();
        log_activity('file_download', 'Mengunduh berkas #' . $id . ' "' . $file['title'] . '"');

        return $this->response
            ->download($path, null)
            ->setFileName($this->downloadName($file));
    }

    public function preview(int $id)
    {
        $file = $this->accessible($id);
        if (! in_array($file['extension'], ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            return $this->flash('files/' . $id, 'warning', 'Pratinjau hanya tersedia untuk PDF dan gambar.');
        }
        $path = $this->uploader->path($file['stored_name']);
        if ($path === null) {
            return $this->flash('files/' . $id, 'error', 'Berkas fisik tidak ditemukan di server.');
        }

        log_activity('file_preview', 'Melihat berkas #' . $id . ' "' . $file['title'] . '"');

        $mime = $file['extension'] === 'pdf' ? 'application/pdf' : ($file['extension'] === 'png' ? 'image/png' : 'image/jpeg');
        $resp = $this->response->download($path, null)->setFileName($this->downloadName($file))->inline();
        $resp->setContentType($mime, '');
        $resp->setHeader('Content-Security-Policy', "frame-ancestors 'none'");

        return $resp;
    }

    // ------------------------------------------------------------------
    // Util
    // ------------------------------------------------------------------

    /** Ambil berkas bila pengguna boleh melihatnya; selain itu 404 (tidak membocorkan keberadaan). */
    private function accessible(int $id): array
    {
        $file = $this->files->detail($id);
        if ($file === null) {
            $this->notFound();
        }

        $me = $this->me();
        if ($file['visibility'] === 'terbatas' && (int) $file['uploaded_by'] !== (int) $me['id'] && ! can('files.manage_all')) {
            $this->notFound();
        }

        return $file;
    }

    private function canModify(array $file, string $permission): bool
    {
        if (can('files.manage_all')) {
            return true;
        }

        return (int) $file['uploaded_by'] === (int) $this->me()['id'] && can($permission);
    }

    private function requireModify(array $file, string $permission): void
    {
        if (! $this->canModify($file, $permission)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }

    /** @return array<string,string> field => pesan error */
    private function validateMeta(): array
    {
        $maxYear = (int) date('Y') + 1;
        $rules   = [
            'title'       => ['label' => 'Judul', 'rules' => 'required|max_length[255]'],
            'category_id' => ['label' => 'Kategori', 'rules' => 'required|is_natural_no_zero|is_not_unique[categories.id]'],
            'author'      => ['label' => 'Penulis/Pemilik', 'rules' => 'permit_empty|max_length[150]'],
            'doc_year'    => ['label' => 'Tahun', 'rules' => "permit_empty|integer|greater_than_equal_to[1900]|less_than_equal_to[{$maxYear}]"],
            'keywords'    => ['label' => 'Kata kunci', 'rules' => 'permit_empty|max_length[255]'],
            'description' => ['label' => 'Deskripsi', 'rules' => 'permit_empty|max_length[5000]'],
            'visibility'  => ['label' => 'Visibilitas', 'rules' => 'required|in_list[umum,terbatas]'],
        ];

        return $this->validate($rules) ? [] : $this->validator->getErrors();
    }

    /** @return array<string,mixed> */
    private function metaFromPost(): array
    {
        $p    = $this->request;
        $year = trim((string) $p->getPost('doc_year'));

        return [
            'category_id' => (int) $p->getPost('category_id'),
            'title'       => trim((string) $p->getPost('title')),
            'description' => trim((string) $p->getPost('description')) ?: null,
            'author'      => trim((string) $p->getPost('author')) ?: null,
            'doc_year'    => $year !== '' ? (int) $year : null,
            'keywords'    => trim((string) $p->getPost('keywords')) ?: null,
            'visibility'  => (string) $p->getPost('visibility'),
        ];
    }

    private function failForm(string $to, array $errors)
    {
        return redirect()->to(site_url($to))
            ->with('errors', $errors)
            ->with('old', $this->request->getPost(['title', 'category_id', 'author', 'doc_year', 'keywords', 'description', 'visibility']));
    }

    /** Nama berkas unduhan: judul + ekstensi asli, aman untuk header. */
    private function downloadName(array $file): string
    {
        $base = trim(preg_replace('/[^\p{L}\p{N}\s._()-]+/u', '', (string) $file['title']) ?? '');
        $base = mb_substr($base !== '' ? $base : 'berkas', 0, 120);

        return $base . '.' . $file['extension'];
    }
}
