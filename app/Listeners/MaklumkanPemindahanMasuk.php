<?php

namespace App\Listeners;

use App\Events\PemindahanCawanganDimulakan;
use App\Mail\PemindahanMasukMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * W21 - when a transfer is initiated, e-mail the destination branch supervisors
 * (pengarah/koordinator) that an incoming record awaits acceptance. Queued, so the
 * notification never blocks or rolls back the transfer transaction; best-effort
 * (a mail failure is reported, never thrown).
 */
class MaklumkanPemindahanMasuk implements ShouldQueue
{
    public function handle(PemindahanCawanganDimulakan $event): void
    {
        $pindah = $event->pindah;

        $supervisors = User::whereIn('role', [User::ROLE_PENGARAH, User::ROLE_KOORDINATOR])
            ->where('is_active', true)
            ->where('cawangan', $pindah->cawangan_tujuan)
            ->get();

        foreach ($supervisors as $u) {
            if (! filled($u->email) || ! str_contains((string) $u->email, '@')) {
                continue;
            }

            try {
                Mail::to($u->email)->send(new PemindahanMasukMail($pindah));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
