<?php

namespace App\Events;

use App\Models\PemindahanCawangan;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * W21 - fired when a branch transfer (case or KN) is initiated, so listeners can
 * notify the destination branch in real time. Decouples the notification from the
 * transfer transaction (the listener is queued).
 */
class PemindahanCawanganDimulakan
{
    use Dispatchable, SerializesModels;

    public function __construct(public PemindahanCawangan $pindah) {}
}
