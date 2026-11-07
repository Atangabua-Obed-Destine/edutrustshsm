<?php

namespace App\Support;

use Illuminate\Translation\FileLoader;

/**
 * The usual translation loader, with one more layer on top: a word list a
 * school level swaps in ("pupil" for "student", "head teacher" for
 * "principal"). Laravel's own addJsonPath puts extra files *under* the
 * main lang/{locale}.json, so in French the main file always won.
 */
class LevelFileLoader extends FileLoader
{
    /** @var list<string> */
    protected array $overridePaths = [];

    public function addOverridePath(string $path): void
    {
        if (! in_array($path, $this->overridePaths, true)) {
            $this->overridePaths[] = $path;
        }
    }

    protected function loadJsonPaths($locale)
    {
        $lines = parent::loadJsonPaths($locale);

        foreach ($this->overridePaths as $path) {
            if ($this->files->exists($full = "{$path}/{$locale}.json")) {
                $lines = array_merge($lines, json_decode($this->files->get($full), true) ?: []);
            }
        }

        return $lines;
    }
}
