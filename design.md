# Dokumen Spesifikasi Desain UI/UX: ICU Monitor v2.0

**Versi Desain:** 3.1
**Fokus:** UI/UX Medis, Modern, Interaktif, Alur Kerja (Workflow) Optimal.
**Konsep Utama:** *Soft Modernism*, *Glassmorphism Minimalis*, Elevasi Data Terstruktur, *Whitespace* Strategis.

---

## 1. Filosofi Desain

Aplikasi medis menuntut kejelasan tinggi, namun tidak harus mengorbankan estetika. ICU Monitor v2.0 dirancang untuk mengurangi kelelahan mata (*eye strain*) pengguna yang bekerja berjam-jam, dengan membedakan informasi kritis melalui hierarki visual, warna, dan animasi yang halus.

*   **Soft Modernism:** Meninggalkan desain *flat* kaku beralih ke elemen berdimensi dengan bayangan halus (*soft shadows*) dan sudut membulat (*rounded corners*).
*   **Glassmorphism:** Penggunaan efek kaca buram (*blur/backdrop-filter*) pada elemen *overlay* (seperti Topbar, Sidebar, Modal) untuk memberikan kesan kedalaman layar.
*   **Micro-interactions:** Umpan balik seketika saat elemen di-*hover* atau diklik (misal: baris tabel sedikit terangkat) agar aplikasi terasa "hidup" dan responsif.

---

## 2. Palet Warna & Tema

Sistem mendukung Tema Terang (Bright/Light Mode) dan Tema Gelap (Dark Mode) yang dapat diubah *real-time* dengan transisi *fading*.

### A. Tema Terang (Light Mode)
| Komponen UI | Warna (HEX) | Deskripsi |
| :--- | :--- | :--- |
| **Main Background** | `#F0F4F8` | Abu-abu sangat muda kebiruan (mengurangi mata lelah). |
| **Card / Surface** | `#FFFFFF` | Putih bersih pekat, dengan elevasi shadow halus (Opacity 90%). |
| **Topbar / Sidebar**| `#FFFFFF` | Latar belakang transparan dengan efek Blur 15px (Alpha 70%). |
| **Primary Text** | `#1A2B3C` | Biru tua arang untuk kontras baca maksimal. |
| **Secondary Text** | `#5A6B7C` | Abu-abu tua kebiruan untuk teks pendukung. |
| **Primary Accent** | `#00A884` | Hijau Emerald Medis (*Vibrant*). |
| **Accent Hover** | `#008C6E` | Hijau Emerald lebih gelap. |

### B. Tema Gelap (Dark Mode)
| Komponen UI | Warna (HEX) | Deskripsi |
| :--- | :--- | :--- |
| **Main Background** | `#0A121A` | Biru hitam sangat tua. |
| **Card / Surface** | `#141E2A` | Biru tua arang, dengan elevasi shadow halus (Opacity 90%). |
| **Topbar / Sidebar**| `#141E2A` | Latar belakang transparan dengan efek Blur 15px (Alpha 70%). |
| **Primary Text** | `#E0E6ED` | Putih tulang (mencegah silau di ruang gelap). |
| **Secondary Text** | `#90A0B0` | Abu-abu muda kebiruan. |
| **Primary Accent** | `#00CFA3` | Hijau Emerald Medis (*Luminous*). |

### C. Sistem Warna Status (Pill Badges)
Warna cerah dengan latar belakang yang sangat pudar (*soft-tint*) untuk visibilitas tanpa terlalu mencolok.

| Status / Kategori | Warna Teks / Dot | Latar Belakang (Soft Tint) |
| :--- | :--- | :--- |
| **Menunggu / Pending**| `#E67E22` (Orange) | `#FDF3E9` |
| **Selesai / Terverifikasi**| `#27AE60` (Hijau) | `#EBF9F1` |
| **Internal / Antrian** | `#3498DB` (Biru) | `#EAF4FB` |
| **Eksternal** | `#8E44AD` (Ungu) | `#F4ECF7` |
| **Ditolak / Alert** | `#E74C3C` (Merah) | `#FDEDEC` |

---

## 3. Tipografi

Sangat disarankan menggunakan *font* geometris sans-serif yang bersih.
*   **Primary Font:** `Inter` atau `Plus Jakarta Sans`
*   **Monospace Font:** `DM Mono` atau `Fira Code` (untuk Jam, NIK, MR, dan Data Numerik Klinis).

| Penggunaan | Ukuran Dasar | Weight | Line Height | Contoh Penggunaan |
| :--- | :--- | :--- | :--- | :--- |
| **Hero Title** | 36px | Bold (700) | 1.1 | Ruang ICU & HCU |
| **Main Title** | 22px | Bold (700) | 1.2 | Dashboard ICU (Topbar) |
| **KPI Number** | 48px | ExtraBold (800) | 1.0 | 12 (Angka Total) |
| **Table Header** | 12px | SemiBold (600) | 1.5 | DIAGNOSA (Uppercase) |
| **Body Text** | 14px | Regular (400) | 1.5 | Nama Pasien |
| **Data Vital (Mono)** | 11px/12px | Medium (500) | 1.4 | HR: 88 bpm |

---

## 4. Komponen Antarmuka Global

