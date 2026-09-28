# Resto Moiz

Sistem manajemen restoran terintegrasi (inventory, pembelian, menu, POS, laporan) dengan RBAC dinamis.
Dibangun dengan CodeIgniter 3, PHP 7.4+, MySQL/MariaDB, dan Bootstrap 5.

Aplikasi ini **berdiri sendiri** dan tidak berbagi kode, database, session, maupun upload dengan `moizhospitalapps`.

## Status

| Fase | Isi | Status |
|------|-----|--------|
| 1 | Fondasi & RBAC: login, user, role, permission matrix, custom permission, audit log, pengaturan | ✅ selesai |
| 2 | Inventory: bahan baku, kategori bertingkat, supplier, stok masuk/keluar FIFO, stock opname, peringatan stok, laporan nilai/aging/slow-moving/dead stock | ✅ selesai |
| 3 | Pembelian (PO, penerimaan barang, invoice & pembayaran supplier) | berikutnya |
| 4–7 | Menu, POS, Refund & Settlement, Laporan keuangan | direncanakan |

## Struktur

```
public/          ← document root web server (index.php, assets, .htaccess)
application/     ← kode aplikasi (tidak bisa diakses dari web)
system/          ← CodeIgniter 3.1.13
.env             ← konfigurasi per-server (tidak masuk git)
```

## Instalasi

1. Buat database & user MySQL **khusus** untuk resto (jangan pakai user database aplikasi lain).
2. Salin konfigurasi lalu isi:
   ```bash
   cp .env.example .env
   php -r "echo bin2hex(random_bytes(32));"   # isi ke APP_KEY
   ```
3. Arahkan document root web server ke folder `public/`.
4. Buat tabel dan data awal, lalu buat akun admin pertama:
   ```bash
   php public/index.php cli setup
   php public/index.php cli create_admin <username>
   ```
   Tanpa argumen password, sistem membuat password acak yang **wajib diganti** saat login pertama.
5. Folder `application/sessions`, `application/logs`, `application/cache` harus bisa ditulis oleh user web server.

### Perintah CLI

| Perintah | Fungsi |
|----------|--------|
| `cli migrate` | Jalankan migration database ke versi terbaru |
| `cli seed` | Sinkron permission dari `application/config/rbac.php`, buat role default yang belum ada, isi pengaturan default. Aman dijalankan ulang; permission role yang sudah diatur admin tidak ditimpa. |
| `cli setup` | `migrate` + `seed` |
| `cli create_admin <username> [password]` | Buat user Admin, atau reset password & aktifkan kembali kalau username sudah ada |

## RBAC

- **Permission** didefinisikan di `application/config/rbac.php` (nama `modul.aksi`, mis. `sales.refund_approve`). Tambah permission baru di sana, lalu jalankan `cli seed`.
- **Role** dikelola di menu Administrasi → Role / Permission Matrix. Role *Admin* otomatis punya semua permission.
- **Multi-role**: satu user punya satu role utama + role tambahan opsional. Permission efektif = gabungan semua role.
- **Custom permission**: permission tambahan per user, opsional dengan tanggal kedaluwarsa (mis. kasir menggantikan manajer yang cuti).
- Perubahan role/permission **langsung berlaku** tanpa perlu logout, karena permission dihitung ulang setiap request.
- Sistem menolak menghapus, menonaktifkan, atau melepas role Admin dari **admin aktif terakhir**.

Di controller:

```php
class Menu extends MY_Controller {
    public function edit_price($id) {
        $this->require_permission('menu.edit_price');   // 403 + tercatat di audit log
        ...
    }
}
```

Di view:

```php
<?php if (can('menu.view_cogs')): ?>
    <td><?= rupiah($cogs) ?></td>
<?php endif; ?>
```

## Inventory

- **Semua perubahan stok lewat `Stock_service`** (`application/libraries/Stock_service.php`): `receive()`, `issue()`, `count()`. Jangan update kolom stok langsung, karena modul Pembelian (GR) dan POS nanti juga memakai library ini.
- **Valuasi FIFO**: setiap stok masuk menjadi batch (`ingredient_batches`), stok keluar mengambil dari batch tertua, dan pemakaian per batch dicatat di `stock_movement_batches`. `ingredients.qty_on_hand` dan `stock_value` dihitung ulang dari batch setiap kali ada pergerakan.
- **Dokumen multi-baris bersifat atomik**: kalau satu baris gagal (mis. stok kurang), tidak ada yang tersimpan.
- **Satuan alternatif**: satu bahan punya satu satuan standar (mis. kg) dan satuan lain dengan faktor konversi (gram = 0.001, karung = 25). Input boleh memakai satuan apa pun, penyimpanan selalu dalam satuan standar.
- **Stock opname**: lembar hitung bisa disimpan sebagai draft. Saat diposting, selisih terhadap stok sistem *pada saat posting* dibukukan sebagai penyesuaian.
- Harga beli & nilai stok hanya terlihat oleh role dengan permission `inventory.view_cost` (Staff Dapur tidak).
- Bahan/supplier/kategori yang sudah punya riwayat tidak bisa dihapus, hanya dinonaktifkan, supaya laporan tetap utuh.

## Keamanan

- Password di-hash dengan `password_hash()` (bcrypt), dengan aturan minimal 8 karakter berisi huruf dan angka.
- CSRF protection aktif untuk semua form POST.
- Login dikunci 15 menit setelah 5 kali gagal (per username + IP).
- Session ID diganti saat login dan saat ganti password. Cookie `HttpOnly`, dan `Secure` kalau `COOKIE_SECURE=true`.
- Semua login, akses yang ditolak, dan perubahan user/role/permission/pengaturan tercatat di audit log (bisa diekspor ke CSV).

## Penyesuaian terhadap PRD v1.0

Beberapa hal di PRD diubah saat implementasi:

| PRD | Implementasi | Alasan |
|-----|--------------|--------|
| `USERS.role_id` (satu role) | Tabel `user_roles` | PRD 3.1 meminta satu user bisa punya beberapa role |
| PPN 10% tetap | `tax_rate` di Pengaturan (default 11%) | Tarif PPN berubah mengikuti regulasi |
| Simpan nomor kartu, expiry, CVV | Hanya 4 digit terakhir + kode approval EDC (fase POS) | Menyimpan data kartu melanggar PCI-DSS |
| Contoh kode: non-admin auto-approve refund < 500K | Limit refund & batas menit diatur di Pengaturan dan dicek per permission (fase Refund) | Contoh di PRD membuat semua user non-admin bisa auto-approve |
| `assets/` & `migrations/` di dalam `application/` | `public/assets/`, `application/migrations/` | Struktur CodeIgniter 3: aset harus di folder publik |
| Role default tanpa Owner | Role *Owner* ditambahkan | Approval PO > 20 juta dan refund > 2 juta butuh Owner |
| Valuasi FIFO/LIFO/Average | FIFO | PRD 2.1.3 memilih FIFO untuk bahan mudah rusak |
| Notifikasi email stok minimum | Peringatan di dashboard & halaman Peringatan Stok | Email belum dikonfigurasi di server; bisa ditambahkan nanti |
