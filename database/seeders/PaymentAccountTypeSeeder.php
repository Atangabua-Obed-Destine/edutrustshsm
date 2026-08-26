<?php

namespace Database\Seeders;

use App\Models\PaymentAccountType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentAccountTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['Bank Account', 'Cash Box', 'Mobile Money'];

        foreach ($types as $title) {
            PaymentAccountType::firstOrCreate(
                ['slug' => Str::slug($title)],
                ['title' => $title, 'status' => true]
            );
        }
    }
}
