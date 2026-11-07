<?php

namespace App\Console\Commands;

use App\Models\Form;
use App\Services\SchoolInstaller;
use App\Support\EnvFile;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use PDO;
use PDOException;

/**
 * Prepares a freshly cloned server for one school, then hands over to the
 * browser for the school's details and its super admin.
 *
 * It never touches a school that is already running: it stops if the database
 * already has accounts, and can be run again after a failure.
 */
class InstallSchool extends Command
{
    protected $signature = 'edutrust:install
        {--url= : The address school computers will use to open EduTrust}
        {--db-host= : Database server}
        {--db-port= : Database port}
        {--db-database= : Database name, created if it does not exist}
        {--db-username= : Database user}
        {--db-password= : Database password}';

    protected $description = 'Install EduTrust for a new school on this server';

    private const REQUIRED_EXTENSIONS = [
        'bcmath', 'ctype', 'dom', 'fileinfo', 'gd', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'zip',
    ];

    public function handle(SchoolInstaller $installer): int
    {
        $this->newLine();
        $this->line('  <options=bold>EduTrust</>, powered by I-NNOVA');
        $this->newLine();

        if ($installer->isInstalled()) {
            $this->components->error('This school is already installed. Nothing was changed.');

            return self::FAILURE;
        }

        // A cached configuration would ignore everything written to .env below.
        $this->callSilent('config:clear');

        if ($problems = $this->serverProblems()) {
            $this->components->error('This server is not ready yet:');
            $this->components->bulletList($problems);

            return self::FAILURE;
        }

        $url = $this->askUrl();

        $database = $this->connectToDatabase();
        if ($database === null) {
            return self::FAILURE;
        }

        if ($reason = $this->whyDatabaseCannotBeUsed($installer)) {
            $this->components->error($reason.' Nothing was changed.');

            return self::FAILURE;
        }

        $this->writeEnvironment($url, $database);

        $this->newLine();
        $this->components->task('Creating the tables', fn () => $this->callSilent('migrate', ['--force' => true]) === 0);
        $this->components->task('Adding classes, subjects, grades and the chart of accounts', fn () => $this->seed());
        $this->components->task('Linking uploaded files', fn () => $this->linkStorage());
        $this->components->task('Building the screens', fn () => $this->buildAssets());

        $link = $url.'/install/'.$installer->issueToken();

        $this->newLine();
        $this->components->info('The server is ready. One step left, in a browser:');
        $this->line('  <options=bold>'.$link.'</>');
        $this->newLine();
        $this->line('  Open it to name the school and create the super admin. The link works once.');
        $this->line('  If it gets lost, run this command again for a new one.');
        $this->newLine();

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function serverProblems(): array
    {
        $problems = [];

        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            $problems[] = 'PHP 8.2 or newer is required. This is PHP '.PHP_VERSION.'.';
        }

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            if (! extension_loaded($extension)) {
                $problems[] = "The PHP extension \"{$extension}\" is off. Turn it on in ".(php_ini_loaded_file() ?: 'php.ini').'.';
            }
        }

        foreach ([storage_path(), base_path('bootstrap/cache')] as $folder) {
            if (! is_writable($folder)) {
                $problems[] = "The folder {$folder} must be writable.";
            }
        }

        if (! File::exists(public_path('build/manifest.json')) && Process::run('npm --version')->failed()) {
            $problems[] = 'Node.js is needed to build the screens. Install it, then run this command again.';
        }

