<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Academy e-mails to applicants and students (admitted, not offered, a link
 * to pay). One simple layout: a heading, a few paragraphs, an optional list
 * of rows, and a button.
 *
 * @param  array<int, string>  $paragraphs
 * @param  array<int, array{0:string, 1:string}>  $rows  label => value pairs shown as a table
 */
class AcademyMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $greeting,
        public array $paragraphs,
        public array $rows = [],
        public ?string $buttonText = null,
        public ?string $buttonUrl = null,
        public array $after = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.academy');
    }
}
