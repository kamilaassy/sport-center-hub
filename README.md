# Sport Center Hub — Sistem Pemesanan Lapangan

Sistem manajemen pemesanan lapangan olahraga berbasis PHP + MySQL.

---

## Lailatul Kamila As s - 31124006
---

## 🏟 Fitur Utama

| Fitur | Deskripsi |
|-------|-----------|
| **Manajemen Lapangan** | CRUD data lapangan (Padel, Badminton, Tennis) beserta harga sewa |
| **Sistem Reservasi** | Form pemesanan dengan cek ketersediaan real-time via AJAX |
| **Anti Double Booking** | Validasi server-side & client-side untuk mencegah jadwal bentrok |
| **Jadwal Harian** | Grid kalender jam per lapangan — slot merah = terisi, hijau = kosong |
| **Dashboard** | Statistik ringkas: total lapangan, booking hari ini, pendapatan bulan ini |
| **Navigasi Tanggal** | Tombol prev/next dan klik slot kosong langsung buka form reservasi |

---

## 🛠 Teknologi
- **Backend**: PHP 8.x (PDO, prepared statements)
- **Database**: MySQL / MariaDB
- **Frontend**: HTML5, CSS3 (custom), Vanilla JS (fetch API)
- **Font**: Barlow Condensed + DM Sans (Google Fonts)

---

## ⚙️ Cara Instalasi

### 1. Import Database
```sql
-- Di phpMyAdmin atau MySQL CLI:
SOURCE database.sql;
```

### 2. Konfigurasi Koneksi
Edit file `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sport_center_hub');
define('DB_USER', 'root');      // Sesuaikan
define('DB_PASS', '');          // Sesuaikan
```

### 3. Jalankan Server
**Opsi A — XAMPP/WAMP/Laragon:**
- Letakkan folder `sport-center-hub/` di `htdocs/`
- Akses: `http://localhost/sport-center-hub/`

**Opsi B — PHP Built-in Server:**
```bash
cd sport-center-hub
php -S localhost:8080
# Akses: http://localhost:8080
```

---

## 📁 Struktur File
```
sport-center-hub/
├── config/
│   └── database.php       # Koneksi PDO ke MySQL
├── assets/
│   ├── style.css          # Stylesheet utama
│   └── app.js             # Logika client-side (AJAX availability check)
├── api/
│   └── check_availability.php   # Endpoint cek jadwal bentrok
├── includes/
│   ├── header.php         # Navbar + head HTML
│   └── footer.php         # Footer + script tag
├── index.php              # Dashboard
├── schedule.php           # Jadwal harian (grid)
├── reservations.php       # CRUD reservasi
├── courts.php             # CRUD lapangan
├── database.sql           # Schema + sample data
└── README.md
```

---

## 🔒 Keamanan yang Diterapkan
- **PDO Prepared Statements** — mencegah SQL Injection
- **`htmlspecialchars()`** — mencegah XSS pada output
- **Double booking check** di server side (tidak hanya client)
- Validasi tipe data dan format input

---

## 📸 Halaman Utama
| URL | Deskripsi |
|-----|-----------|
| `/index.php` | Dashboard statistik & booking hari ini |
| `/schedule.php` | Jadwal harian dalam bentuk grid |
| `/reservations.php` | Daftar & form reservasi |
| `/courts.php` | Daftar & form manajemen lapangan |
