# Panduan Deployment — Mapping Bed ICU

## Pilihan Konfigurasi Database

Aplikasi ini mendukung **dua skema database**:

| Skema | DB Utama App | Data RS | Cocok untuk |
|---|---|---|---|
| **A — Full SQL Server** | SQL Server (database baru) | SQL Server RS (DB_RSUS) | Prod di jaringan RS, tanpa install MySQL |
| **B — MySQL + SQL Server** | MySQL | SQL Server RS (DB_RSUS) | Prod jika MySQL sudah tersedia |
| **C — Full MySQL (dev lokal)** | MySQL | MySQL (data dummy) | Dev lokal tanpa akses jaringan RS |

---

## Skema A — Full SQL Server (Rekomendasi Prod di RS)

Tidak perlu install MySQL sama sekali. Gunakan SQL Server RS yang sudah ada, buat database baru khusus aplikasi ICU.

### A1. Persiapan Database di SQL Server

Hubungi DBA RS, minta dibuat database baru dan user:

```sql
-- Jalankan di SQL Server RS (oleh DBA)
CREATE DATABASE mapping_bed_icu;
GO

CREATE LOGIN icu_app WITH PASSWORD = 'PASSWORD_KUAT';
GO

USE mapping_bed_icu;
CREATE USER icu_app FOR LOGIN icu_app;
ALTER ROLE db_owner ADD MEMBER icu_app;
GO
```

> Database `DB_RSUS` (data pasien) cukup akses READ saja:
> ```sql
> USE DB_RSUS;
> CREATE USER icu_app FOR LOGIN icu_app;
> -- Grant read only ke tabel yang dibutuhkan
> GRANT SELECT ON REGISTER_PASIEN TO icu_app;
> GRANT SELECT ON PENDAFTARAN TO icu_app;
> GRANT SELECT ON STATUS_KAMAR TO icu_app;
> GRANT SELECT ON M_KELAS TO icu_app;
> GRANT SELECT ON M_RUANG_MASTER TO icu_app;
> GRANT SELECT ON M_CARABAYAR TO icu_app;
> GRANT SELECT ON ICD10 TO icu_app;
> GO
> ```

### A2. Konfigurasi `.env` untuk Full SQL Server

```dotenv
APP_NAME="Mapping Bed ICU"
APP_ENV=production
APP_KEY=                          # diisi otomatis saat php artisan key:generate
APP_DEBUG=false
APP_URL=http://IP_ATAU_DOMAIN_SERVER

LOG_LEVEL=error

# ── DB Utama Aplikasi (SQL Server, database baru) ─────────────────────────────
DB_CONNECTION=sqlsrv
DB_HOST=192.168.200.160
DB_PORT=1433
DB_DATABASE=mapping_bed_icu
DB_USERNAME=icu_app
DB_PASSWORD=PASSWORD_KUAT
DB_TRUST_CERT=true
DB_ENCRYPT=false

# ── DB RS (SQL Server, database existing, read-only) ──────────────────────────
DB_RSUS_ENABLED=true
DB_RSUS_HOST=192.168.200.160
DB_RSUS_PORT=1433
DB_RSUS_DATABASE=DB_RSUS
DB_RSUS_USERNAME=icu_app
DB_RSUS_PASSWORD=PASSWORD_KUAT
DB_RSUS_TRUST_CERT=true

# ── Session & Cache (pakai database = SQL Server) ────────────────────────────
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database

# ── Keycloak SSO ──────────────────────────────────────────────────────────────
KEYCLOAK_ENABLED=true
KEYCLOAK_BASE_URL=http://192.168.200.157:8081
KEYCLOAK_REALM=myrealm
KEYCLOAK_CLIENT_ID=icu-bed
KEYCLOAK_CLIENT_SECRET=SECRET_DARI_KEYCLOAK_ADMIN
KEYCLOAK_REDIRECT_URI=http://IP_ATAU_DOMAIN_SERVER/auth/keycloak/callback

VITE_APP_NAME="${APP_NAME}"
```

---

## Skema B — MySQL + SQL Server

Gunakan ini jika MySQL sudah tersedia di server.

### B1. Buat Database MySQL

```bash
sudo mysql -u root -p
```
```sql
CREATE DATABASE mapping_bed_icu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'icu_user'@'localhost' IDENTIFIED BY 'PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON mapping_bed_icu.* TO 'icu_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### B2. Konfigurasi `.env` untuk MySQL + SQL Server

```dotenv
# ── DB Utama Aplikasi (MySQL) ─────────────────────────────────────────────────
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mapping_bed_icu
DB_USERNAME=icu_user
DB_PASSWORD=PASSWORD_KUAT

