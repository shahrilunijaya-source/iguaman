<?php

namespace App\Mail;

use App\Models\PemindahanCawangan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** W21 — notify a destination branch that an incoming record transfer awaits acceptance. */
class PemindahanMasukMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PemindahanCawangan $pindah) {}

    public function envelope(): Envelope
    {
        $jenis = $this->pindah->jenis_rekod === PemindahanCawangan::JENIS_KES ? 'Kes' : 'Khidmat Nasihat';

        return new Envelope(
            subject: "Pemindahan {$jenis} Masuk — {$this->pindah->cawangan_asal} → {$this->pindah->cawangan_tujuan}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.pemindahan-masuk');
    }
}
