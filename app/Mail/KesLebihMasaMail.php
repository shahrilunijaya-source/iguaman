<?php

namespace App\Mail;

use App\Models\Form;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Notify the branch Pengarah that an unanswered offer was auto re-assigned (Lebih Masa). */
class KesLebihMasaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Form $kes) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'PEMAKLUMAN KEPERLUAN AGIHAN SEMULA - '.($this->kes->no_fail ?: '#'.$this->kes->id),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.kes-lebih-masa');
    }
}