# ── DB RS (SQL Server) — sama seperti Skema A ────────────────────────────────
DB_RSUS_ENABLED=true
DB_RSUS_HOST=192.168.200.160
# ... (sisanya sama dengan Skema A)
```

---

## Bagian 1 — Prasyarat Server

### OS yang Didukung
Ubuntu 22.04 LTS / 24.04 LTS (direkomendasikan)

### Software yang Dibutuhkan

| Software | Versi | Wajib | Keterangan |
|---|---|---|---|
| PHP | 8.2+ | ✅ | Dengan extension tertentu |
| Nginx | 1.18+ | ✅ | Web server |
| Composer | 2.x | ✅ | PHP dependency manager |
| Node.js | 18+ | ✅ | Build frontend (hanya saat deploy) |
| pdo_sqlsrv | sesuai PHP | ✅ | Driver SQL Server |
| MySQL | 8.0+ | ❌ Opsional | Hanya untuk Skema B |

---

## Bagian 2 — Instalasi PHP & Extension

```bash
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2-fpm php8.2-cli php8.2-common \
    php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip \
    php8.2-bcmath php8.2-intl php8.2-tokenizer

# Jika pakai MySQL (Skema B), tambahkan:
# sudo apt install -y php8.2-mysql

# Verifikasi
php -v
```

### Install Driver SQL Server (wajib untuk semua skema)

```bash
# 1. Microsoft ODBC Driver 18
curl https://packages.microsoft.com/keys/microsoft.asc | sudo apt-key add -
curl https://packages.microsoft.com/config/ubuntu/22.04/prod.list \
    | sudo tee /etc/apt/sources.list.d/mssql-release.list

sudo apt update
sudo ACCEPT_EULA=Y apt install -y msodbcsql18 unixodbc-dev

# 2. PHP extension via PECL
sudo pecl install sqlsrv pdo_sqlsrv

# 3. Aktifkan extension
echo "extension=sqlsrv.so"     | sudo tee /etc/php/8.2/mods-available/sqlsrv.ini
echo "extension=pdo_sqlsrv.so" | sudo tee /etc/php/8.2/mods-available/pdo_sqlsrv.ini
sudo phpenmod sqlsrv pdo_sqlsrv
sudo systemctl restart php8.2-fpm

# Verifikasi
php -m | grep sqlsrv
# Harus muncul: sqlsrv dan pdo_sqlsrv
```

---

## Bagian 3 — Instalasi Nginx

```bash
sudo apt install -y nginx

