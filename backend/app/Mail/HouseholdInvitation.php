<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HouseholdInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.invitation.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.household-invitation',
            with: [
                'inviterName' => self::escapeMarkdown($this->invitation->invitedBy->name),
                'householdName' => self::escapeMarkdown($this->invitation->household->name),
                'acceptUrl' => rtrim(config('app.frontend_url'), '/').'/invitations/'.$this->invitation->token.'/accept',
                'declineUrl' => rtrim(config('app.frontend_url'), '/').'/invitations/'.$this->invitation->token.'/decline',
                'expiresAt' => $this->invitation->expires_at->format('d. m. Y'),
            ],
        );
    }

    /**
     * The names are user-chosen and the template is Markdown: Blade's {{ }}
     * escapes HTML but not Markdown syntax, so a household named
     * '[Confirm here](https://evil.example)' would render as a real link in
     * an email that comes from us. Backslash-escaping makes it literal text.
     */
    private static function escapeMarkdown(string $text): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|<>~])/', '\\\\$1', $text);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
