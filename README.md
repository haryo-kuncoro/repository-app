# E-Repository (CodeIgniter 4)

Aplikasi web repositori berkas untuk menyimpan **skripsi, berkas akreditasi, berkas monev, dan dokumen penting lainnya**.
Dibangun dengan **CodeIgniter 4.7**, **Bootstrap 5**, **MySQL/MariaDB**. Semua aset front-end (Bootstrap, Bootstrap Icons,
Nunito, SweetAlert2, DataTables, jQuery) sudah **di-bundle lokal** di `public/assets/vendor/` — tidak perlu Node.js, Python,
Composer, maupun CDN. Cukup unggah lewat File Manager / FTP.

## Fitur

| Area | Rincian |
|---|---|
| Berkas | Unggah, ubah, ganti berkas, hapus ke tempat sampah, pulihkan, hapus permanen, pratinjau PDF/gambar, unduh, hitung unduhan, checksum SHA-256 |
| Pencarian | DataTables server-side (aman untuk ribuan berkas), saring per kategori / tahun / visibilitas |
| Kategori | Skripsi, Akreditasi, Monev, Dokumen Penting (bawaan) — dapat ditambah/diubah |
| Visibilitas | **Umum** (semua pengguna yang berhak) atau **Terbatas** (hanya pengunggah & pengelola) |
| RBAC | Peran + hak akses granular (11 permission), peran kustom, UI pengaturan hak akses |
| Keamanan login | Throttle anti brute-force 3 lapis, captcha ceklis "Saya bukan robot", honeypot, wajib ganti password awal |
| Audit | Log aktivitas (login, gagal login, unggah, unduh, ubah, hapus, akses ditolak) |

## Persyaratan hosting

* PHP **8.2+** (disarankan 8.3) dengan ekstensi: `intl`, `mbstring`, `mysqli` (`mysqlnd`), `json`, `fileinfo`, `curl` (hanya bila memakai reCAPTCHA/Turnstile)
* MySQL 5.7+ / MariaDB 10.3+
* Apache / LiteSpeed dengan `mod_rewrite` dan dukungan `.htaccess` (standar cPanel / DirectAdmin)
* Tidak memerlukan SSH, Composer, Node.js, atau Python

> Ekstensi `intl` kadang belum aktif: cPanel → **Select PHP Version** → **Extensions** → centang `intl`, `mbstring`, `fileinfo`, `mysqli`.

## Instalasi di shared hosting (cPanel)

### 1. Siapkan database
1. cPanel → **MySQL® Databases**: buat database, buat user, tambahkan user ke database dengan **ALL PRIVILEGES**.
2. cPanel → **phpMyAdmin** → pilih database tersebut → tab **Import** → pilih `database/repository.sql` → **Go**.
   (Berisi 9 tabel, 4 peran, 11 permission, 4 kategori, dan 1 akun admin.)

### 2. Unggah berkas — pilih **salah satu** opsi

**Opsi A (disarankan): document root diarahkan ke folder `public/`**
Cocok bila Anda memakai subdomain / domain tambahan (cPanel → **Domains** → atur *Document Root* ke `.../repository/public`).
1. Unggah isi zip ke mis. `/home/USER/repository/` (folder `app`, `system`, `writable`, `public`, dst.).
2. Arahkan document root domain/subdomain ke `/home/USER/repository/public`.
3. Selesai — tidak ada yang perlu diedit di kode.

**Opsi B: document root tetap `public_html`**
Aplikasi diletakkan **di luar** `public_html` agar kode & berkas tidak bisa diakses langsung.
1. Unggah semua **kecuali folder `public/`** ke `/home/USER/repository/`.
2. Unggah **isi** folder `public/` (termasuk `.htaccess`, `.user.ini`, `assets/`) ke `/home/USER/public_html/` (atau subfolder, mis. `public_html/repo/`).
3. Edit `public_html/index.php`, ubah baris:
   ```php
   require FCPATH . '../app/Config/Paths.php';
   ```
   menjadi (sesuaikan jalur):
   ```php
   require '/home/USER/repository/app/Config/Paths.php';
   ```
