<div align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/assets/SirkulaFood-logo-dark.png">
    <img src="docs/assets/SirkulaFood-logo-light.png" alt="Logo SirkulaFood transparan" width="356">
  </picture>
  <h1>Selamatkan pangan. Jaga nilainya.</h1>
    <p>
    <a href="https://sirkulafood.my.id/">Kunjungi Website</a> ·
    <a href="https://t.me/SirkulaFood_bot">Buka Telegram</a> ·
    <a href="https://github.com/SirkulaFood/SirkulaFood">Source Code</a>
  </p>
  <p><strong>Manajemen inventaris cerdas untuk mengurangi limbah pangan.</strong></p>
  <p>SirkulaFood membantu tim ritel memantau produk mendekati kedaluwarsa, mengelola diskon, dan menyalurkan surplus pangan layak kepada mitra donasi.</p>
</div>

![Tampilan landing page SirkulaFood](docs/assets/SirkulaFood-landing-page.png)
<br><sub>Tampilan Landing page SirkulaFood</sub> 

## Tentang SirkulaFood

SirkulaFood adalah platform berbasis Laravel dan Vue untuk mengelola produk, batch inventaris, serta tanggal kedaluwarsa. Tim dapat meninjau produk berisiko, mempertimbangkan rekomendasi diskon, dan mengoordinasikan surplus pangan dengan mitra donasi. Setiap rekomendasi dan tindak lanjut dicatat agar dapat ditinjau kembali.

## Kontribusi terhadap SDGs

Melalui pemantauan inventaris, strategi diskon, pemeriksaan kondisi pangan, dan penyaluran donasi, SirkulaFood mendukung upaya menuju konsumsi dan produksi yang lebih bertanggung jawab, pengurangan kelaparan, inovasi operasional, dan kemitraan untuk mencapai tujuan bersama.

<p align="center">
  <img src="docs/assets/SirkulaFood-poster.png" alt="Poster kontribusi SirkulaFood terhadap SDGs" width="595">
  <br><sub>EcoLoop poster</sub>
</p>

Kontribusi tersebut menggambarkan dukungan melalui alur kerja dan kemitraan; bukan klaim pencapaian target SDGs secara keseluruhan. Pengukuran dampak membutuhkan data operasional dan tolok ukur yang jelas.

## Jenis akun

| Jenis akun | Kegunaan | Pendaftaran |
|:--|:--|:--|
| **Mitra sosial / yayasan** | Menerima produk yang didonasikan. | Tersedia pada halaman pendaftaran. |
| **Mitra UMKM** | Menerima informasi produk diskon. | Tersedia pada halaman pendaftaran. |
| **Supermarket / toko** | Mengelola inventaris serta menawarkan produk untuk diskon atau donasi. | Tersedia pada halaman pendaftaran. |
| **Admin sistem** | Mengelola platform dan akun mitra. | Dibuat oleh administrator. |

Pemilik toko dapat memberikan akses operasional kepada staf dengan peran pengelola atau kasir.

## Alur pengelolaan pangan

| Tahap | Dukungan SirkulaFood |
|:--|:--|
| **Pantau** | Tinjau produk, batch, jumlah stok, harga, dan sisa masa simpan. |
| **Periksa** | Gunakan analisis gambar sebagai bantuan awal untuk menilai kondisi produk. |
| **Pertahankan nilai** | Tinjau rekomendasi dan persetujuan diskon sebelum perubahan diterapkan. |
| **Salurkan** | Siapkan produk yang memenuhi syarat untuk mitra donasi dan koordinasikan serah terima. |
| **Catat** | Simpan aktivitas, keputusan, serta rekap untuk ditinjau. |

## Teknologi dan integrasi

| Teknologi | Peran |
|:--|:--|
| **Laravel 12** | Backend, inventaris, aturan bisnis, persetujuan, audit, dan API. |
| **Vue 3 + Inertia** | Dasbor, portal mitra, simulasi, dan alur kerja pengguna. |
| **9Router** | Gateway kompatibel OpenAI untuk model bahasa dan visi yang dikonfigurasi. |
| **Telegram** | Percakapan berbasis webhook dan tindak lanjut inventaris. Persetujuan tetap dilakukan oleh staf berwenang. |
| **n8n** | Berkas alur kerja tersedia sebagai draf nonaktif. Atur endpoint dan kredensial sebelum diimpor atau diaktifkan. |
| **MySQL / SQLite** | Penyimpanan aplikasi sesuai konfigurasi lokal. |

### Alur keputusan AI

```text
Data inventaris / foto produk
              ↓
Laravel memvalidasi konteks dan permintaan
              ↓
9Router → model AI yang dikonfigurasi → hasil terstruktur
              ↓
Staf meninjau / menyetujui melalui aplikasi atau Telegram
              ↓
Diskon atau donasi yang disetujui → dicatat oleh Laravel
```

Analisis AI membantu memberikan konteks, tetapi foto saja tidak dapat memastikan kesegaran, kontaminasi, bau, atau keamanan pangan. Staf tetap berwenang menentukan kelayakan dan keputusan operasional.

## Menjalankan secara lokal


