<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** An e-mail written in a workflow's "Send an e-mail" step. */
class WorkflowMail extends Mailable
{
    public function __construct(public string $subjectLine, public string $text, public ?string $link = null, public ?string $name = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.workflow', with: ['subject' => $this->subjectLine, 'body' => $this->text, 'link' => $this->link, 'name' => $this->name]);
    }
}
