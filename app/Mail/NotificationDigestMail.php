<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The morning summary: unread notifications since the last one. */
class NotificationDigestMail extends Mailable
{
    /** @param array<int, array> $items NotificationPresenter items */
    public function __construct(public User $user, public array $items, public int $total)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->total === 1 ? '1 thing you missed' : $this->total . ' things you missed');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification-digest', with: ['user' => $this->user, 'items' => $this->items, 'total' => $this->total]);
    }
}
