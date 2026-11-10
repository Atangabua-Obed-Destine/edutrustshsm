# EduTrust

School management for Cameroonian schools, powered by I-NNOVA.

This guide is for the technician installing EduTrust on a school's server.

## What the server needs

- PHP 8.2 or newer, with these extensions on: `bcmath`, `ctype`, `dom`, `fileinfo`, `gd`, `iconv`, `mbstring`, `openssl`, `pdo_mysql`, `simplexml`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, `zip`, `zlib`
- MySQL or MariaDB
- Apache or Nginx
- Composer, Node.js and Git
- Internet access during the installation

XAMPP provides PHP, MariaDB and Apache. On XAMPP, turn on `gd` and `zip` in `php.ini` if they are off.

## Install

Clone into the web folder (`C:\xampp\htdocs` on XAMPP, `/var/www` on Linux), then:

```
git clone https://github.com/Atangabua-Obed-Destine/edutrustshsm.git edutrust
cd edutrust
composer install --no-dev
php artisan edutrust:install
```

The installer checks the server, asks for its address and database details, then sets everything up. It creates the database if it does not exist. On Windows, run it in PowerShell or Command Prompt rather than Git Bash, which can hang on the password question.

**The address** is what the school's computers will type to reach EduTrust, for example `http://192.168.1.10/edutrust/public`. Use the server's network address, not `localhost`, or other computers will not load uploaded logos. On Linux, point the web server's document root at the `public` folder and use `http://192.168.1.10`. Linux also needs `storage` and `bootstrap/cache` writable by the web server's user.

The installer finishes by printing a private link. Open it in a browser, on the server or any computer on the school's network:

1. Enter the school's name, code and levels. **Every student matricule starts with the code**, so agree it with the school first.
2. Create the super admin. This account creates all the others.

You arrive on the dashboard, signed in, with a list of what to set up next: the academic year, terms, sequences, subjects, classes, the intake batch and fees. Each item links to its screen, and **Configuration Health** shows the full list.

The link works once. If it gets lost, run `php artisan edutrust:install` again for a new one. If an installation stops part-way, fix the problem and run the same command. It never touches a school that already has accounts.

## After installing

**Scheduled jobs** apply late-fee fines, recurring entries and fee reminders. Run the scheduler every minute.

Linux, in `crontab -e`:

```
* * * * * cd /var/www/edutrust && php artisan schedule:run >> /dev/null 2>&1
```

Windows, in an administrator PowerShell:

```
schtasks /create /tn "EduTrust scheduler" /sc minute /mo 1 /ru SYSTEM /tr "C:\xampp\php\php.exe C:\xampp\htdocs\edutrust\artisan schedule:run"
```

**Email.** Until a mail server is set, emails only go to a log file. Add the school's `MAIL_*` settings to `.env`, then keep `php artisan queue:work` running so that parent invitations and fee reminders are sent.

**Backups.** EduTrust does not back itself up. Set up a daily copy of the database (`mysqldump`) and of the `storage/app` folder, kept away from the server.

## Updating a school

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
