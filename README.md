# Talenta - Sistem Absensi & Jurnal Digital 🏫

Talenta adalah sebuah Sistem Informasi (Website) berbasis **PHP Native** dan **MySQL** yang dirancang khusus untuk mengelola absensi kehadiran, pengisian jurnal harian/tugas, ujian (ulangan) online, serta manajemen materi pembelajaran untuk organisasi pendidikan atau sekolah.

Sistem ini mendukung **4 level pengguna (Multi-Role)**:
1. **Admin**: Memiliki akses penuh terhadap semua manajemen data (User, Kelas, Token, Jurnal).
2. **Guru**: Dapat mengelola kelas yang diajarnya, memberikan Ulangan, membagikan Materi, serta menyetujui/meninjau Jurnal siswa.
3. **Instruktur**: Peran yang mirip dengan Guru, dirancang untuk pengajar praktikum atau ekstrakurikuler.
4. **Siswa**: Harus melakukan absensi harian menggunakan Token, mengirimkan Jurnal kegiatan harian (teks, foto, dokumen), melihat jadwal, dan mengerjakan Ulangan.

---

## ✨ Fitur Utama

- **Sistem Absensi Berbasis Token (Secure)**
  Kehadiran siswa dikunci menggunakan kode Token 6 karakter yang di-generate dinamis oleh Guru/Admin per kelas dan per hari.
- **Auto-Lock Logout (Siswa)**
  Siswa yang telah absen *diwajibkan* untuk mengirimkan Jurnal Kegiatan Harian sebelum sistem mengizinkan mereka untuk Keluar (Logout).
- **Manajemen Ulangan Online (CBT)**
  Pembuatan soal pilihan ganda dinamis dengan timer hitung mundur. Hasil ulangan langsung direkap otomatis dilengkapi UI Analisis Jawaban (Visualisasi baris-per-baris jawaban benar/salah).
- **Statistik & Analitik Visual**
  Mahasiswa dapat melihat *Chart.js* grafik perkembangan nilai Ulangan dan persentase kehadiran bulanan mereka langsung dari *Dashboard*.
- **Report & Export Lengkap**
  Admin/Guru dapat mengunduh (*Export to CSV/Excel*) rekapan Absensi, daftar nilai Ulangan, serta rekapan Jurnal dalam 1 kali klik.
- **Notifikasi In-App (Real-time Feel)**
  Setiap *action* penting (Siswa kirim Jurnal, Admin buat pengumuman baru) akan memicu lonceng notifikasi *In-App* secara otomatis.
- **E-Learning & Broadcast**
  Tersedia fitur berbagi Materi Kuliah (PDF/Video) serta fitur Pengumuman global yang akan ter-broadcast ke semua layar pengguna.
- **Responsive & Dark/Light Mode**
  UI modern yang terinspirasi dari Glassmorphism, 100% responsif di HP/Tablet, lengkap dengan *toggle* pergantian tema Gelap/Terang.

---

## 🛠️ Teknologi yang Digunakan

- **Backend:** PHP 8.x (Native / PDO MySQL)
- **Database:** MariaDB / MySQL
- **Frontend:** HTML5, CSS3 (Vanilla / Custom Variables), JavaScript (ES6)
- **Library External:**
  - [Chart.js](https://www.chartjs.org/) (Visualisasi Data)
  - [FontAwesome](https://fontawesome.com/) (Ikon)
  - [Google Fonts](https://fonts.google.com/) (Inter & Outfit typography)

---

## 🚀 Panduan Instalasi (Localhost)

Ikuti langkah-langkah di bawah ini untuk menjalankan project Talenta di komputer lokal Anda:

### Prerequisites:
- Server Lokal seperti **XAMPP**, **MAMP**, atau **Laragon** (PHP 8.0+ disarankan).
- Git (opsional, untuk kloning repository).

### Langkah-langkah:
1. **Clone Repository (atau Download ZIP)**  
   Clone repo ini ke dalam folder `htdocs` (jika pakai XAMPP).
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/UsernameAnda/absensi-talenta.git ABSENSITALENTA
   ```
2. **Setup Database**
   - Buka **phpMyAdmin** (`http://localhost/phpmyadmin`).
   - Buat database baru dengan nama: `talenta_db`.
   - Lakukan *Import* file `database.sql` yang ada di root direktori project ini.
3. **Konfigurasi Database**
   - Buka folder `config` dan edit file `database.php`.
   - Pastikan informasi koneksi sudah sesuai dengan server lokal Anda:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root'); // Username database Anda
     define('DB_PASS', '');     // Password database Anda (kosongkan jika default XAMPP)
     define('DB_NAME', 'talenta_db');
     ```
4. **Jalankan Aplikasi**
   - Buka browser Anda dan akses: `http://localhost/ABSENSITALENTA` (Sesuaikan dengan nama folder).

---

## 🔐 Akun Akses Default

Berikut adalah kredensial yang dapat Anda gunakan untuk mencoba (testing) login setelah database berhasil di-import:

| Hak Akses | Username | Password |
| :--- | :--- | :--- |
| **Admin Pusat** | `admin` | `password` |
| **Guru Kelas** | `guru1` | `password` |
| **Instruktur** | `instruktur1` | `password` |
| **Siswa 1** | `siswa1` | `password` |
| **Siswa 2** | `siswa2` | `password` |

*(Sangat disarankan untuk segera login dan **mengganti password** Anda lewat menu Profil!)*

---

## 📸 Screenshots

| Dashboard Admin | Dashboard Siswa (Auto-Lock Token) |
| :---: | :---: |
| ![Admin Dashboard](https://via.placeholder.com/600x350.png?text=Admin+Dashboard) | ![Siswa Dashboard](https://via.placeholder.com/600x350.png?text=Siswa+Token+Lock) |

---

## 🤝 Kontribusi

Proyek ini dibuat untuk keperluan edukasi dan penugasan organisasi. Namun jika Anda menemukan *bugs* atau memiliki ide peningkatan fitur, jangan ragu untuk membuka *Issues* atau *Pull Request*.

## 📄 Lisensi

Proyek ini berada di bawah lisensi terbuka untuk tujuan pendidikan (*Educational Purpose*).

---
*Dibuat dengan ❤️ untuk kemajuan pendidikan digital.*
