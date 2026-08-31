<?php

namespace App\Mail;

use App\Models\Guardian;
use App\Models\SchoolSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * One reminder per guardian, covering every child.
 *
 * A parent with three children at the school should receive one letter listing
 * three balances, not three letters — the fastest way to have reminders
 * ignored is to send too many of them.
 */
class FeeReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, object>  $lines  one row per outstanding fee
     */
    public function __construct(
        public Guardian $guardian,
        public Collection $lines,
        public float $total,
        public string $schoolName,
        public string $currency,
    ) {
    }

    /** @param Collection<int, object> $lines */
    public static function for(Guardian $guardian, Collection $lines): self
    {
        $settings = SchoolSetting::current();

        return new self(
            $guardian,
            $lines,
            (float) $lines->sum('balance'),
            $settings?->school_name ?? config('app.name'),
            $settings?->currency ?? 'XAF',
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('School fees outstanding at :school', ['school' => $this->schoolName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.fee-reminder',
            with: [
                'name' => $this->guardian->display_name ?? __('Parent / Guardian'),
            ],
        );
    }
}
