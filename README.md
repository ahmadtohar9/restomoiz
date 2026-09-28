# Resto Moiz

Sistem manajemen restoran terintegrasi (inventory, pembelian, menu, POS, laporan) dengan RBAC dinamis.
Dibangun dengan CodeIgniter 3, PHP 7.4+, MySQL/MariaDB, dan Bootstrap 5.

Aplikasi ini **berdiri sendiri** dan tidak berbagi kode, database, session, maupun upload dengan `moizhospitalapps`.

## Status

| Fase | Isi | Status |
|------|-----|--------|
| 1 | Fondasi & RBAC: login, user, role, permission matrix, custom permission, audit log, pengaturan | ✅ selesai |
| 2 | Inventory: bahan baku, kategori bertingkat, supplier, stok masuk/keluar FIFO, stock opname, peringatan stok, laporan nilai/aging/slow-moving/dead stock | ✅ selesai |
| 3 | Pembelian: PO dengan approval berjenjang, penerimaan barang (GR), invoice supplier dengan 3-way matching, pembayaran & verifikasi, laporan belanja/tren harga/kinerja supplier/aging hutang | ✅ selesai |
| 4 | Menu: kategori, varian, resep & COGS otomatis, harga normal/member/grosir dengan jadwal & riwayat, promo + simulator, barcode/label, menu online publik, analisis margin | ✅ selesai |
| 5 | POS (scan barcode, varian, tambahan/topping, promo & kode promo, PPN/service), order dine-in/takeaway/delivery, meja, layar dapur, struk & tiket dapur, pembayaran tunai/debit/kredit/e-wallet, shift kasir dengan approval selisih kas, pelanggan/member | ✅ selesai |
| 6 | Refund per item/penuh dengan matriks approval, bukti refund, reverse COGS opsional, settlement harian, rekonsiliasi bank bulanan | ✅ selesai |
| 7 | Dashboard eksekutif, laba rugi bulanan, laporan penjualan (menu, kategori, jam ramai, metode bayar, promo), kinerja kasir, analisis refund, pengeluaran operasional; ekspor CSV & cetak/PDF | ✅ selesai |
| 8–9 | Backup otomatis, Go-Live Checklist | ✅ tersedia; uji coba & pelatihan oleh tim resto |

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

## Pembelian

Alur: **PO** (draft → submit → approval) → **Penerimaan barang** (stok masuk FIFO) → **Invoice supplier** (3-way match) → **Pembayaran** (verifikasi).

- **Approval berjenjang** (batas di Pengaturan): di bawah `po_auto_limit` otomatis disetujui; sampai `po_owner_limit` butuh 1 approval (`purchase.approve`); di atasnya butuh approval kedua dari Owner (`purchase.approve_owner`). PO yang ditolak kembali ke draft dengan alasan.
- **Pemisahan tugas** (kecuali Admin): pembuat PO tidak bisa meng-approve PO-nya sendiri, approver level 2 harus orang lain dari level 1, dan pembayaran diverifikasi oleh orang lain dari yang menginput.
- **Penerimaan barang** bisa sebagian. Jumlah yang berbeda dari sisa PO wajib diberi alasan, dan penerimaan melebihi sisa PO ditolak. Stok masuk dengan harga PO setelah diskon; pajak tidak dimasukkan ke nilai persediaan.
- **3-way matching**: total invoice untuk satu PO dibandingkan dengan nilai barang yang diterima (termasuk diskon & pajak PO), dengan toleransi `invoice_match_tolerance`. Invoice yang tidak cocok hanya bisa disetujui dengan catatan alasan.
- **Pembayaran** bisa sebagian. Jumlah yang bisa diajukan sudah dikurangi pembayaran yang masih menunggu verifikasi, jadi tidak bisa bayar dobel. Untuk kartu kredit hanya disimpan 4 digit terakhir dan kode otorisasi.
- **Lampiran** (surat jalan, invoice, bukti bayar) disimpan di `storage/` di luar document root dan hanya bisa diunduh lewat `files/view` oleh user dengan `purchase.view`. Validasi file: gambar dengan `getimagesize()`, PDF dengan signature `%PDF-`.

## Menu

