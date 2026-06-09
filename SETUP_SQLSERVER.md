# Setup Koneksi SQL Server (DB Staging RS)

## Kondisi Saat Ini

Aplikasi menggunakan **dual connection**:
- **MySQL lokal** (`bed-icu`) — untuk tabel baru ICU (users, icu_admision, icu_booking_external, icu_spri_internal)
- **SQL Server staging** (`192.168.200.160:1433`, DB_RSUS) — untuk tabel existing RS (REGISTER_PASIEN, PENDAFTARAN, STATUS_KAMAR, M_KELAS, M_RUANG_MASTER)

Jika driver `pdo_sqlsrv` belum terinstall, aplikasi otomatis **fallback ke MySQL lokal** (data seeder).

---

## Langkah Install Driver SQL Server untuk PHP 8.3 (Laragon Windows)

### 1. Download Driver PHP SQL Server

Buka: https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server

Download **SQLSRV 5.12.0** untuk Windows x64.

Extract, ambil 2 file untuk PHP 8.3 Thread Safe (TS) x64:
- `php_sqlsrv_83_ts_x64.dll`
- `php_pdo_sqlsrv_83_ts_x64.dll`

### 2. Copy ke Folder Extension PHP Laragon

```
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\ext\
```

Copy kedua file `.dll` ke folder tersebut.

### 3. Install Microsoft ODBC Driver 18 (prerequisite)

Download: https://learn.microsoft.com/en-us/sql/connect/odbc/download-odbc-driver-for-sql-server

Install `msodbcsql.msi` (x64).

### 4. Edit php.ini

Buka: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.ini`

Tambahkan di bagian extension (setelah baris `extension=pdo_sqlite`):

```ini
extension=php_sqlsrv_83_ts_x64
extension=php_pdo_sqlsrv_83_ts_x64
```

### 5. Update .env — Isi Kredensial SQL Server

```env
DB_RSUS_HOST=192.168.200.160
DB_RSUS_PORT=1433
DB_RSUS_DATABASE=DB_RSUS
DB_RSUS_USERNAME=username_sql_server_anda
DB_RSUS_PASSWORD=password_anda
DB_RSUS_TRUST_CERT=true
```

### 6. Restart Laragon

Stop semua service Laragon → Start kembali.

### 7. Verifikasi

Buka terminal di folder project:

```bash
php artisan db:test-rsus
```

Output yang diharapkan:
```
pdo_sqlsrv extension  : ✓ YA
RegistrasiPasien  → connection: sqlsrv_rsus | table: REGISTER_PASIEN
MKelas            → connection: sqlsrv_rsus | table: M_KELAS
StatusKamar       → connection: sqlsrv_rsus | table: STATUS_KAMAR
RegistrasiPasien count : [angka dari DB RS] ✓
```

---

## Mapping Tabel

| Model Laravel         | Tabel SQL Server  | Tabel MySQL Lokal     | Aksi           |
|-----------------------|-------------------|-----------------------|----------------|
| RegistrasiPasien      | REGISTER_PASIEN   | registrasi_pasien     | Read           |
| Pendaftaran           | PENDAFTARAN       | pendaftaran           | Read           |
| StatusKamar           | STATUS_KAMAR      | status_kamar          | Read + Write   |
| MKelas                | M_KELAS           | m_kelas               | Read only      |
| MRuangMaster          | M_RUANG_MASTER    | m_ruang_master        | Read only      |
| User                  | -                 | users                 | MySQL saja     |
| IcuAdmision           | -                 | icu_admision          | MySQL saja     |
| IcuBookingExternal    | -                 | icu_booking_external  | MySQL saja     |
| IcuSpriInternal       | -                 | icu_spri_internal     | MySQL saja     |

---

## Tabel Baru yang Perlu Dibuat di SQL Server (Opsional)

Jika ingin tabel ICU juga di SQL Server, jalankan:

```bash
php artisan migrate --database=sqlsrv_rsus --path=database/migrations/2026_06_04_000005_create_icu_admision_table.php
```

Tapi untuk saat ini, tabel ICU tetap di MySQL lokal sudah cukup untuk testing.
