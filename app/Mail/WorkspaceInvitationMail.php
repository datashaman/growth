<?php

namespace App\Mail;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public WorkspaceInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You\'re invited to :workspace', [
                'workspace' => $this->invitation->workspace->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.workspace-invitation',
            with: [
                'invitation' => $this->invitation,
                'workspace' => $this->invitation->workspace,
                'inviter' => $this->invitation->invitedBy,
                'acceptUrl' => route('invitations.show', ['token' => $this->invitation->token]),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
