# MediCore AI ERP — Doctor Dashboard Design Documentation

## 1. Overview
Dashboard utama untuk modul **Doctor Dashboard** dari aplikasi **MediCore AI ERP**, sebuah sistem manajemen rumah sakit. Halaman ini menampilkan ringkasan aktivitas harian dokter, jadwal pasien, peringatan klinis berbasis AI, dan akses cepat ke modul-modul lain.

---

## 2. Layout Structure

### 2.1 Grid Utama
- **Sidebar kiri** (fixed, ~210px): navigasi utama.
- **Header atas** (full width, sticky): search bar, ikon notifikasi, profil dokter.
- **Konten utama** (area scrollable): disusun dalam grid 3 kolom utama (kiri-tengah lebih lebar, kanan lebih sempit sebagai panel sekunder).

### 2.2 Komposisi Halaman (top → bottom)
1. Header global (search, quick actions, notifikasi, profil)
2. Banner sambutan ("Good morning, Dr. Mehta!") + AI Clinical Assistant card + kalender mini
3. Baris kartu statistik (5 metric cards)
4. Baris tengah: Today's Appointments | Quick Links | Patient Queue Status (donut chart) | Today's Schedule | Notifications
5. Baris bawah: Mobile App promo | Upcoming Consultations | Recent Patient Notes | AI Patient Risk Alerts | Smart Reminders | AI Assistant chat widget

---

## 3. Sidebar Navigation
- Logo brand "MediCore AI" + subtitle "MediCore AI ERP" di bagian atas.
- Menu list vertikal dengan ikon + label:
  - Dashboard (active/highlighted, background biru muda)
  - Patients
  - Appointments
  - Prescriptions
  - Lab Reports
  - Treatment Plans
  - Billing & Invoices
  - Medical Records
  - AI Diagnosis Support
  - Messages (dengan badge notifikasi angka "8")
  - Tasks
  - Analytics
  - Inventory
  - Settings
- Item aktif ditandai dengan background pill berwarna biru muda dan teks biru tua/bold.

---

## 4. Header (Top Bar)
- Search input full-width dengan placeholder "Search patients, appointments, reports..." dan tombol close (x).
- Ikon aksi cepat: tombol "+" (add baru), ikon kalender, ikon bell (notifikasi dengan badge merah angka).
- Profil dokter di kanan: foto avatar bulat, nama "Dr. Rohan Mehta", subtitle "Cardiologist", dengan chevron dropdown.

---

## 5. Hero / Welcome Section
**Card besar berwarna gradasi biru muda — lebar ±60% dari konten:**
- Headline: "Good morning, Dr. Mehta!"
- Subteks: "Here's what's happening in your practice today. Stay aware, stay ahead."
- Info baris: ikon jam + "09:30 AM, May 20, 2026, Tuesday" dan ikon lokasi + "Cardiology Department, CityCare Multi-Speciality Hospital"
- Ilustrasi dokter (karakter AI/avatar) di sisi kanan card.

**Card "AI Clinical Assistant" (kanan atas, lebih kecil):**
- Badge "BETA"
- Judul: "AI Clinical Assistant"
- Deskripsi: "Your AI assistant for smarter clinical decisions and summaries."
- Sub-card di dalamnya: "AI Patient Summary Generator" dengan deskripsi singkat dan tombol CTA biru "Generate Summary".

**Mini Calendar (kanan, paling atas):**
- Header bulan: "May 2026" dengan navigasi prev/next.
- Grid hari (Sun–Sat) dan tanggal 28–31, dengan tanggal aktif (21) ditandai lingkaran biru solid.

---

## 6. Statistic Cards (5 kartu sejajar)
Setiap kartu memiliki: ikon berwarna dalam kotak rounded, label kecil, angka besar (bold), dan indikator perubahan (naik/turun % dibanding hari sebelumnya).

