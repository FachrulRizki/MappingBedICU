# AuraHealth Dashboard — Design Documentation

## Gambaran Umum

**AuraHealth** adalah platform manajemen kesehatan berbasis web dengan tampilan dashboard modern bertema gelap (*dark mode*). Dashboard ini dirancang untuk profesional medis dan pengguna yang ingin memantau kondisi kesehatan secara menyeluruh — mulai dari nutrisi harian, kepatuhan pengobatan, hingga manajemen pasien.

---

## Palet Warna

| Peran | Warna | Hex |
|---|---|---|
| Background Utama | Hitam kehijauan gelap | `#0D1A17` |
| Background Sidebar | Hijau tua sangat gelap | `#0F1E1A` |
| Surface / Card | Hijau gelap | `#132A23` |
| Aksen Utama | Hijau terang (teal) | `#2DD9A4` |
| Aksen Sekunder | Hijau muda | `#4AEAB5` |
| Teks Utama | Putih | `#FFFFFF` |
| Teks Sekunder | Abu-abu kehijauan | `#8EA89E` |
| Status Aktif | Hijau cerah | `#3DDB8A` |
| Status Tidak Aktif | Oranye/merah redup | `#E07050` |
| Badge / Tag | Teal redup | `#1A3D32` |

**Prinsip Warna:**
- Dominasi warna gelap menciptakan nuansa klinis dan profesional.
- Aksen teal digunakan secara konsisten sebagai penanda interaktif, highlight data, dan CTA (Call-to-Action).
- Gradasi dari `#0D1A17` ke `#132A23` pada card memberikan kedalaman visual tanpa gangguan.

---

## Tipografi

| Elemen | Gaya |
|---|---|
| Heading Utama | Sans-serif tebal, bersih (contoh: **Plus Jakarta Sans Bold** atau **DM Sans Bold**) |
| Body & Label | Sans-serif ringan, mudah dibaca (contoh: **Inter Regular** atau **DM Sans Regular**) |
| Angka / Statistik | Monospaced atau tabular figures untuk keselarasan data |
| Badge / Status | Huruf kecil (lowercase), ukuran kecil, berat medium |

**Hierarki Teks:**
1. **Headline besar** — Ukuran 32–40px, putih, bobot 700
2. **Subheading / Label Section** — 14–16px, putih 70%, bobot 600
3. **Body / Deskripsi** — 13–14px, abu-abu kehijauan, bobot 400
4. **Caption / Metadata** — 11–12px, abu-abu redup, bobot 400

---

## Layout & Struktur

```
┌──────────────────────────────────────────────────────────────┐
│  Sidebar (240px fixed)  │  Main Content Area (fluid)         │
│                         │                                    │
│  Logo                   │  Top Bar (Search + Lang + Avatar)  │
│  Nav Menu               │─────────────────────────────────── │
│    - Dashboard          │  Hero Banner + Stats Cards (2-col) │
│    - Medical Team       │─────────────────────────────────── │
│    - Patients           │  Nutrition + Adherence (2-col)     │
│    - Medications        │─────────────────────────────────── │
│    - Messages           │  Professionals + Weight + BP (3-col│
│                         │─────────────────────────────────── │
│  More                   │  Recent Patients Table (full-width)│
│    - Settings           │                                    │
│    - Tutorials          │                                    │
│    - Help & Support     │                                    │
│                         │                                    │
│  Promo Card             │                                    │
└──────────────────────────────────────────────────────────────┘
```

### Grid System
- **Sidebar**: Lebar tetap 240px, tinggi penuh layar, posisi fixed
- **Main Content**: Padding horizontal 24–32px, padding vertikal 20px
- **Card Grid**: Menggunakan CSS Grid 12-kolom, dengan breakpoint responsif
- **Gap antar Card**: 16–20px

---

## Komponen UI

### 1. Sidebar
- Background solid gelap dengan sedikit batas kanan (border 1px transparan)
- Menu item: padding 12px 16px, border-radius 10px
- **State aktif**: Background teal gelap `#1A3D32`, teks teal terang, ikon teal
- **State hover**: Background sedikit lebih terang dari default
- Ikon kecil (16–18px) di sebelah kiri label
- Badge notifikasi (angka merah kecil) pada item "Messages"
- Promo card di bagian bawah dengan background teal gelap dan CTA button

### 2. Top Bar / Header
- Background sama dengan konten utama (tidak ada perbedaan mencolok)
- Search bar: Input dengan ikon kaca pembesar, background lebih gelap, border subtle
- Dropdown bahasa (EN) dengan chevron
- Avatar pengguna di kanan (lingkaran, 36–40px)

### 3. Hero Banner
- Background gradient dari hijau teal `#1A3D32` ke teal cerah `#2A5A48`
- Teks "Check Your Health!" besar (32–40px), putih, bold
- Ilustrasi karakter dokter 3D di sisi kanan (elemen dekoratif)
- Rounded corner 16px, padding 24–32px

