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
| RBAC | Peran + hak akses granular (13 permission), peran kustom, UI pengaturan hak akses |
| Keamanan login | Throttle anti brute-force 3 lapis, captcha ceklis "Saya bukan robot", honeypot, wajib ganti password awal |
| Audit | Log aktivitas (login, gagal login, unggah, unduh, ubah, hapus, akses ditolak, virus diblokir) |
| Pemindai virus | Setiap unggahan dipindai sebelum disimpan: **ClamAV** (bila tersedia) + pemindai heuristik bawaan PHP. Berkas berbahaya ditolak, bisa dipindai ulang, dan yang terdeteksi belakangan dikarantina (tidak bisa diunduh) |
| Backup & pulihkan | Backup **database + seluruh berkas** dalam satu ZIP (terverifikasi SHA-256), unduh, unggah, pulihkan dengan konfirmasi password, backup pengaman otomatis + rollback, perintah `spark` untuk cron |

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
`files.manage_all`, `categories.manage`, `users.manage`, `roles.manage`, `logs.view`, `backup.manage`, `backup.restore`.
(`backup.*` hanya dimiliki Super Admin secara bawaan; berikan ke peran lain hanya bila benar-benar perlu.)
Menambah permission baru: tambahkan di `app/Config/Rbac.php`, tambahkan juga barisnya ke tabel `permissions`
(atau jalankan `php spark db:seed InitialSeeder` bila ada akses CLI), lalu proteksi route dengan filter `perm:nama.permission`
di `app/Config/Routes.php`.

Aturan penting: pengguna non-Super Admin **tidak dapat** melihat/mengubah akun Super Admin, memberikan peran Super Admin,
atau memberikan hak akses yang tidak ia miliki sendiri.

## Pemindai virus / malware

Setiap berkas yang diunggah (dan setiap berkas pengganti) dipindai **sebelum** disimpan. Ada dua lapis:

| Lapis | Tersedia di shared hosting? | Yang dideteksi |
|---|---|---|
| **Heuristik bawaan** (murni PHP) | Selalu | berkas uji EICAR, web shell PHP/ASP tertanam (termasuk di dalam gambar/PDF/teks), makro VBA di Office (`.doc/.xls/.ppt`, `.docx/.xlsx/.pptx`) dan makro ODF, PDF berisi JavaScript/Launch/berkas tertanam (nama yang disamarkan `#hex` ikut terbaca), program Windows/Linux di dalam arsip (walau diganti nama), zip bomb, path traversal, arsip terenkripsi, struktur dokumen palsu, rumus DDE pada CSV |
| **ClamAV** | Hanya bila hosting/VPS menyediakannya | Basis signature virus/malware ClamAV (jutaan signature), termasuk isi arsip terkompresi |

**Jujur tentang batasnya:** pemindai heuristik **bukan antivirus**. Ia menangkap pola berbahaya yang umum, tetapi tidak punya basis signature malware dan tidak bisa melihat isi yang terkompresi di dalam objek PDF tertentu. Deteksi malware sesungguhnya hanya datang dari ClamAV. Hampir semua shared hosting **tidak** menyediakan ClamAV; karena itu aplikasi mendukung `clamd` di server lain lewat TCP.

### Mode (`repository.scanMode` di `.env`)

| Mode | Perilaku |
|---|---|
| `auto` *(bawaan)* | Heuristik selalu jalan; ClamAV dipakai otomatis bila terdeteksi. Bila ClamAV tidak ada, hanya heuristik (form unggah menampilkan peringatan) |
| `clamav` | ClamAV **wajib**. Bila tidak tersedia atau gagal memindai, unggahan **ditolak** (gagal tertutup) |
| `builtin` | Hanya heuristik |
| `off` | Tanpa pemindaian (tidak disarankan) |

### Menyambungkan ClamAV
* **VPS/server sendiri:** `apt install clamav-daemon` lalu `freshclam` (pembaruan signature). Aplikasi menemukan socket umum (`/var/run/clamav/clamd.ctl`, dst.) otomatis.
* **Shared hosting tanpa ClamAV:** jalankan `clamd` di VPS kecil (mis. 1 GB RAM), buka port 3310 **hanya untuk IP hosting Anda** (firewall), lalu isi:
  ```ini
  repository.scanMode = clamav
  repository.clamdHost = 203.0.113.10
  repository.clamdPort = 3310
  ```
  Berkas dikirim ke daemon lewat protokol `INSTREAM` (tidak perlu berbagi folder). **Jangan** membuka clamd ke internet tanpa firewall: protokolnya tidak terautentikasi dan tidak terenkripsi.