- **Varian**: setiap menu punya minimal satu varian (menu tanpa pilihan = "Reguler"). Harga, resep, dan barcode melekat pada varian.
- **COGS** per porsi = Σ (qty resep × harga beli terakhir bahan); kalau bahan belum pernah dibeli, dipakai rata-rata nilai stok. Riwayat COGS disimpan harian (`menu_cogs_history`) otomatis saat daftar menu dibuka dan setiap resep/harga berubah.
- **Harga** disimpan sebagai riwayat (`menu_variant_prices`): harga yang berlaku = `effective_from` terbaru yang sudah lewat, sehingga perubahan harga bisa dijadwalkan dan selalu punya alasan.
- **Ketersediaan**: porsi yang bisa dibuat = min(stok bahan ÷ qty resep). Jika `menu_auto_oos` aktif, menu otomatis tampil "habis" saat stok tidak cukup untuk 1 porsi.
- **Promo** dihitung oleh `Promo_engine` (dipakai simulator sekarang dan POS nanti). Promo "bisa digabung" dijumlahkan; promo lain berdiri sendiri; mesin memilih kombinasi yang paling menguntungkan pelanggan. Tes regresi: `php tests/promo_engine_test.php`.
- **Barcode**: kode internal EAN-13 berprefix 20 (rentang in-store GS1), digambar sebagai CODE128 SVG oleh `application/libraries/Barcode.php` (tabel pola sudah dicocokkan dengan JsBarcode). QR di label memakai `qrcode-generator` (MIT) yang disimpan di `public/assets/vendor`.
- **Menu online** publik di `/menu-online` (bisa dimatikan lewat `public_menu_enabled`). Kategori bertanda *internal* tidak ditampilkan.

## Penjualan & POS

- **Harga selalu dihitung server** (`Pos_service`): browser hanya mengirim varian, qty, tambahan, dan catatan. Harga member/grosir, promo (`Promo_engine`), service charge, dan PPN dihitung ulang di server. Urutan: subtotal − promo → service charge → PPN atas (dasar + service).
- **Alur pesanan**: *Kirim ke Dapur* menyimpan pesanan (status `open`, bisa dibayar nanti, mis. dine-in) atau *Bayar* langsung. Tambahan item ke pesanan terbuka tercetak sebagai tiket dapur "TAMBAHAN". Satu meja hanya punya satu pesanan terbuka.
- **Stok**: bahan resep + bahan tambahan keluar (FIFO, alasan `sales`) saat item dikirim ke dapur; COGS asli tercatat per item. Item yang dibatalkan sebelum dimasak mengembalikan stok ke batch asal dengan harga asli (`Stock_service::reverse()`); item yang sudah dimasak dicatat terbuang dan pembatalannya butuh `sales.edit_order`. Jika `pos_block_insufficient_stock` = 0, penjualan tetap jalan saat stok sistem kurang (ditandai, COGS kekurangan dihitung dengan harga beli terakhir).
- **Pembayaran** butuh shift kasir yang terbuka. Kartu: hanya jenis, 4 digit terakhir, dan kode approval EDC. Kuota promo dicek ulang dengan kunci baris saat bayar.
- **Shift**: kas seharusnya = modal + penjualan tunai − refund tunai. Selisih di atas `cash_variance_limit` wajib diberi catatan dan menunggu approval `sales.shift_approve` oleh orang lain.
- **Layar dapur** refresh otomatis (`kitchen_refresh_seconds`) dengan bunyi notifikasi: dapur menandai dimasak/siap, pelayan/kasir menandai diantar.

## Refund & Settlement

- **Refund** (`Refund_service`) diajukan dari detail transaksi lunas, per item & qty. Nilai = (jumlah baris − diskon) proporsional + bagian service & PPN; refund yang menghabiskan item mendapat sisa persis sehingga total refund tidak pernah melebihi yang dibayar.
- **Approval**: < `refund_auto_limit` dan ≤ `refund_auto_minutes` sejak bayar → kasir langsung; di atas itu → Manajer (`sales.refund_approve`); > `refund_owner_limit` → Owner (`sales.refund_owner`). Approver harus orang lain dari pengaju. Status: menunggu approval → disetujui → selesai (uang dikembalikan) / ditolak.
- **Uang kembali**: tunai dari laci shift kasir yang terbuka (mengurangi kas seharusnya), atau metode asal dengan nomor referensi reversal. **Stok**: opsional dikembalikan ke batch asal (reverse COGS, sesuai PRD) atau dicatat sebagai kerugian.
- **Settlement harian** (`payment.reconcile`): penjualan per kanal − refund dibandingkan setoran tunai, settlement EDC, e-wallet, dan biaya MDR. Verifikasi butuh semua shift ditutup dan catatan jika ada selisih; setelah diverifikasi data terkunci.
- **Rekonsiliasi bank bulanan**: seharusnya masuk (POS) vs mutasi rekening + dana dalam perjalanan + biaya + penyesuaian; rekening koran disimpan privat.

