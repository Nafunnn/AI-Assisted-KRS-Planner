# AI-Assisted KRS Planner

Aplikasi web untuk menyusun **Kartu Rencana Studi (KRS)** dari file Excel penawaran mata kuliah. Mahasiswa bisa memilih kelompok, melihat jadwal mingguan, mendeteksi bentrok, lalu mengekspor hasilnya. Asisten AI membantu menyarankan jadwal, memperbaiki konflik, dan menyesuaikan beban SKS.

## Fitur

- **Katalog bersama** diunggah admin (Excel penawaran); mahasiswa menyusun rencana di atas katalog yang sama
- **Sync katalog aman**: update Excel mempertahankan identitas kelompok; rencana terdampak ditandai stale
- **Planner visual** dengan kalender mingguan, daftar mata kuliah, dan ringkasan SKS
- **Deteksi bentrok** otomatis antar kelompok yang dipilih
- **Beberapa rencana** per katalog (draft / final)
- **Teman**: permintaan teman, bagikan rencana view-only, salin ke rencana sendiri
- **Asisten AI** untuk cek konflik, usulkan kelompok, dan generate jadwal (min/max SKS, hari libur, batas jam)
- **Ekspor** rencana ke PDF atau PNG
- **Akun** dengan registrasi, verifikasi email, reset password, 2FA, dan passkey
- **Provider AI per pengguna** (Anthropic, Gemini, Ollama, OpenRouter, 9Router lokal, atau gateway kustom)



## Stack


| Lapisan     | Teknologi                                    |
| ----------- | -------------------------------------------- |
| Backend     | PHP 8.3, Laravel 13                          |
| Frontend    | Vue 3, Inertia.js 3, Tailwind CSS 4, Reka UI |
| Auth        | Laravel Fortify                              |
| AI          | Laravel AI SDK (`laravel/ai`)                |
| Routing FE  | Laravel Wayfinder                            |
| Excel / PDF | PhpSpreadsheet, DomPDF                       |
| Tes         | Pest 4, Larastan, Pint                       |




## Kebutuhan

- PHP **8.3+** dengan ekstensi `pdo_sqlite` (atau driver database lain)
- Composer 2
- Node.js **20.19+** (disarankan 22 LTS) dan npm
- Database: **SQLite** secara default; MySQL/PostgreSQL juga didukung



## Instalasi

```bash
git clone https://github.com/Nafunnn/AI-Assisted-KRS-Planner "AI-Assisted KRS Planner"
cd "AI-Assisted KRS Planner"
composer setup
```

`composer setup` akan menginstal dependensi PHP dan Node, menyalin `.env`, generate `APP_KEY`, menjalankan migrasi, lalu build frontend.

### Setup manual

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Default database di `.env.example` adalah SQLite (`DB_CONNECTION=sqlite`). File database dibuat otomatis saat migrasi pertama.

Akun uji (hanya jika Anda menjalankan seeder):

```bash
php artisan db:seed
```


| Email              | Password   | Peran      |
| ------------------ | ---------- | ---------- |
| `admin@example.com` | `password` | Admin katalog |
| `test@example.com` | `password` | Mahasiswa |




## Menjalankan aplikasi

```bash
composer run dev
```

