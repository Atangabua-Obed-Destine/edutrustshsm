<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Every view compiles to PHP that parses.
 *
 * Blade has two forms for inline PHP and they interact badly in ways that
 * produce no error until someone opens the page. This has now bitten three
 * times in this codebase:
 *
 *   - an expression directive and a later block form pair with each other,
 *     and everything between them is emitted verbatim as one raw block;
 *   - a braced expression inside the expression form compiles to `<?php(`,
 *     which is not a valid opening tag at all.
 *
 * Both leave a view that looks fine in the editor and fails in the browser.
 * Compiling every view and parsing the result costs a second and catches
 * them at the point they are written.
 */
class BladeCompilesTest extends TestCase
{
    public function test_every_blade_view_compiles_to_valid_php(): void
    {
        $broken = [];

        foreach ($this->views() as $path) {
            $compiled = Blade::compileString(file_get_contents($path));
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

            // `<?php(` — PHP needs whitespace after the opening tag, so this
            // is emitted as text and the rest of the file goes with it.
            if (str_contains($compiled, '<?php(')) {
                $broken[] = $relative.' — compiles to an invalid opening tag';

                continue;
            }

            if ($error = $this->parseError($compiled)) {
                $broken[] = $relative.' — '.$error;
            }
        }

        $this->assertSame([], $broken, "Views that do not compile:\n  - ".implode("\n  - ", $broken));
    }

    public function test_no_view_leaves_a_directive_uncompiled(): void
    {
        $leaky = [];

        foreach ($this->views() as $path) {
            $compiled = Blade::compileString(file_get_contents($path));
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

            // A surviving directive means a raw block swallowed it.
            if (preg_match('/(?<!@)@(php|endphp|foreach|endforeach|if|endif)\b/', $compiled, $m)) {
                $leaky[] = $relative.' — @'.$m[1].' survived compilation';
            }
        }

        $this->assertSame([], $leaky, "Views with swallowed directives:\n  - ".implode("\n  - ", $leaky));
    }

    /** @return array<int, string> */
    private function views(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $file) {
            if (! $file->isDir() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /** The parse error in a compiled template, if there is one. */
    private function parseError(string $compiled): ?string
    {
        // Linted as-is. An earlier version wrapped it in a function for
        // isolation, which made every view carrying a `use` import look
        // broken — `php -l` parses without executing, so there is nothing
        // to isolate from.
        $file = tempnam(sys_get_temp_dir(), 'blade');
        file_put_contents($file, $compiled);

        $output = [];
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $status);

        unlink($file);

        return $status === 0 ? null : trim(preg_replace('/ in .*$/m', '', implode(' ', $output)));
    }
}
