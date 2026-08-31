<?php

namespace App\Console\Commands;

use App\Support\PrivateFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Moves already-uploaded private files off the public web root.
 *
 * New uploads go straight to the private disk, but everything uploaded before
 * that change is still sitting under public/storage where it can be fetched
 * with no session at all. Stored paths are relative and identical on both
 * disks, so nothing in the database needs rewriting.
 */
class SecurePrivateFiles extends Command
{
    protected $signature = 'files:secure {--dry-run : List what would move without moving it}';

    protected $description = 'Move private uploads (certificates, receipts, financial attachments) off the public disk';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $skipped = 0;

        foreach (PrivateFiles::PRIVATE_PREFIXES as $prefix) {
            if (! $public->exists($prefix)) {
                continue;
            }

            foreach ($public->allFiles($prefix) as $path) {
                if ($private->exists($path)) {
                    // Already secured on a previous run; the public copy is the
                    // leftover, so it still has to go.
                    if (! $dryRun) {
                        $public->delete($path);
                    }

                    $skipped++;

                    continue;
                }

                $this->line(($dryRun ? '[dry-run] ' : '').$path);

                if (! $dryRun) {
                    $private->put($path, $public->get($path));
                    $public->delete($path);
                }

                $moved++;
            }
        }

        $this->newLine();

        $this->info($dryRun
            ? "{$moved} file(s) would move, {$skipped} public leftover(s) would be removed."
            : "{$moved} file(s) moved to the private disk, {$skipped} public leftover(s) removed.");

        if ($moved === 0 && $skipped === 0) {
            $this->comment('Nothing to do — no private uploads are on the public disk.');
        }

        return self::SUCCESS;
    }
}
