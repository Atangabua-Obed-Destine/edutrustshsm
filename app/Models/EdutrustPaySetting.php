<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * One branch's connection to its body's EdutrustPay console.
 *
 * Deliberately NOT BelongsToBranch: these rows are read from the console by the
 * scheduled report and flush commands, where BranchContext is inactive and the
 * global scope is a no-op. Branch is carried explicitly and every query names it.
 */
class EdutrustPaySetting extends Model
{
    use Auditable;

    protected $table = 'edutrustpay_settings';

    protected $fillable = [
        'branch_id', 'enabled', 'endpoint', 'institution_ref', 'key_id',
        'secret_ciphertext', 'last_tested_at', 'last_test_ok', 'last_test_message', 'updated_by',
    ];

    /**
     * Never serialise the secret — not into JSON, not into an exception page,
     * and not into the audit trail (Auditable strips it as well).
     */
    protected $hidden = ['secret_ciphertext'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_test_ok' => 'boolean',
            'last_tested_at' => 'datetime',
            // Encrypted on write, decrypted on read, using APP_KEY.
            'secret_ciphertext' => 'encrypted',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function secret(): ?string
    {
        return $this->secret_ciphertext ?: null;
    }

    public function hasSecret(): bool
    {
        return filled($this->secret_ciphertext);
    }

    /**
     * Enough to recognise a key without revealing it.
     */
    public function secretHint(): ?string
    {
        $secret = $this->secret();

        return $secret ? str_repeat('•', 8).substr($secret, -4) : null;
    }

    public function isConfigured(): bool
    {
        return filled($this->endpoint) && filled($this->institution_ref)
            && filled($this->key_id) && $this->hasSecret();
    }
}
