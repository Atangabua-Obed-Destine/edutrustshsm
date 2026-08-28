<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The app is bilingual by requirement (Cameroon). Coverage was ~53%, so a
 * French session showed a heavily mixed interface. This keeps it from drifting
 * back: any new __('...') string must be translated before it lands.
 */
class TranslationCoverageTest extends TestCase
{
    /** @return array<int, string> every distinct __() literal in views and app code */
    private function translatableStrings(): array
    {
        $keys = [];

        foreach (['resources/views', 'app'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($dir))
            );

            foreach ($iterator as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = file_get_contents($file->getPathname());

                // Single-quoted __() arguments, honouring escaped quotes.
                preg_match_all('/__\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $source, $matches);

                foreach ($matches[1] as $raw) {
                    // Undo PHP single-quote escaping to recover the runtime key:
                    // a backslash before any character just yields that character.
                    $keys[preg_replace('/\\\\(.)/', '$1', $raw)] = true;
                }
            }
        }

        return array_keys($keys);
    }

    private function french(): array
    {
        return json_decode(
            file_get_contents(lang_path('fr.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function test_french_covers_every_translatable_string(): void
    {
        $fr = $this->french();

        $missing = array_values(array_filter(
            $this->translatableStrings(),
            fn (string $key) => ! array_key_exists($key, $fr)
        ));

        $this->assertSame([], $missing, sprintf(
            "%d string(s) have no French translation. Add them to lang/fr.json:\n  - %s",
            count($missing),
            implode("\n  - ", array_slice($missing, 0, 25))
        ));
    }

    public function test_no_translation_is_empty(): void
    {
        $empty = array_keys(array_filter(
            $this->french(),
            fn ($v) => trim((string) $v) === ''
        ));

        $this->assertSame([], $empty, 'these keys have an empty translation: '.implode(', ', $empty));
    }

    public function test_placeholders_survive_translation(): void
    {
        $broken = [];

        foreach ($this->french() as $english => $translated) {
            preg_match_all('/:[a-zA-Z_]+/', $english, $inSource);
            preg_match_all('/:[a-zA-Z_]+/', (string) $translated, $inTarget);

            sort($inSource[0]);
            sort($inTarget[0]);

            if ($inSource[0] !== $inTarget[0]) {
                $broken[] = $english;
            }
        }

        $this->assertSame([], $broken, "placeholders differ between English and French in:\n  - ".implode("\n  - ", array_slice($broken, 0, 20)));
    }
}