* Biner `clamscan` didukung sebagai cadangan, tetapi memuat seluruh database tiap pemindaian (lambat dan boros memori) sehingga tidak cocok untuk hosting biasa.

### Berkas yang sudah tersimpan
* Halaman detail berkas menampilkan status pemindaian, mesin yang dipakai, dan waktunya. Admin (`files.manage_all`) punya tombol **Pindai Ulang**.
* Berkas berstatus `infected` / `blocked` **diblokir dari unduhan dan pratinjau** untuk semua orang (termasuk admin) sampai dihapus.
* Pindai massal / terjadwal (butuh `php` CLI, tersedia di cron cPanel). Signature baru bisa mendeteksi berkas lama, jadi jalankan berkala:
  ```
  0 2 * * 0  /usr/local/bin/php /home/USER/repository/spark repository:scan --all
  ```
* Aturan arsip dapat disesuaikan di `app/Config/Repository.php` (`scanBlockedInArchive`, `scanBlockMacros`, `scanBlockActivePdf`, `scanBlockEncryptedArchives`). Catatan: zip yang berisi `.php`/`.exe`/`.sh` **ditolak** secara bawaan; ubah daftar itu bila mahasiswa Anda perlu mengunggah kode sumber PHP.
* Heuristik dapat memberi **false positive** (mis. PDF/teks yang memuat kode `<?php` secara harfiah). Pesan penolakan menyebut alasannya.

## Backup & pemulihan

Menu **Backup & Pulihkan** (hanya Super Admin secara bawaan). Satu ZIP berisi `database.sql`, seluruh berkas unggahan,
dan manifest berisi SHA-256 setiap berkas. Backup disimpan di `writable/backups/` (di luar webroot, dilindungi `.htaccess`,
nama acak) dan **tidak pernah** dapat diakses langsung lewat URL — hanya lewat tombol Unduh bagi yang berhak.

**Persyaratan:** ekstensi PHP `zip` (umumnya aktif) dan ruang disk bebas minimal ± ukuran data (pemulihan butuh ± 2×, karena membuat backup pengaman).

### Membuat backup
* Tombol **Buat Backup Sekarang** (centang/lepas "Sertakan seluruh berkas unggahan").
* Otomatis lewat cron (cPanel → Cron Jobs), mis. tiap hari 02.00 dan simpan 7 terbaru:
  `0 2 * * * /usr/local/bin/php /home/akun/repository-app/spark repository:backup --auto --keep 7`
* Opsi: `--db-only` (tanpa berkas). Backup otomatis yang lebih lama dihapus; backup **manual tidak pernah dihapus otomatis**.
* **Unduh salinan backup ke komputer/penyimpanan lain secara berkala** — backup yang hanya ada di server yang sama tidak menolong bila server rusak.

### Memulihkan
1. Pilih backup di daftar → **Pulihkan** → masukkan password Anda dan ketik `PULIHKAN`.
2. Sistem memvalidasi seluruh ZIP, **membuat backup pengaman** (`prerestore`), lalu mengganti database dan folder berkas.
   Bila langkah mana pun gagal, kondisi sebelumnya dikembalikan otomatis.
3. Anda keluar dari sesi dan login ulang dengan akun yang ada **di backup** (tabel pengguna ikut dipulihkan).

Backup dari luar: unggah lewat form (batas unggah PHP, biasanya 20 MB), atau untuk berkas besar unggah via FTP ke `writable/backups/`
lalu klik **Pindai folder backup**. Lewat CLI: `php spark repository:restore NAMA.zip --yes` (atau path ZIP; `--list` untuk daftar).
Aplikasi harus sudah terpasang (tabel sudah ada) sebelum memulihkan ke server baru: impor `database/repository.sql` lebih dulu.
Backup dari versi aplikasi lebih lama dipulihkan lalu skemanya dimigrasi otomatis; dari versi lebih baru ditolak.

### Keamanan fitur ini
* Hak akses terpisah (`backup.manage`, `backup.restore`), CSRF, pembatasan percobaan password pada pemulihan, semua aksi tercatat di log.
* Nama backup divalidasi regex + `realpath` (tidak ada path-traversal). ZIP **tidak diekstrak** dengan `extractTo`: tiap entri diperiksa
  (nama, symlink, ukuran, jumlah, SHA-256) dan ditulis lewat stream ke folder staging (anti zip-slip & zip-bomb).
* SQL dari backup **tidak dijalankan mentah**: setiap baris harus cocok daftar putih (DROP/CREATE/INSERT untuk tabel aplikasi saja)
  dan seluruh berkas divalidasi sebelum satu baris pun dieksekusi. Ini mencegah ZIP yang dimanipulasi menjalankan perintah lain.
