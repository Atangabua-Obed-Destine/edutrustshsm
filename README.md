# EduTrust

School management for Cameroonian schools, powered by I-NNOVA.

This guide is for the technician installing EduTrust on a school's server, from a bare machine to a school ready to use. It works on Windows with XAMPP and on Linux.

1. [Prepare the server](#1-prepare-the-server)
2. [Get the code](#2-get-the-code)
3. [Run the installer](#3-run-the-installer)
4. [Create the school and its super admin](#4-create-the-school-and-its-super-admin)
5. [Linux only: the web server](#5-linux-only-the-web-server)
6. [After installing](#6-after-installing)
7. [Updating a school](#7-updating-a-school)

## 1. Prepare the server

The server needs:

- PHP 8.2 or newer, with these extensions on: `bcmath`, `ctype`, `dom`, `fileinfo`, `gd`, `iconv`, `mbstring`, `openssl`, `pdo_mysql`, `simplexml`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, `zip`, `zlib`
- MySQL or MariaDB
- Apache or Nginx
- Composer 2, Node.js 18 or newer, and Git
- Internet access during the installation

### Windows (XAMPP)

1. Install XAMPP (PHP, MariaDB and Apache), Composer, Node.js and Git for Windows.
2. Turn on `gd` and `zip`, which XAMPP leaves off. Open `C:\xampp\php\php.ini` (XAMPP Control Panel → Apache → Config → PHP (php.ini)) and remove the `;` at the start of these two lines:

   ```
   extension=gd
   extension=zip
   ```

   Save, then restart Apache. Without them `composer install` stops with an error such as `requires ext-gd`, and the Excel exports cannot work.
3. Start Apache and MySQL in the XAMPP Control Panel.
4. Check from a terminal: `php -m` must list `gd` and `zip`.

### Linux (Ubuntu or Debian)

```
sudo apt update
sudo apt install apache2 mariadb-server git unzip composer nodejs npm \
  php php-cli php-mysql php-gd php-zip php-mbstring php-xml php-bcmath php-curl
sudo a2enmod rewrite && sudo systemctl restart apache2
```

Check that `php -v` shows 8.2 or newer and `node -v` shows 18 or newer.

## 2. Get the code

Clone into the web folder (`C:\xampp\htdocs` on Windows, `/var/www` on Linux):

```
git clone https://github.com/Atangabua-Obed-Destine/edutrustshsm.git edutrust
cd edutrust
composer install --no-dev
```

Run Composer inside the cloned folder and do not delete anything from it: it installs the EdutrustPay package from the repository's own `packages/reporting-contract` folder.

## 3. Run the installer

```
php artisan edutrust:install
```

On Windows, run it in PowerShell or Command Prompt rather than Git Bash, which can hang on the password question.

The installer asks for:

- **The address** the school's computers will type to reach EduTrust, for example `http://192.168.1.10/edutrust/public`. Use the server's network address (`ipconfig` on Windows, `ip a` on Linux), not `localhost`, or other computers will not load uploaded logos. On Linux with the document root on `public` (step 5), the address is `http://192.168.1.10`.
- **The database details**: host, name, user and password. It creates the database if it does not exist.

It then checks every PHP extension above and names any that is missing, writes `.env`, builds the screens, creates the tables and links storage. It finishes by printing a private link.

If an installation stops part-way, fix the problem and run the same command again. It never touches a school that already has accounts.

## 4. Create the school and its super admin

Open the link in a browser, on the server or any computer on the school's network:

1. Enter the school's name, code and levels. **Every student matricule starts with the code**, so agree it with the school first.
2. Create the super admin. This account creates all the others.

You arrive on the dashboard, signed in, with a list of what to set up next: the academic year, terms, sequences, subjects, classes, the intake batch and fees. Each item links to its screen, and **Configuration Health** shows the full list.

The link works once. If it gets lost, run `php artisan edutrust:install` again for a new one.

## 5. Linux only: the web server

- Point the web server's document root at the `public` folder, with `AllowOverride All` on Apache.
- Make the folders EduTrust writes to writable by the web server's user:

  ```
  sudo chown -R www-data:www-data storage bootstrap/cache
  ```

## 6. After installing

### Scheduler

Late-fee fines, recurring entries, fee reminders and the EdutrustPay reports all run on the scheduler, which must run every minute.

Windows, in an administrator PowerShell:

```
schtasks /create /tn "EduTrust scheduler" /sc minute /mo 1 /ru SYSTEM /tr "C:\xampp\php\php.exe C:\xampp\htdocs\edutrust\artisan schedule:run"
```

Linux, in `crontab -e`:

```
* * * * * cd /var/www/edutrust && php artisan schedule:run >> /dev/null 2>&1
```

### Email

Until a mail server is set, emails only go to a log file. Add the school's `MAIL_*` settings to `.env`, then keep `php artisan queue:work` running so that parent invitations and fee reminders are sent.

### EdutrustPay

Under **System Settings → EdutrustPay Reporting**, paste the console address, institution reference, key id and secret that the body gives you, then press **Test connection** and **Send reports now**.

The page should say the scheduler is **Running**. Each night the school then sends the last three complete months and the current month up to that day. The current month is marked provisional until it ends, then sent again as final.

### A school reached through a tunnel

If the school is reached from outside through Cloudflare Tunnel or a similar service, the tunnel program normally runs on the server itself, and nothing needs setting.

If it runs on a different computer, add that computer's address to `.env`, keeping the first two:

```
TRUSTED_PROXIES=127.0.0.1,::1,192.168.1.20
```

Then run `php artisan config:clear`. Without it, payments over the tunnel fail with "The server could not be reached", because the browser blocks the page's own requests.

### Opening EduTrust as an app

On each office computer, open EduTrust in Google Chrome and sign in once. Then:

- **Through the tunnel (an `https` address):** click the install icon at the right of the address bar, or open the menu ⋮ and choose **Install EduTrust**.
- **On the school network (an `http://192.168…` address):** open the menu ⋮ → **Cast, save and share** → **Create shortcut**, and tick **Open as window**.

EduTrust then opens from a desktop or taskbar icon in its own window, without the browser's address bar. The icon is the school's logo if one is set in School Settings.

### Backups

EduTrust does not back itself up. Set up a daily copy of the database (`mysqldump`) and of the `storage/app` folder, kept away from the server.

## 7. Updating a school

Back up first, then in the EduTrust folder:

```
git pull
composer install --no-dev
php artisan migrate --force
npm ci
npm run build
php artisan optimize:clear
```

## If the super admin is locked out

There is no password reset by email yet. On the server, run this with the account's email and a new password, then ask them to sign in:

```
php artisan tinker --execute="App\Models\User::where('email', 'name@school.com')->firstOrFail()->update(['password' => Hash::make('a-new-password')]);"
```

## For developers

Copy `.env.example` to `.env`, set `APP_ENV=local` and `APP_DEBUG=true`, then run `composer install`, `php artisan edutrust:install`, and `composer run dev`.

`php artisan test` runs the tests on an in-memory database.

`packages/reporting-contract` is a copy of the same package in the EdutrustPay repository, kept here so a clone installs on its own. Copy it again when the contract changes.
