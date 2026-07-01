<?php

namespace App\Mail;

use App\Models\Form;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic 3-tier assignment-chain notification (EPIC G - legacy agihanbaru/*
 * + agihansemula/* PHPMailer blocks). One parameterised mailable drives every
 * transition email: $tajuk is the subject, $mesej the body paragraphs.
 */
class AgihanTransisiMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  array<int,string>  $mesej */
    public function __construct(public Form $kes, public string $tajuk, public array $mesej) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->tajuk.' - '.($this->kes->no_fail ?: '#'.$this->kes->id),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.agihan-transisi');
    }
}