## Laporan

- **Definisi** (`Report_model`): pendapatan bersih = subtotal − diskon + service − refund (tanpa PPN, karena PPN titipan pajak); dicatat saat bayar, refund saat dana dikembalikan. COGS = COGS item terjual − bahan yang kembali ke stok dari refund pada periode yang sama.
- **Laba rugi** bulanan: pendapatan per kategori menu → COGS (menu terjual + bahan terbuang/kedaluwarsa/rusak/selisih opname) → laba kotor → beban operasional (menu *Keuangan → Pengeluaran*) → EBIT → bunga/beban lain → laba bersih, dibanding bulan sebelumnya.
- Grafik dibuat dengan SVG ringan (`public/assets/js/charts.js`): satu seri per grafik, palet biru yang sudah divalidasi kontras & buta warna, tooltip saat hover/fokus keyboard, dan tabel data untuk setiap grafik.
- Semua laporan bisa diekspor CSV; tombol cetak menghasilkan versi rapi untuk *Simpan sebagai PDF* di browser.

## Operasional

- **Backup**: `php public/index.php cli backup [hari]` membuat dump database terkompresi di `storage/backups/` (PHP murni, tanpa `exec`) dan menghapus backup yang lebih lama dari N hari (default 14). Jadwalkan harian lewat cron / aaPanel Cron, dan salin backup ke lokasi lain (off-site) secara berkala.
- **Go-Live Checklist** (*Administrasi → Go-Live Checklist*): pengecekan otomatis kesiapan (migration, SSL, backup, profil resto, data master, user per role, uji alur) + daftar manual perangkat & tim (PRD bagian 6).
- **Upgrade**: `git pull` lalu `php public/index.php cli setup` (migration + seed permission baru). Backup dulu sebelum upgrade.

## Tampilan (Tailwind CSS, offline)

Semua aset front-end disajikan lokal dari `public/assets/` — tidak ada CDN, aplikasi tetap tampil utuh tanpa internet (mis. jaringan kasir lokal).

- `resources/css/app.css` → **Tailwind CSS** (prefix `tw-`, preflight nonaktif) untuk layout: sidebar, topbar, login, dan sentuhan komponen.
- `resources/scss/bootstrap.scss` → tema **Bootstrap 5.3** (grid, form, modal, dropdown, offcanvas) dikompilasi dengan warna yang sama.
- `public/assets/vendor/` → Bootstrap JS, Bootstrap Icons, dan font Inter (lisensi disertakan).
- Hasil build **di-commit**, jadi server produksi tidak butuh Node.js. Setelah mengubah tampilan, jalankan di mesin dev: `npm install` lalu `npm run build` (atau `npm run watch:css` saat mengembangkan).
- Sidebar: grup bisa dibuka/tutup (diingat per browser), tombol ciutkan jadi ikon saja di desktop, drawer di HP, dan pencarian menu (tekan `/`).

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
| Email ke approver / reminder hutang otomatis | Daftar tugas di dashboard (approval, barang datang, invoice, verifikasi, jatuh tempo) | Sama: email belum tersedia |
| Jurnal akuntansi (Dr Inventory / Cr AP) | Belum ada modul GL; hutang dilacak lewat invoice & pembayaran | Modul keuangan di fase 7 |
| Verifikasi pembayaran oleh Accounting | Oleh user `purchase.payment` yang berbeda dari penginput | Kontrol internal: input dan verifikasi dipisah |
| Recurrence promo bulanan | Hari dalam minggu + jam + rentang tanggal | Mencakup harian/mingguan/happy hour; pola bulanan bisa ditambah bila dibutuhkan |
| Dynamic pricing (opsional di PRD) | Belum | Ditandai *future* di PRD |
| Kartu kredit: simpan nomor tersamar, expiry | Hanya jenis kartu, 4 digit terakhir, kode approval EDC | PCI-DSS: data kartu lain tidak perlu & berisiko |
| Email struk | Cetak struk thermal (58/80 mm) | Email belum dikonfigurasi di server |
| Bill PPN 10% (contoh struk PRD) | Tarif dari Pengaturan (default 11%) | Mengikuti regulasi |
