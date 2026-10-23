<?php

namespace App\Mail;

use App\Models\Checkin\Guest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A registered guest's ticket: the QR to show at the door. */
class CheckinTicketMail extends Mailable
{
    public function __construct(public Guest $guest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your ticket: ' . $this->guest->event->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.checkin-ticket', with: ['guest' => $this->guest, 'event' => $this->guest->event]);
    }
}