* Backup berisi data sangat sensitif (hash password, semua berkas). Belum dienkripsi — simpan di lokasi aman.

## Keamanan yang diterapkan

* **Login throttle** (tabel `login_attempts`): 5 gagal per *username+IP*, 15 gagal per *username* dari semua IP, 20 gagal per *IP* → kunci 15 menit (dapat diatur). Percobaan saat terkunci tidak memperpanjang kunci. Waktu respons disamakan agar username tidak bisa ditebak (*timing attack*).
* **Captcha "Saya bukan robot"**: ceklis dengan token sekali pakai terikat sesi, waktu minimal ditentukan **server**, wajib ada interaksi pengguna nyata, menolak browser otomatis (`navigator.webdriver`), plus honeypot. Opsional diganti **Google reCAPTCHA v2** atau **Cloudflare Turnstile** lewat `.env` (`repository.captchaDriver`).
* **Sesi**: ID sesi diganti saat login (anti *session fixation*), terikat User-Agent, cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` saat HTTPS), akun yang dinonaktifkan langsung terputus pada request berikutnya.
* **CSRF** pada semua POST (token acak per-form), **XSS** dicegah dengan escape di semua keluaran, **SQL Injection** dicegah dengan Query Builder berparameter + whitelist kolom pengurutan.
* **Unggah aman**: whitelist ekstensi, tipe MIME dibaca dari **isi** berkas, cek *magic bytes*, tolak *double extension* (`a.php.pdf`), nama berkas di disk **acak** berekstensi `.upl`, disimpan di luar web root, dikirim sebagai `attachment` + `nosniff`.
* **Password**: bcrypt/`PASSWORD_DEFAULT`, kebijakan min. 10 karakter (huruf besar+kecil+angka), tidak boleh memuat username atau kata umum.
* **Header**: CSP ketat (tanpa inline script & tanpa CDN), `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS (HTTPS), `Cache-Control: no-store` untuk halaman internal.
* **Hardening hosting**: folder `app/`, `system/`, `writable/` dan root proyek menolak akses langsung; `.env`, `.git`, `*.sql`, `*.log` dll. diblokir; auto-routing CI4 dimatikan (semua route eksplisit); mode `production` tidak menampilkan detail error.
* **Pemindaian virus** pada setiap unggahan (lihat bagian di atas) + karantina berkas terdeteksi.
* **Penjaga tipe input** (`InputGuard`): parameter berbentuk array (`title[]=x`, `search[value][]=x`) di tempat yang tidak semestinya ditolak HTTP 400 sebelum menyentuh controller.
* **Header keamanan di semua respons**, termasuk redirect, 403, 404, dan unduhan; `Secure` cookie otomatis bila diakses lewat HTTPS; `DBDebug` otomatis mati di produksi (tidak membocorkan SQL).
* **Audit**: semua kejadian penting dicatat beserta IP & waktu.

### Hasil audit keamanan (apa yang diuji, bukan sekadar diklaim)

Diuji otomatis terhadap aplikasi yang berjalan (MySQL asli, Apache + mod_php, dan Chromium):

| Area | Pengujian | Hasil |
|---|---|---|
| SQL Injection | 14 payload klasik + *time-based* (`SLEEP`) + *UNION* pada semua parameter DataTables, filter, form (kategori, profil, pengguna, peran, berkas), dan login | Tidak ada yang tembus; tidak ada error SQL bocor; tabel & data utuh |
| CSRF | Semua rute POST: tanpa token, token palsu, dan logout tanpa token | Tidak ada perubahan data; sesi tidak bisa diputus lewat CSRF |
| Otorisasi (RBAC) | 41 rute × anonim / pengguna berhak terendah (token valid) / admin; metode `PUT`/`DELETE`/`PATCH`; `GET` ke rute hapus | 119 pemeriksaan lulus; tidak ada akses tanpa hak; eskalasi ke Super Admin ditolak |
| Brute force | Throttle 3 lapis, captcha dipakai ulang di sesi lain, honeypot, password spraying per-IP | Terkunci sesuai aturan |
| XSS | Payload di semua kolom; tampilan semua halaman daftar, JSON DataTables, dan konten tersimpan | Selalu ter-escape; CSP tanpa inline script |
| Unggah | `.php`, `.html`, PHP menyamar PDF, `a.php.pdf`, PHP di dalam PNG, makro, PDF aktif, zip bomb, exe di arsip, EICAR | Semua ditolak; PDF/DOCX/ZIP/PNG sah diterima |
| Tipe input | Array, nilai 50 KB, null byte, Unicode, JSON rusak | Tidak ada HTTP 500 (satu bug ditemukan & diperbaiki saat audit) |
| Pustaka front-end | `npm audit` untuk Bootstrap, jQuery, DataTables, SweetAlert2, Bootstrap Icons, Nunito | 0 kerentanan diketahui |
| Hosting | `.env`, `.git`, `.sql`, `app/`, `system/`, `writable/` diakses lewat HTTP | 403/404; di HTTPS: cookie `Secure`, HSTS, CSP hadir |