4. Edit `/home/USER/repository/app/Config/Paths.php`, pastikan tiga jalur menunjuk ke folder yang benar:
   ```php
   public string $systemDirectory   = __DIR__ . '/../../system';
   public string $appDirectory      = __DIR__ . '/..';
   public string $writableDirectory = __DIR__ . '/../../writable';
   ```
   (bawaan sudah benar bila `app`, `system`, `writable` berada sejajar di `/home/USER/repository/`.)

> **Jangan** meletakkan seluruh proyek langsung di `public_html` tanpa mengarahkan document root ke `public/`. `.htaccess` di root proyek sengaja menolak semua akses sebagai pengaman (situs akan menampilkan *403* — itu tandanya aman, bukan error).

### 3. Konfigurasi `.env`
1. Salin `.env.example` → `.env` (di folder root aplikasi, **sejajar dengan `app/`**, bukan di `public/`).
2. Isi minimal:
   ```ini
   CI_ENVIRONMENT = production
   app.baseURL = 'https://repo.domainanda.ac.id/'     # harus diakhiri "/"
   database.default.hostname = localhost
   database.default.database = USERCPANEL_namadb
   database.default.username = USERCPANEL_userdb
   database.default.password = 'password-db'
   ```
3. Setelah SSL aktif (Let's Encrypt / AutoSSL), aktifkan juga `app.forceGlobalSecureRequests = true` dan `cookie.secure = true`.
   Anda dapat membuka komentar aturan **Paksa HTTPS** di `public/.htaccess`.

### 4. Izin folder
Folder `writable/` (dan isinya) harus dapat ditulis PHP: umumnya `755` (atau `775`) sudah cukup di cPanel. Berkas unggahan
tersimpan di `writable/uploads/repository/` — **di luar web root dan dilindungi `.htaccess`**, hanya dapat diunduh lewat aplikasi
setelah login & cek hak akses.

### 5. Login pertama
* URL: `https://domain-anda/login`
* Username: `admin` — Password awal: `Admin@Repo2026!`
* Anda **dipaksa mengganti password** pada login pertama. **Lakukan segera setelah impor database selesai** (password awal bersifat publik).
* Setelah itu: ubah email admin di menu **Profil**, buat pengguna/peran sesuai kebutuhan, lalu atur nama institusi di `.env`
  (`repository.appName`, `repository.institution`).

## RBAC

Hak akses dikelola di menu **Peran & Hak Akses**. Peran bawaan:

| Peran | Kemampuan |
|---|---|
| **Super Admin** | Semua hak akses (tidak dapat diubah/dihapus; selalu minimal 1 aktif) |
| **Admin** | Kelola berkas (semua), kategori, pengguna, lihat log |
| **Operator** | Unggah & kelola berkas **miliknya sendiri** |
| **Pengguna** | Lihat & unduh berkas umum |

Daftar permission: `dashboard.view`, `files.view`, `files.download`, `files.create`, `files.edit`, `files.delete`,
`files.manage_all`, `categories.manage`, `users.manage`, `roles.manage`, `logs.view`.
Menambah permission baru: tambahkan di `app/Config/Rbac.php`, tambahkan juga barisnya ke tabel `permissions`
(atau jalankan `php spark db:seed InitialSeeder` bila ada akses CLI), lalu proteksi route dengan filter `perm:nama.permission`
di `app/Config/Routes.php`.

Aturan penting: pengguna non-Super Admin **tidak dapat** melihat/mengubah akun Super Admin, memberikan peran Super Admin,
atau memberikan hak akses yang tidak ia miliki sendiri.

## Keamanan yang diterapkan

* **Login throttle** (tabel `login_attempts`): 5 gagal per *username+IP*, 15 gagal per *username* dari semua IP, 20 gagal per *IP* → kunci 15 menit (dapat diatur). Percobaan saat terkunci tidak memperpanjang kunci. Waktu respons disamakan agar username tidak bisa ditebak (*timing attack*).
* **Captcha "Saya bukan robot"**: ceklis dengan token sekali pakai terikat sesi, waktu minimal ditentukan **server**, wajib ada interaksi pengguna nyata, menolak browser otomatis (`navigator.webdriver`), plus honeypot. Opsional diganti **Google reCAPTCHA v2** atau **Cloudflare Turnstile** lewat `.env` (`repository.captchaDriver`).
* **Sesi**: ID sesi diganti saat login (anti *session fixation*), terikat User-Agent, cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` saat HTTPS), akun yang dinonaktifkan langsung terputus pada request berikutnya.
* **CSRF** pada semua POST (token acak per-form), **XSS** dicegah dengan escape di semua keluaran, **SQL Injection** dicegah dengan Query Builder berparameter + whitelist kolom pengurutan.
* **Unggah aman**: whitelist ekstensi, tipe MIME dibaca dari **isi** berkas, cek *magic bytes*, tolak *double extension* (`a.php.pdf`), nama berkas di disk **acak** berekstensi `.upl`, disimpan di luar web root, dikirim sebagai `attachment` + `nosniff`.
* **Password**: bcrypt/`PASSWORD_DEFAULT`, kebijakan min. 10 karakter (huruf besar+kecil+angka), tidak boleh memuat username atau kata umum.
* **Header**: CSP ketat (tanpa inline script & tanpa CDN), `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS (HTTPS), `Cache-Control: no-store` untuk halaman internal.
* **Hardening hosting**: folder `app/`, `system/`, `writable/` dan root proyek menolak akses langsung; `.env`, `.git`, `*.sql`, `*.log` dll. diblokir; auto-routing CI4 dimatikan (semua route eksplisit); mode `production` tidak menampilkan detail error.
* **Audit**: semua kejadian penting dicatat beserta IP & waktu.

### Catatan jujur
* Captcha `builtin` efektif menahan bot sederhana dan sudah dilapisi throttle, tetapi **bukan** pengganti reCAPTCHA/Turnstile terhadap bot canggih. Bila repositori Anda terbuka di internet, aktifkan salah satunya (gratis).
* Aplikasi **tidak memindai virus**. Pertimbangkan memindai folder `writable/uploads/` berkala (mis. ClamAV bila hosting menyediakan).
* Di belakang Cloudflare / reverse proxy, isi `app.proxyIPs` agar IP asli pengguna terbaca (penting untuk throttle & log).
* **Backup rutin**: database (phpMyAdmin → Export) **dan** folder `writable/uploads/`.

## Struktur singkat

```
app/Config/Repository.php   pengaturan aplikasi (throttle, captcha, unggah)
app/Config/Rbac.php         katalog permission & peran bawaan
app/Controllers/            Auth, Dashboard, Files, Categories, Users, Roles, Logs, Profile, Captcha
app/Filters/                AuthFilter, GuestFilter, PermissionFilter, SecurityHeaders
app/Libraries/              Auth (sesi+RBAC), Throttle, Captcha, Uploader
app/Database/               Migrations + Seeds
database/repository.sql     skema + data awal untuk phpMyAdmin
public/                     document root (index.php, .htaccess, assets/)
writable/                   logs, session, cache, uploads (di luar web root pada Opsi A/B)
```

## Pemecahan masalah

| Gejala | Solusi |
|---|---|
| Halaman putih / *500* | Lihat `writable/logs/log-*.log`. Penyebab umum: ekstensi `intl` belum aktif, `.env` salah, `writable/` tidak bisa ditulis |
| *403 Forbidden* di seluruh situs | Document root belum diarahkan ke `public/` (lihat Opsi A/B) |
| *Unable to connect to the database* | Periksa `database.default.*` di `.env` (di cPanel, nama DB & user berawalan nama akun) |
| Link/CSS rusak atau redirect aneh | `app.baseURL` di `.env` salah atau tidak diakhiri `/` |
| Unggahan gagal untuk berkas besar | Naikkan `upload_max_filesize` & `post_max_size` (cPanel → *MultiPHP INI Editor*) dan `repository.maxUploadKb` |
| Tidak bisa login padahal benar | Kemungkinan terkunci throttle (tunggu 15 menit) atau cookie sesi tidak tersimpan (cek `cookie.secure` saat belum HTTPS) |
| Membuka kunci manual | phpMyAdmin → `DELETE FROM login_attempts;` |

## Lisensi
Kode aplikasi bebas dipakai & dimodifikasi untuk institusi Anda. CodeIgniter 4 (MIT), Bootstrap (MIT), Bootstrap Icons (MIT),
SweetAlert2 (MIT), DataTables (MIT), jQuery (MIT), Nunito (SIL OFL 1.1).
