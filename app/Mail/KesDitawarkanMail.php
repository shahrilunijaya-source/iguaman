<?php

namespace App\Mail;

use App\Models\Form;
use App\Models\PeguamPanel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Notify a panel lawyer that a case has been offered to them. */
class KesDitawarkanMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Form $kes, public PeguamPanel $peguam)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tawaran Kes Bantuan Guaman — '.($this->kes->no_fail ?: '#'.$this->kes->id),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.kes-ditawarkan');
    }
}