npm install
```

Tunggu setiap perintah selesai. Jika instalasi gagal karena versi runtime, ekstensi, atau dependency, penuhi persyaratan yang disebutkan lalu ulangi perintah yang gagal.

### 3. Buat konfigurasi lokal

Salin konfigurasi contoh dan hasilkan kunci aplikasi:

```powershell
Copy-Item .env.example .env

Buka `.env` di editor dan pilih salah satu konfigurasi database berikut.

#### SQLite (disarankan untuk mulai)

Pastikan ekstensi `pdo_sqlite` aktif, kemudian buat file databasenya:

```powershell
New-Item -ItemType File -Path database/database.sqlite -Force
```

Atur nilai berikut di `.env`:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Kosongkan `DB_HOST`, `DB_PORT`, `DB_USERNAME`, dan `DB_PASSWORD` jika nilainya terisi. Konfigurasi contoh menggunakan database juga untuk session, cache, dan antrean.

#### MySQL

Jalankan MySQL dan buat database kosong bernama `SirkulaFood_db` (atau nama pilihan Anda). Isi `.env` dengan nilai lokal yang benar:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=SirkulaFood_db
DB_USERNAME=root
DB_PASSWORD=
```

Sesuaikan pengguna dan kata sandi dengan instalasi MySQL Anda.

Untuk menjalankan fitur dasar secara lokal, pastikan pengaturan berikut sesuai:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
ENABLE_MOCK_AI=true
```

Mode mock AI memungkinkan aplikasi berjalan tanpa API AI eksternal.

### 4. Siapkan database dan frontend


Migrasi membuat tabel aplikasi dan tabel pendukung yang digunakan konfigurasi database untuk session, cache, dan queue.

### 5. Jalankan aplikasi

Cara termudah adalah menjalankan server Laravel, worker antrean, dan Vite sekaligus:

```powershell
composer run dev
```

Biarkan proses itu tetap berjalan dan buka **http://127.0.0.1:8000** di browser. Alternatifnya, jalankan proses berikut dari tiga terminal terpisah di folder proyek:

php artisan serve
```

```powershell
php artisan queue:listen --tries=1 --timeout=0
```

```powershell
npm run dev

Untuk menghentikan proses tekan `Ctrl+C` di terminal terkait. Jika ingin mencoba tanpa Vite development server, jalankan `npm run build`, lalu `php artisan serve`.

### 6. Integrasi opsional

Aplikasi dasar dapat dijalankan tanpa mengisi kunci integrasi. Atur hanya layanan yang ingin digunakan:

- **9Router/AI:** Isi `NINEROUTER_API_KEY`, `NINEROUTER_BASE_URL`, `NINEROUTER_MODEL`, dan `NINEROUTER_VISION_MODEL`. URL contoh di `.env.example` adalah `http://127.0.0.1:20128/v1`; pastikan gateway berjalan dan dapat dijangkau Laravel. Variabel `GEMINI_API_KEY` dan `GROQ_API_KEY` tersedia untuk konfigurasi terkait. Simpan kunci secara privat.
- **Telegram:** Isi `TELEGRAM_BOT_TOKEN` dan `TELEGRAM_WEBHOOK_SECRET`. Webhook memerlukan URL HTTPS publik yang dapat dijangkau Telegram; localhost saja tidak cukup. Daftarkan URL dengan `php artisan telegram:set-webhook --url=https://domain-anda.example` setelah endpoint publik siap.
- **n8n:** Atur `N8N_WEBHOOK_URL`, `N8N_EVENT_WEBHOOK_URL`, dan kredensial webhook jika diperlukan. File workflow di repository adalah draf; impor ke n8n, atur endpoint/kredensial, lalu aktifkan secara manual.
- **Penjadwal Laravel:** Tugas di `routes/console.php` memerlukan proses terpisah. Untuk menjalankannya selama pengembangan lokal, buka terminal lain dan jalankan `php artisan schedule:work`.

### Pemecahan masalah

- **`could not find driver`:** Aktifkan `pdo_sqlite` atau `pdo_mysql` sesuai database, kemudian buka terminal baru.
- **Database tidak tersambung:** Pastikan layanan MySQL aktif dan nilai koneksi benar. Untuk SQLite, pastikan file `database/database.sqlite` ada dan `DB_CONNECTION=sqlite`.
- **Perubahan `.env` tidak terbaca:** Jalankan `php artisan config:clear`, lalu mulai ulang server.
- **Tampilan atau perubahan frontend tidak muncul:** Pastikan `npm run dev` tetap berjalan, atau jalankan ulang `npm run build`.
- **Port 8000 sudah digunakan:** Jalankan `php artisan serve --port=8001` dan ubah `APP_URL` menjadi `http://127.0.0.1:8001`.


## Struktur proyek

```text
app/                    Backend Laravel, controller, model, layanan, dan job
resources/js/            Halaman Vue dan komponen antarmuka
resources/css/           Gaya aplikasi
routes/                  Rute web, API, dan tugas terjadwal
SirkulaFood-agent-*.json Ekspor alur kerja n8n
```

---

<div align="center">
  <sub>Kelola stok, tentukan langkah berikutnya, dan jaga keputusan tetap berada di tangan manusia.</sub>
</div>