### 4. Stats Card (Kecil)
- Background card gelap
- Ikon di kiri atas dengan background teal sangat redup
- Angka besar (36–48px, bold) sebagai metrik utama
- Label kecil di bawah angka
- Perubahan persentase dengan indikator panah (↑ hijau / ↓ merah)

### 5. Donut Chart — Today's Nutrition
- Chart berbentuk cincin tebal dengan gradasi warna
- Angka kalori di tengah chart (bold, putih)
- Legend: Protein (teal), Carbs (hijau muda), Fat (abu-abu)
- Background card dengan border-radius 16px

### 6. Line Chart — Adherence Overview
- Dua line: Medication Intake (teal solid) & Range Compliance (teal transparan/dashed)
- Tooltip muncul saat hover dengan nilai persentase
- Area bawah garis terisi dengan gradient transparan
- Sumbu X: hari dalam seminggu (Sat–Fri)
- Sumbu Y: persentase 0–100%
- Dropdown filter "Weekly" di kanan atas card

### 7. Bar Chart — Weight Tracking Trends
- Bar vertikal, warna teal dengan variasi tinggi
- Sumbu X: Week 1–4
- Sumbu Y: skala berat
- Background gelap, minimalis

### 8. Area/Line Chart — Blood Pressure
- Dua line (Systolic & Diastolic) dengan area gradient di bawahnya
- Skala tekanan darah di sumbu Y: 80/60 – 160/100
- Sumbu X: S M T W T F S (hari)

### 9. List Card — Recent Professionals
- Avatar bulat tiap dokter (foto kecil, ~36px)
- Nama dan spesialisasi dokter
- Jumlah pasien di kanan (teks abu-abu)
- Link "View All" di kanan atas card
- Separator tipis antar item

### 10. Data Table — Recent Patients
- Header kolom: Checkbox, Patient Name, Age, Doctor, Status, Created At, Adherence, Actions
- Setiap baris: Avatar inisial berwarna, nama + email, data terstruktur
- Badge Status: `Active` (hijau) / `Inactive` (oranye/merah), pill-shape kecil
- Badge Adherence: `Intake` / `Range` (teal redup)
- Action icons: View (mata), Edit (pensil), More (tiga titik)
- Tombol "+ Add Patient" di kanan atas (CTA utama, background teal)
- Pagination di bawah tabel: Previous / 1 2 3 ... 7 / Next

---

## Ikonografi

- Style: **Line icons**, stroke tipis 1.5–2px, bersih dan minimalis
- Ukuran: 16–20px untuk navigasi, 18–24px untuk card
- Konsisten menggunakan satu library ikon (contoh: **Phosphor Icons**, **Lucide**, atau **Heroicons**)
- Warna ikon default: abu-abu kehijauan; aktif/highlighted: teal

---

## Efek Visual & Interaksi

| Elemen | Efek |
|---|---|
| Card hover | Slight brightness increase + subtle shadow |
| Button hover | Brightness +10%, subtle scale(1.02) |
| Sidebar nav hover | Background fade in 150ms |
| Chart tooltip | Fade in, posisi mengikuti kursor |
| Badge / Status | Subtle glow (box-shadow teal/merah tipis) |
| Scroll konten | Custom scrollbar tipis berwarna teal redup |

---

## Spacing System

Menggunakan skala **4px base**:

| Token | Nilai |
|---|---|
| xs | 4px |
| sm | 8px |
| md | 16px |
| lg | 24px |
| xl | 32px |
| 2xl | 48px |

- Card padding: `24px`
- Gap antar komponen: `16–20px`
- Border-radius card: `12–16px`
- Border-radius badge: `6px` (pill untuk status)
- Border-radius button: `8–10px`

---

## Prinsip Desain

1. **Clarity over decoration** — Setiap elemen visual memiliki fungsi. Tidak ada hiasan tanpa tujuan.
2. **Data first** — Informasi medis ditampilkan dengan hierarki yang jelas; angka penting selalu menonjol.
3. **Dark mode by default** — Mengurangi kelelahan mata untuk penggunaan jangka panjang di lingkungan klinis.
4. **Konsistensi warna** — Aksen teal digunakan secara konsisten sebagai penanda status positif, aksi, dan data aktif.
5. **Accessibilitas** — Kontras teks terhadap background memenuhi standar WCAG AA minimum (ratio ≥ 4.5:1 untuk teks kecil).

---

## Referensi Desain

- **Inspirasi**: Klinik digital modern, health-tech SaaS platform
- **Mood Board**: Dark teal + emerald, clean data visualization, professional medical aesthetics
- **Target Pengguna**: Dokter, administrator klinik, pasien yang melek teknologi
- **Platform**: Web (desktop-first, responsif ke tablet)