sudo nano /etc/nginx/sites-available/mapping-bed-icu
```

Isi konfigurasi:

```nginx
server {
    listen 80;
    server_name IP_ATAU_DOMAIN_SERVER;

    root /var/www/mapping-bed-icu/public;
    index index.php;

    access_log /var/log/nginx/mapping-bed-icu-access.log;
    error_log  /var/log/nginx/mapping-bed-icu-error.log;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/mapping-bed-icu /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## Bagian 4 — Instalasi Composer & Node.js

```bash
# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer -V

# Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v && npm -v
```

---

## Bagian 5 — Deploy Aplikasi

### 5.1 Clone & Permissions

```bash
cd /var/www
sudo git clone <URL_REPO_GIT> mapping-bed-icu
cd mapping-bed-icu

sudo chown -R www-data:www-data /var/www/mapping-bed-icu
sudo chmod -R 775 storage bootstrap/cache
```

### 5.2 Konfigurasi .env

```bash
sudo -u www-data cp .env.example .env
sudo -u www-data nano .env
# Isi sesuai Skema A atau B di atas
```

### 5.3 Install Dependencies & Build Frontend

```bash
# PHP dependencies (production, tanpa dev packages)
sudo -u www-data composer install --no-dev --optimize-autoloader

# Generate APP_KEY
sudo -u www-data php artisan key:generate

# Build frontend assets (Vue + Vite)
sudo -u www-data npm install
sudo -u www-data npm run build

# Hapus node_modules setelah build
sudo rm -rf node_modules
```

### 5.4 Migrasi Database

```bash
# Buat semua tabel milik aplikasi di database mapping_bed_icu
# (users, sessions, icu_booking_external, icu_spri_internal, activity_logs, dll)
# Tabel mirror RS (m_kelas, registrasi_pasien, dll) TIDAK dibuat di production
sudo -u www-data php artisan migrate --force

# OPSIONAL: Buat user admin lokal sebagai fallback jika SSO Keycloak bermasalah
# Lewati ini jika semua user login via Keycloak
sudo -u www-data php artisan db:seed --class=UserSeeder --force
```

> **Tidak perlu `db:seed` penuh** — data pasien, bed, cara bayar sudah ada
> di SQL Server RS dan diambil live via koneksi `sqlsrv_rsus`.
> `DatabaseSeeder` hanya seed data dummy dan hanya berjalan di mode dev lokal.

### 5.5 Optimasi Cache

```bash
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

---

## Bagian 6 — Konfigurasi Keycloak

Di Keycloak Admin Console (`http://192.168.200.157:8081`):

### 6.1 Konfigurasi Client `icu-bed`

1. Realm `myrealm` → **Clients** → `icu-bed`
2. Tab **Settings**:
   - Valid Redirect URIs: `http://IP_SERVER/auth/keycloak/callback`
   - Valid Post Logout Redirect URIs: `http://IP_SERVER/login`
   - Web Origins: `http://IP_SERVER`
3. Tab **Credentials** → copy **Client Secret** → paste ke `.env` `KEYCLOAK_CLIENT_SECRET`

### 6.2 Buat Realm Roles

| Role Lokal | Nama Role di Keycloak (pilih salah satu) |
|---|---|
| `admin` | `admin` atau `icu-admin` |
| `admisi` | `admisi` atau `icu-admisi` atau `petugas-admisi` |
| `petugas_icu` | `petugas-icu` atau `icu-petugas` atau `nurse-icu` |
| `petugas_ruang` | `petugas-ruang` atau `nurse` |

### 6.3 Assign Role ke User

**Users** → pilih user → **Role Mapping** → **Assign Role** → pilih Realm Role.

> Mapping nama Keycloak → role lokal ada di `app/Services/KeycloakService.php`

---

## Bagian 7 — Verifikasi

```bash
# Cek extension PHP
php -m | grep -E "sqlsrv|pdo_sqlsrv|pdo_mysql"

# Test koneksi DB utama (SQL Server)
sudo -u www-data php artisan tinker --execute="DB::connection('sqlsrv')->getPdo(); echo 'App DB OK';"

# Test koneksi DB RS (SQL Server read-only)
sudo -u www-data php artisan tinker --execute="DB::connection('sqlsrv_rsus')->getPdo(); echo 'RS DB OK';"

# Cek status migration
sudo -u www-data php artisan migrate:status

# Cek log
tail -f /var/www/mapping-bed-icu/storage/logs/laravel.log
```

---

## Bagian 8 — Update Aplikasi

```bash
cd /var/www/mapping-bed-icu

# Maintenance mode
sudo -u www-data php artisan down

# Pull kode terbaru
sudo git pull origin main

# Update dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Build ulang frontend jika ada perubahan
sudo -u www-data npm install && sudo -u www-data npm run build && sudo rm -rf node_modules

# Jalankan migration baru
sudo -u www-data php artisan migrate --force

# Rebuild cache
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Selesai
sudo -u www-data php artisan up
```

---

## Bagian 9 — Troubleshooting

### 500 Error

```bash
tail -50 /var/www/mapping-bed-icu/storage/logs/laravel.log
tail -50 /var/log/nginx/mapping-bed-icu-error.log

# Pastikan APP_KEY tidak kosong
grep APP_KEY /var/www/mapping-bed-icu/.env

# Fix permission
sudo chown -R www-data:www-data /var/www/mapping-bed-icu/storage
sudo chmod -R 775 /var/www/mapping-bed-icu/storage
```

### Koneksi SQL Server Gagal

```bash
# Test koneksi manual
sqlcmd -S 192.168.200.160,1433 -U icu_app -P PASSWORD -Q "SELECT 1" -C

# Test via PHP
php -r "
\$conn = new PDO(
    'sqlsrv:Server=192.168.200.160,1433;Database=mapping_bed_icu;TrustServerCertificate=1',
    'icu_app', 'PASSWORD'
);
echo 'OK';
"

# Cek ODBC driver
odbcinst -q -d | grep -i sql
```

### Login SSO Tidak Bisa

```bash
# Test Keycloak reachable dari server
curl -I http://192.168.200.157:8081/realms/myrealm/.well-known/openid-configuration

# Pastikan KEYCLOAK_REDIRECT_URI di .env sama persis dengan yang di Keycloak client
grep KEYCLOAK_REDIRECT_URI /var/www/mapping-bed-icu/.env
```

### Clear Cache Setelah Edit .env

```bash
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan config:cache
```

---

## Ringkasan Tabel yang Dibuat di Database Utama

| Tabel | Fungsi | Dipakai di Prod |
|---|---|---|
| `users` | Akun login + Keycloak fields | ✅ |
| `sessions` | Session login | ✅ |
| `password_reset_tokens` | Reset password | ✅ |
| `cache` / `cache_locks` | Laravel cache | ✅ |
| `jobs` / `failed_jobs` | Queue | ✅ |
| `icu_booking_external` | Booking pasien luar | ✅ |
| `icu_spri_internal` | SPRI dari ruang internal | ✅ |
| `activity_logs` | Log aktivitas user | ✅ |
| `migrations` | Tracking migration | ✅ |
| `m_kelas` | Mirror data RS (dev only) | ❌ |
| `m_ruang_master` | Mirror data RS (dev only) | ❌ |
| `status_kamar` | Mirror data RS (dev only) | ❌ |
| `registrasi_pasien` | Mirror data RS (dev only) | ❌ |
| `pendaftaran` | Mirror data RS (dev only) | ❌ |
| `m_carabayar` | Mirror data RS (dev only) | ❌ |

> Tabel dengan "dev only" dibuat via migration tapi tidak diisi/dipakai saat `DB_RSUS_ENABLED=true`.
