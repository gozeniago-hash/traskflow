<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation)
    {
    }

    public function envelope(): Envelope
    {
        $workspace = $this->invitation->workspace->name;

        return new Envelope(
            subject: $this->invitation->role === 'client'
                ? "You've been invited to the {$workspace} client portal"
                : "Join {$workspace} on TaskFlow",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.invitation');
    }
}
