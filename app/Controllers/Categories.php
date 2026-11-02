<?php

namespace App\Controllers;

use App\Models\CategoryModel;

class Categories extends BaseController
{
    public function index()
    {
        return view('categories/index', ['rows' => (new CategoryModel())->listing()]);
    }

    /** Tambah (id kosong) atau ubah (id terisi). */
    public function save()
    {
        $model = new CategoryModel();
        $id    = (int) $this->request->getPost('id');

        $rules = [
            'name'        => ['label' => 'Nama kategori', 'rules' => 'required|max_length[100]|is_unique[categories.name,id,' . $id . ']'],
            'description' => ['label' => 'Deskripsi', 'rules' => 'permit_empty|max_length[255]'],
            'icon'        => ['label' => 'Ikon', 'rules' => 'permit_empty|regex_match[/^bi-[a-z0-9-]{1,40}$/]'],
        ];

        if (! $this->validate($rules)) {
            return redirect()->to(site_url('categories'))
                ->with('error', implode(' ', $this->validator->getErrors()));
        }

        $name = trim((string) $this->request->getPost('name'));
        $data = [
            'name'        => $name,
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'icon'        => trim((string) $this->request->getPost('icon')) ?: 'bi-folder',
            'is_active'   => $this->request->getPost('is_active') ? 1 : 0,
        ];

        if ($id > 0) {
            if ($model->find($id) === null) {
                $this->notFound();
            }
            $data['slug'] = $model->uniqueSlug($name, $id);
            $model->update($id, $data);
            log_activity('category_update', 'Mengubah kategori #' . $id . ' "' . $name . '"');
            $msg = 'Kategori diperbarui.';
        } else {
            $data['slug'] = $model->uniqueSlug($name);
            $newId        = $model->insert($data, true);
            log_activity('category_create', 'Menambah kategori #' . $newId . ' "' . $name . '"');
            $msg = 'Kategori ditambahkan.';
        }

        return $this->flash('categories', 'success', $msg);
    }

    public function delete(int $id)
    {
        $model = new CategoryModel();
        $cat   = $model->find($id);
        if ($cat === null) {
            $this->notFound();
        }

        // Hitung SEMUA berkas (termasuk di tempat sampah) karena relasi dibatasi database.
        if (db_connect()->table('files')->where('category_id', $id)->countAllResults() > 0) {
            return $this->flash('categories', 'error', 'Kategori masih memiliki berkas (termasuk di tempat sampah). Pindahkan berkas atau nonaktifkan kategori.');
        }

        $model->delete($id);
        log_activity('category_delete', 'Menghapus kategori #' . $id . ' "' . $cat['name'] . '"');

        return $this->flash('categories', 'success', 'Kategori dihapus.');
    }
}