Pengujian ini membuktikan **tidak ditemukan** celah pada vektor yang diuji; ini **bukan jaminan** bebas celah. Untuk sistem yang sangat sensitif, lakukan uji penetrasi independen.

### Batasan yang perlu Anda ketahui
* **Tanpa 2FA dan tanpa reset password lewat email.** Pengguna lupa password → admin mereset di menu Pengguna.
* **Penguncian login bisa dimanfaatkan penyerang untuk "mengunci" akun tertentu** (15 percobaan gagal/username dalam 15 menit dari IP mana pun). Itu kompromi yang disengaja terhadap serangan terdistribusi. Buka kunci: `php spark repository:unlock admin` atau phpMyAdmin → `DELETE FROM login_attempts;`. Captcha builtin tidak menghalangi skrip yang sengaja meniru ceklis; aktifkan reCAPTCHA/Turnstile bila ini menjadi masalah.
* CSP mengizinkan `style-src 'unsafe-inline'` (dibutuhkan SweetAlert2 dan atribut `style`); skrip tetap hanya dari `'self'`.
* Tidak ada WAF dan tidak ada pembatasan laju untuk selain login; untuk itu gunakan fitur hosting/Cloudflare.

### Catatan jujur
* Captcha `builtin` efektif menahan bot sederhana dan sudah dilapisi throttle, tetapi **bukan** pengganti reCAPTCHA/Turnstile terhadap bot canggih. Bila repositori Anda terbuka di internet, aktifkan salah satunya (gratis).
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
| Tidak bisa login padahal benar | Kemungkinan terkunci throttle (tunggu 15 menit atau `php spark repository:unlock USERNAME`) atau cookie sesi tidak tersimpan (cek `cookie.secure` saat belum HTTPS) |
| Semua unggahan ditolak: "Pemindai antivirus (ClamAV) tidak tersedia" | `repository.scanMode = clamav` tetapi ClamAV tidak terjangkau. Pastikan `clamd` berjalan / `clamdHost` benar, atau ubah ke `auto` |
| Unggahan sah ditolak oleh pemindai heuristik | Baca alasan di pesan. Sesuaikan aturan di `Config\Repository` (`scanBlock*`) atau gunakan mode `builtin`/`auto` sesuai kebutuhan |
| Backup gagal: "ZipArchive" / ekstensi zip | Aktifkan ekstensi PHP `zip` (cPanel → Select PHP Version / PHP Extensions) |
| Pemulihan gagal "Ruang disk tidak cukup" | Hapus backup lama / tambah kuota; pemulihan butuh ± 2× ukuran data |
| Menu Backup tidak muncul setelah upgrade dari 1.1.0 | Tambahkan permission baru (lihat Upgrade di bawah) |
| Membuka kunci manual | phpMyAdmin → `DELETE FROM login_attempts;` |

### Upgrade dari 1.1.x ke 1.2.0
Timpa berkas (kecuali `.env`), lalu tambahkan permission baru: `php spark db:seed InitialSeeder`, atau lewat phpMyAdmin:
```sql
INSERT INTO permissions (name, label, group_name) VALUES
('backup.manage', 'Membuat, mengunduh, mengunggah & menghapus backup (berisi seluruh data & berkas — sangat sensitif)', 'Administrasi'),
('backup.restore', 'Memulihkan data dari backup (MENGGANTI seluruh database & berkas)', 'Administrasi');
```
Super Admin otomatis memiliki keduanya.

## Riwayat versi
* **1.2.0** — backup & pulihkan database + berkas (UI, cron `repository:backup`, `repository:restore`), backup pengaman + rollback otomatis, permission `backup.manage` & `backup.restore`.
* **1.1.0** — pemindai virus (ClamAV + heuristik), karantina & pindai ulang, `repository:scan`, `repository:unlock`, `InputGuard`, header keamanan di semua respons, perbaikan nama berkas `x.pdf.pdf` dan penolakan keliru nama dasar seperti `js.pdf`.
* **1.0.0** — rilis awal.

## Lisensi
Kode aplikasi bebas dipakai & dimodifikasi untuk institusi Anda. CodeIgniter 4 (MIT), Bootstrap (MIT), Bootstrap Icons (MIT),
SweetAlert2 (MIT), DataTables (MIT), jQuery (MIT), Nunito (SIL OFL 1.1).
