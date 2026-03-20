# Padeliotunyrai.lt

Padelio turnyrų reprezentacinė svetainė.

## Technologijos
- Laravel 11 (PHP 8.2)
- MySQL
- Nginx
- Filament v3 admin panelė

## Diegimas serveryje

### 1. Klonuoti repozitoriją
```bash
git clone https://github.com/tadas12587/padelioturnurai.lt.git /var/www/padeliotunyrai
cd /var/www/padeliotunyrai
```

### 2. Įdiegti priklausomybes
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Konfigūruoti .env
```bash
cp .env.example .env
php artisan key:generate
```

Užpildyti `.env` faile:
- DB_CONNECTION=mysql
- DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
- MAIL_MAILER, MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD
- MAIL_FROM_ADDRESS — el. paštas iš kurio siunčiamos žinutės
- APP_URL=https://padeliotunyrai.lt

### 4. Duomenų bazė
```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
```

### 5. Nginx konfigūracija
```nginx
server {
    listen 80;
    server_name padeliotunyrai.lt www.padeliotunyrai.lt;
    root /var/www/padeliotunyrai/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 6. Admin panelė
URL: `https://padeliotunyrai.lt/admin`

Prisijungimo duomenys po `db:seed`:
- El. paštas: `admin@padeliotunyrai.lt`
- Slaptažodis: `Admin123!`

**Pirmą kartą prisijungus — pakeisk slaptažodį!**

### 7. Nuotraukos ir logotipai
Nuotraukos saugomos `storage/app/public/`. Po diegimo:
```bash
php artisan storage:link
```

## Kalbos
- Lietuvių (numatyta): `/`, `/turnyrai`, `/kontaktai`
- Anglų: `/en/`, `/en/turnyrai`, `/en/kontaktai`