1.  **Sidebar (Navigasi):**
    *   Lebar tetap (misal: 240px). Menggunakan efek *Glassmorphism* (transparan dengan latar blur).
    *   Icon menu menggunakan gaya *outline*. Menu aktif berubah menjadi *solid* dengan pendaran (*glow*) warna Emerald dan tulisan sedikit ditebalkan.
2.  **Topbar (Header Sticky):**
    *   Melayang di atas konten (*sticky*) dengan efek kaca buram.
    *   Berisi Judul Halaman, Jam Digital (Monospace, Real-time), Theme Toggle (ikon Matahari/Bulan dalam satu *pill* dinamis), dan Avatar User.
3.  **Kartu Statistik (KPI Cards):**
    *   Menggunakan *soft shadow*. Pada *hover*, kartu terangkat naik (`translate-y: -4px`) dan bayangan membesar.
    *   Dilengkapi *Micro-sparkline* (grafik mini) di bawah angka jika mewakili data yang memiliki tren.
4.  **Tombol (Buttons):**
    *   Tombol aksi utama (Primer) menggunakan warna solid aksen dengan efek klik *bounce/scale* mengecil ke dalam (`scale: 0.96`).
    *   Tombol sekunder/batal menggunakan desain *outline* atau *ghost button*.

---

## 5. Spesifikasi Tata Letak Halaman (Layout & Workspace)

Setiap menu memiliki alur kerja (*workflow*) yang dibedakan tata letaknya sesuai fungsi departemen.

### A. Dashboard Utama (Overview)
*   **Hero Section:** Banner lebar (*full-width*) melengkung dengan gradasi Emerald Teal. Berisi logo RS, Unit Kerja, dan teks "LIVE" berkedip dengan *ticker* informasi pasien berjalan.
*   **Tabel Elevated:** Tabel pasien bukan grid kaku, melainkan *list* di mana setiap baris (`<tr>`) adalah entitas kartu tersendiri.
*   **Interaksi Hover Baris:** Saat disorot, baris terangkat (Lift-up), *background* berubah cerah, dan memunculkan tombol aksi mini (opsional).
*   **Animasi Tampil:** Data di-load menggunakan *Staggered Fade Slide Up* (muncul satu persatu dari bawah ke atas).

### B. Menu Admisi (Penerimaan & Booking)
*   **Layout:** *Split-screen* (Membagi layar menjadi 2 kolom utama).
    *   **Kolom Kiri (40%):** Daftar antrian rujukan pasien masuk (*Vertical Card List*).
    *   **Kolom Kanan (60%):** Denah Bed interaktif (*Mini Map*).
*   **Interaksi Utama (Drag & Drop):** Petugas dapat menyeret (*drag*) nama pasien dari antrian kiri, lalu menjatuhkannya (*drop*) ke kotak Bed hijau (kosong) di peta kanan untuk mengalokasikan kamar secara langsung.

### C. Menu ICU (Clinical Monitoring Center)
*   **Layout:** *Grid View* secara default (Kartu Kotak 4x3 atau disesuaikan dengan jumlah Bed).
*   **Bed Card UI:**
    *   Tiap kartu mewakili 1 Bed (misal: ICU-01). Menampilkan Nama Pasien, No MR.
    *   Menampilkan data vital (HR, BP, SpO2) dengan **grafik denyut (sparkline) neon** yang berjalan *real-time*.
*   **Sistem Peringatan (Alerts):** Jika parameter pasien melewati batas kritis, pinggiran kartu (*border*) akan **berdenyut merah (*pulsating glow*)**.
*   **Toggle:** Terdapat tombol kecil di kanan atas untuk beralih dari tampilan *Grid* (Peta Bed) ke tampilan *Table/List* klasik.

### D. Menu Petugas Ruang (Manajemen Bangsal & SPRI)
*   **Fokus:** Menampilkan daftar pasien bangsal yang sedang dirawat petugas terkait dan alur pembuatan SPRI (Surat Permintaan Rawat ICU).
*   **Layout Panel (3 Bagian):**
    1.  **Panel Pencarian/Daftar Pasien:** Tampilan baris *elevated*. Petugas mencari pasien yang kondisinya memburuk, lalu klik "Pilih".
    2.  **Panel Pembuatan SPRI (Modal Kanan/Overlay):** Formulir *Glassmorphism* minimalis. Data medis pasien otomatis terisi (*auto-fill*). Petugas hanya memilih Indikasi ICU (Dropdown) dan Tingkat Kegawatan.
    3.  **Riwayat SPRI:** Tampilan kartu ringkas (mirip resi) di bagian bawah yang menunjukkan status SPRI yang pernah dibuat (Menunggu, Disetujui, Ditolak).

---

## 6. Feedback & Interaksi Tambahan

*   **Skeleton Loading:** Ganti *spinner* berputar konvensional dengan *Skeleton Screens* (bentuk blok abu-abu yang berkedip napas/pulsing) saat memuat data pasien/grafik untuk persepsi memuat data yang lebih cepat.
*   **Auto-Refresh Indicator:** Titik hijau berdenyut (*pinging dot*) di pojok bawah tabel dengan teks *countdown* mundur (misal: "Auto-refresh: 15s").
*   **Toasts/Notifikasi:** Pemberitahuan sukses/gagal muncul melayang (*floating*) di pojok kanan atas dengan animasi masuk dan keluar (slide).