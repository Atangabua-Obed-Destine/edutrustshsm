<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Read a per-branch setting, or write several at once.
     *
     *   setting('receipt.footer')                  // read with null default
     *   setting('receipt.footer', 'Thank you')     // read with a default
     *   setting(['receipt.footer' => 'Thank you']) // write
     */
    function setting(string|array $key, mixed $default = null): mixed
    {
        if (is_array($key)) {
            Setting::putMany($key);

            return null;
        }

        return Setting::get($key, $default);
    }
}

if (! function_exists('private_file_url')) {
    /**
     * Link to a private upload through the authorised route.
     *
     * Never build these with asset('storage/...') — that is the public web root
     * these files were deliberately moved off.
     */
    function private_file_url(string $source, ?object $model, string $field): ?string
    {
        if (! $model || blank($model->{$field} ?? null)) {
            return null;
        }

        return route('admin.files.show', [
            'source' => $source,
            'id' => $model->getKey(),
            'field' => $field,
        ]);
    }
}