| Kartu | Ikon | Nilai | Perubahan |
|---|---|---|---|
| Total Patients Today | orang (biru) | 42 | +5% from yesterday |
| Pending Prescriptions | dokumen (oranye) | 18 | -8% from yesterday |
| Lab Reports to Review | tabung lab (ungu) | 26 | +12% from yesterday |
| Follow-up Patients | kalender (hijau) | 31 | +7% from yesterday |
| Emergency Alerts | segitiga peringatan (merah) | 3 | View all alerts → |

Kartu emergency alert menggunakan warna aksen merah untuk menonjolkan urgensi.

---

## 7. Today's Appointments (panel kiri-tengah)
- Header: "Today's Appointments" + link "View all".
- List item per pasien terdiri dari:
  - Avatar foto bulat
  - Nama pasien (bold)
  - Jenis kunjungan (mis. "Consultation", "ECG Review", "Follow-up")
  - Waktu (format jam)
  - Status badge berwarna: **Confirmed** (hijau), **Pending** (kuning/oranye)
- Footer: tombol/link "View Full Schedule".

Contoh data: Arjun Sharma (09:30, Confirmed), Neha Kapoor (10:15, Consultation), Vikram Singh (11:00, ECG Review, Pending), Pooja Verma (12:00, Confirmed), Rakesh Patel (02:00, Pending).

---

## 8. Quick Links
- Grid 2 kolom x 3 baris berisi shortcut icon + label ke modul lain:
  - Patient Records, Appointments, Prescriptions, Lab Reports, Treatment Plans, Medical History
- Tombol khusus "AI Diagnosis Support" dengan ikon dan chevron, ditampilkan menonjol (full width, di bagian bawah grid).

---

## 9. Patient Queue Status
- Donut chart di tengah dengan label total di pusat ("Total 42").
- Breakdown status dengan warna berbeda dan persentase:
  - Waiting (14%)
  - In Consultation (16%)
  - Completed (10%)
  - No Show (2%)
- Statistik tambahan di bawah chart: "Average Wait Time: 18 mins" dan "Longest Wait Time: 45 mins".

---

## 10. Today's Schedule (panel kanan atas)
- Header + link "View all"
- List linimasa jadwal dengan waktu, nama pasien, jenis kunjungan, dan status badge (Confirmed/Pending), mirip struktur Today's Appointments tapi format ringkas vertikal.
- Footer: link "View Full Calendar".

---

## 11. Notifications
- Header + link "View all"
- List notifikasi dengan ikon kategori (lab, appointment, prescription) dan teks singkat + timestamp relatif ("5 mins ago", "20 mins ago", "1 hour ago").
- Contoh: "Lab report for Arjun Sharma is ready", "New appointment request from Priya Nair", "Prescription alert: 18 pending prescriptions".

---

## 12. Mobile App Promo Card (kiri bawah)
- Background biru gelap kontras (dark navy).
- Judul: "MediCore AI Mobile"
- Deskripsi: "Access patient data, appointments and more on the go."
- Dua badge tombol: App Store dan Google Play.
- Elemen ilustrasi mockup ponsel di sisi kanan card.

---

## 13. Upcoming Consultations
- Header + link "View all"
- List pasien dengan avatar, nama, jenis konsultasi, dan waktu (mis. "01:00 PM").
- Contoh: Ananya Joshi, Mohit Bansal, Vikram Singh.

---

## 14. Recent Patient Notes
- Header + link "View all Notes"
- List nama pasien dengan icon dokumen/catatan kecil, tanpa detail tambahan yang ditampilkan secara eksplisit pada level ringkasan.

---

## 15. AI Patient Risk Alerts
- Header + link "View all"
- List kartu alert dengan severity badge berwarna:
  - **High Risk** (merah) — Rakesh Patel, Age 68 - Diabetic
  - **Medium Risk** (kuning/oranye) — Savitri Devi, Age 64 - Cardiac
  - **Medium Risk** (kuning/oranye) — Mohit Bansal
- Tombol aksi kecil (eye icon "review/view") di tiap baris.

---