        return $problems;
    }

    private function askUrl(): string
    {
        $url = $this->option('url') ?: $this->ask(
            'What address will school computers use to open EduTrust?',
            $this->guessUrl()
        );

        return rtrim(str_contains($url, '://') ? $url : 'http://'.$url, '/');
    }

    /** XAMPP and plain Apache serve a cloned folder at <server>/<folder>/public. */
    private function guessUrl(): string
    {
        $ip = gethostbyname(gethostname());
        $inWebRoot = in_array(basename(dirname(base_path())), ['htdocs', 'www', 'html'], true);

        return 'http://'.$ip.($inWebRoot ? '/'.basename(base_path()).'/public' : '');
    }

    /** @return array{host: string, port: string, database: string, username: string, password: string}|null */
    private function connectToDatabase(): ?array
    {
        do {
            $database = [
                'host' => $this->option('db-host') ?: $this->ask('Database server', env('DB_HOST', '127.0.0.1')),
                'port' => $this->option('db-port') ?: $this->ask('Database port', env('DB_PORT', '3306')),
                'database' => $this->option('db-database') ?: $this->ask('Database name (created if it does not exist)', env('DB_DATABASE', 'edutrust')),
                'username' => $this->option('db-username') ?: $this->ask('Database user', env('DB_USERNAME', 'root')),
                'password' => $this->option('db-password') ?? ((string) $this->secret('Database password (leave empty if there is none)')),
            ];

            $error = $this->openDatabase($database);

            if ($error === null) {
                config([
                    'database.default' => 'mysql',
                    'database.connections.mysql.host' => $database['host'],
                    'database.connections.mysql.port' => $database['port'],
                    'database.connections.mysql.database' => $database['database'],
                    'database.connections.mysql.username' => $database['username'],
                    'database.connections.mysql.password' => $database['password'],
                ]);
                DB::purge('mysql');
                DB::setDefaultConnection('mysql');

                return $database;
            }

            $this->components->error($error);
        } while ($this->input->isInteractive() && $this->confirm('Try different database details?', true));

        return null;
    }

    /** Connects, and creates the database if it is not there yet. */
    private function openDatabase(array $database): ?string
    {
        if (! preg_match('/^\w+$/', $database['database'])) {
            return 'A database name can only use letters, numbers and underscores.';
        }

        try {
            $pdo = new PDO(
                "mysql:host={$database['host']};port={$database['port']};charset=utf8mb4",
                $database['username'],
                $database['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            return 'Could not open the database: '.$e->getMessage();
        }

        return null;
    }

    private function whyDatabaseCannotBeUsed(SchoolInstaller $installer): ?string
    {
        if ($installer->isInstalled()) {
            return 'This database already has accounts, so it belongs to a running school.';
        }

        // Empty, or an install of ours that stopped part-way, can both continue.
        // getTables() with no schema lists every database on the server.
        if (Schema::getTables(DB::connection()->getDatabaseName()) !== [] && ! Schema::hasTable('migrations')) {
            return 'This database holds tables from something else. Choose an empty one.';
        }

        return null;
    }

    private function writeEnvironment(string $url, array $database): void
    {
        $path = base_path('.env');

        if (! File::exists($path)) {
            File::copy(base_path('.env.example'), $path);
        }

        $values = [
            'APP_NAME' => 'EduTrust',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $url,
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $database['host'],
            'DB_PORT' => $database['port'],
            'DB_DATABASE' => $database['database'],
            'DB_USERNAME' => $database['username'],
            'DB_PASSWORD' => $database['password'],
        ];

        // A key already set must stay: it opens everything encrypted with it.
        if (! preg_match('/^APP_KEY=.+$/m', File::get($path))) {
            $values['APP_KEY'] = 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));
            config(['app.key' => $values['APP_KEY']]);
        }

        EnvFile::set($path, $values);
        config(['app.url' => $url]);
    }

    private function seed(): bool
    {
        // Running again after a failure must not add the curriculum twice.
        if (Form::withoutGlobalScopes()->exists()) {
            return true;
        }

        return DB::transaction(fn () => $this->callSilent('db:seed', ['--force' => true]) === 0);
    }

    private function linkStorage(): bool
    {
        if (! file_exists(public_path('storage'))) {
            $this->callSilent('storage:link');
        }

        return file_exists(public_path('storage'));
    }

    private function buildAssets(): bool
    {
        if (File::exists(public_path('build/manifest.json'))) {
            return true;
        }

        $process = Process::path(base_path())->timeout(900);

        return $process->run('npm ci')->successful()
            && $process->run('npm run build')->successful();
    }
}