Perintah itu menjalankan server Laravel, queue listener, dan Vite secara bersamaan. Buka [http://localhost:8000](http://localhost:8000).

Atau jalankan terpisah:

```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

Jika perubahan frontend tidak muncul, jalankan `npm run dev` atau `npm run build`.

## Format Excel penawaran

Header baris pertama **harus persis** seperti ini (urutan dan ejaan termasuk titik pada `Kelp.`):


| Kode MK | Nama Mata Kuliah | SKS | T/P | Kelp. | Jadwal 1 | Jadwal 2 | Jadwal 3 | Jam |
| ------- | ---------------- | --- | --- | ----- | -------- | -------- | -------- | --- |


Aturan kolom:

- **T/P**: `T` (Teori) atau `P` (Praktikum)
- **Jam**: `P` (Pagi), `M` (Malam), atau `PM`
- **Jadwal**: `HARI, HH:MM:SS - HH:MM:SS` dengan nama hari Indonesia huruf besar, misalnya `SENIN, 07:00:00 - 09:30:00`
- Slot jadwal kosong atau berisi `-, -` dilewati
- Maksimal ukuran file 10 MB

Contoh baris:

```text
IF101 | Algoritma dan Pemrograman | 3 | T | A | SENIN, 07:00:00 - 09:30:00 |  |  | P
```

Header yang tidak cocok akan ditolak. Baris rusak dilewati; impor tetap berhasil selama ada minimal satu kelompok valid.

## Asisten AI

Asisten hanya aktif setelah pengguna menyimpan dan mengaktifkan provider di **Settings → AI Providers**. Tanpa konfigurasi aktif, chat akan menolak permintaan.

Provider yang didukung:


| Provider       | Keterangan                                            |
| -------------- | ----------------------------------------------------- |
| Anthropic      | Claude via API key                                    |
| Google Gemini  | Gemini via API key                                    |
| Ollama         | Model lokal (`http://localhost:11434` secara default) |
| OpenRouter     | Gateway multi-model                                   |
| 9Router        | Gateway OpenAI-compatible lokal                       |
| Custom Gateway | Endpoint OpenAI-compatible lain                       |


Setelah provider aktif, buka rencana KRS lalu pakai panel asisten. Contoh permintaan:

- “Cek apakah ada bentrok di rencana ini”
- “Buatkan jadwal 18–22 SKS, bebas Jumat, selesai sebelum 17:00”
- “Ganti kelompok matkul X yang tidak bentrok”

Logika jadwal tetap di server (deteksi konflik, generate, sinkronisasi kelompok). Model AI hanya memanggil tool; perubahan rencana divalidasi sebelum disimpan.

## Alur pemakaian

1. Admin masuk, buka **Kelola Katalog**, import Excel penawaran (langsung published)
2. Mahasiswa daftar / masuk, lalu buka **KRS Planner** — katalog published muncul untuk semua
3. Mulai rencana, pilih kelompok di daftar mata kuliah; kalender dan ringkasan SKS ikut berubah
4. Buat rencana cadangan jika ingin membandingkan opsi
5. Opsional: tambah **Teman**, bagikan rencana, atau salin rencana teman
6. Pakai asisten AI jika perlu saran atau generate otomatis
7. Ekspor PDF atau PNG jika jadwal sudah aman (tanpa bentrok)

Katalog milik admin (bersama). Rencana KRS milik masing-masing mahasiswa. Admin boleh sync Excel ulang; kelompok yang match `(kode MK, T/P, kelompok)` mempertahankan ID; item rencana yang jadwalnya berubah atau kelompoknya hilang ditandai stale.

## Perintah berguna

```bash
php artisan test --compact          # tes
composer run lint                   # format PHP (Pint)
composer run types:check            # PHPStan / Larastan level 7
npm run lint                        # ESLint
npm run format                      # Prettier
npm run types:check                 # Vue / TypeScript
composer run ci:check               # lint + typecheck + tes
```



## Struktur singkat

```text
app/AI/                 Agen, tool, registry entitas, dan chat asisten
app/Http/Controllers/   Rute web: KRS, ekspor, chat, settings
app/Services/Krs/       Import Excel, konflik, generate jadwal, ekspor
app/Models/             CourseOffering, Course, CourseSection, KrsPlan, …
resources/js/pages/     Halaman Inertia (krs, settings, auth)
resources/js/components/krs/  Kalender, daftar MK, panel AI
tests/Feature/          Tes auth, impor, planner, AI, ekspor
```

Rute utama (setelah login dan verifikasi email):


| Path                             | Fungsi                       |
| -------------------------------- | ---------------------------- |
| `/krs`                           | Daftar katalog dan rencana   |
| `/krs/admin/offerings`           | Kelola katalog (admin)       |
| `/krs/planner/{offering}/{plan}` | Planner jadwal               |
| `/krs/plans/{plan}/export/pdf`   | Unduh PDF                    |
| `/friends`                       | Teman dan rencana dibagikan  |
| `/settings/ai-providers`         | Konfigurasi provider AI      |




## Lisensi

MIT. Template awal memakai Laravel Vue starter kit; logika KRS dan asisten AI adalah bagian aplikasi ini.