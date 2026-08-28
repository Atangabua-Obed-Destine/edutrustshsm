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
