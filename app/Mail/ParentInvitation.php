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

/**
 * The parent-portal invitation.
 *
 * Invites previously produced a link that an admin had to read off the screen
 * and relay by hand — the flow was shipped but not deliverable.
 */
class ParentInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Guardian $guardian,
        public string $link,
        public string $schoolName,
    ) {
    }

    public static function for(Guardian $guardian, string $link): self
    {
        return new self(
            $guardian,
            $link,
            SchoolSetting::current()?->school_name ?? config('app.name')
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your :school parent portal invitation', ['school' => $this->schoolName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.parent-invitation',
            with: [
                'name' => $this->guardian->guardian_name ?: __('Parent'),
                'expiresAt' => $this->guardian->invite_expires_at,
            ],
        );
    }
}