## 16. Smart Reminders
- List reminder dengan ikon kategori dan teks ringkas + jumlah:
  - "5 follow-ups due today"
  - "12 Lab reports pending review"
  - "18 Prescriptions pending"
  - "3 Insurance approvals pending"
  - "4 Appointments need confirmation"
- Tiap baris memiliki tombol aksi kecil di kanan (mis. ikon centang/checklist).

---

## 17. AI Assistant Chat Widget (floating, kanan bawah)
- Card kecil dengan status "Online" (indikator hijau).
- Avatar AI assistant.
- Bubble chat contoh: "Hello Dr. Mehta! How can I assist you today?"
- Quick reply chips: "Summarize Patient", "Suggest Diagnosis", "Check Drug Interaction".
- Input field "Ask me anything..." dengan tombol kirim (paper plane icon).

---

## 18. Visual Design System

### 18.1 Warna
- **Primary Blue**: digunakan untuk aksen utama, tombol CTA, item navigasi aktif, ikon utama, dan elemen highlight (mis. tanggal aktif di kalender).
- **Background**: putih/abu sangat terang (#F7F9FC-ish) untuk kanvas utama, putih murni untuk card.
- **Dark Navy**: digunakan pada card promo mobile app sebagai warna kontras gelap.
- **Status Colors**:
  - Hijau → Confirmed / Completed / positif
  - Kuning/Oranye → Pending / Medium Risk
  - Merah → Emergency / High Risk / alert kritis
  - Ungu → kategori lab/khusus (lab reports icon)

### 18.2 Tipografi
- Heading besar (welcome banner): bold, ukuran besar (~24-28px).
- Section titles: semi-bold, ~16-18px.
- Body text & list items: regular, ~13-14px.
- Angka statistik (metric cards): bold, ukuran besar (~28-32px) sebagai focal point.

### 18.3 Komponen Reusable
- **Card**: rounded corners (~12-16px radius), shadow halus, padding konsisten.
- **Badge/Status Pill**: rounded-full, padding horizontal kecil, warna background pastel sesuai status, teks warna solid senada.
- **Avatar**: foto bulat (circle), ukuran konsisten ~32-40px untuk list, lebih besar (~48px) untuk header profil.
- **Icon Container**: kotak rounded dengan background pastel sesuai kategori warna, ikon solid di tengah.
- **List Item**: avatar/icon + 2 baris teks (judul + subjudul) + elemen kanan (waktu/badge/aksi).
- **Section Header**: judul + link "View all" rata kanan.
- **Donut/Pie Chart**: dengan label total di tengah dan legend warna di bawah/samping.

### 18.4 Spacing & Grid
- Gutter antar card konsisten (~16-20px).
- Card statistik disusun dalam grid 5 kolom sejajar dengan tinggi sama.
- Panel konten utama menggunakan grid 3-4 kolom responsif untuk baris tengah dan bawah.

---

## 19. Komponen yang Disarankan untuk Dikembangkan (Dev Notes)
- `SidebarNav` — list menu dengan state active/inactive
- `TopHeaderBar` — search + notification + profile dropdown
- `WelcomeBanner` — hero card dengan ilustrasi & info waktu/lokasi
- `AIAssistantCard` — card promo AI dengan CTA
- `MiniCalendar` — komponen kalender ringkas
- `StatCard` — kartu metrik dengan ikon, angka, dan delta indicator
- `AppointmentListItem` / `ScheduleListItem` — item list dengan avatar, status badge
- `QuickLinksGrid` — grid shortcut menu
- `DonutChartCard` — chart status pasien dengan legend
- `NotificationListItem`
- `RiskAlertCard` — dengan severity badge (High/Medium/Low)
- `SmartReminderItem`
- `ChatWidget` — floating AI assistant chat box
- `MobileAppPromoCard`

---

*Dokumen ini dihasilkan dari analisis visual screenshot UI "MediCore AI ERP — Doctor Dashboard" dan dimaksudkan sebagai referensi desain/spec untuk tim development atau desain lanjutan.*
