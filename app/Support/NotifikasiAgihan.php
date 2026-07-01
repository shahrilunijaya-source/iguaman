<?php

namespace App\Support;

use App\Mail\AgihanTransisiMail;
use App\Models\Form;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Transition notifications for the 3-tier assignment chain (EPIC G - legacy
 * agihanbaru/* + formAgihanBaruKP.php PHPMailer blocks). Resolves recipients by
 * role (HQ-wide) or branch (Ketua Cawangan / Pengarah Negeri ≈ branch
 * pengarah/koordinator) and sends the parameterised AgihanTransisiMail.
 *
 * Every send is best-effort: a mail failure is reported, never thrown, so it
 * cannot roll back the transition that triggered it. Recipient-supplied text is
 * escaped before being placed into the HTML body.
 */
class NotifikasiAgihan
{
    /** Pengarah accepted a new case → tell the chosen PPUU to pick a lawyer (0→8). */
    public function pengarahTerima(Form $kes, int $idPPUU): void
    {
        $ppuu = User::where('id', $idPPUU)->where('is_active', true)->get();

        $this->send($kes, $ppuu, 'Pemakluman Tugasan bagi Agihan Baru', [
            'Satu kes baharu telah <strong>diagih</strong> kepada tuan/puan untuk pemilihan Peguam Panel.',
            'Sila lengkapkan pemilihan Peguam Panel dalam tempoh <strong>3 hari</strong>.',
        ]);
    }

    /** Pengarah rejected a new case → tell the branch (0→9). */
    public function pengarahTolak(Form $kes, string $sebab): void
    {
        $this->send($kes, $this->branchSupervisors($kes->cawangan), 'Pemakluman Status Permohonan Khidmat Peguam Panel JBG', [
            'Permohonan khidmat Peguam Panel bagi kes ini telah <strong>ditolak</strong> oleh Pengarah.',
            'Sebab: '.e($sebab),
            'Sila kemas kini sistem dalam tempoh <strong>3 hari</strong>.',
        ]);
    }

    /** PPUU picked a lawyer → ask the Pengarah to endorse (8→10). */
    public function ppuuPilih(Form $kes, string $namaPP): void
    {
        $this->send($kes, $this->role(User::ROLE_PENGARAH), 'Pemakluman Status Tugasan bagi Agihan Baru', [
            'PPUU telah memilih Peguam Panel <strong>'.e($namaPP).'</strong> bagi kes ini.',
            'Mohon semakan dan keputusan sokongan tuan/puan (Disokong / Tidak Disokong).',
        ]);
    }

    /** Ketua Pengarah rejected the pick → tell the branch + Pengarah (13→15). */
    public function kpTolak(Form $kes, string $ulasan): void
    {
        $recipients = $this->branchSupervisors($kes->cawangan)
            ->merge($this->role(User::ROLE_PENGARAH))
            ->unique('id');

        $this->send($kes, $recipients, 'PEMAKLUMAN STATUS PERMOHONAN KHIDMAT PEGUAM PANEL JBG', [
            'Permohonan khidmat Peguam Panel bagi kes ini <strong>tidak diluluskan</strong> oleh Ketua Pengarah.',
            'Sebab: '.e($ulasan),
            'Kes dikembalikan kepada PPUU untuk pemilihan semula.',
        ]);
    }

    /** Active users of a role (HQ-wide). */
    private function role(string $role): Collection
    {
        return User::where('role', $role)->where('is_active', true)->get();
    }

    /** Branch heads (Ketua Cawangan / Pengarah Negeri) for a case branch. */
    private function branchSupervisors(?string $cawangan): Collection
    {
        if (! filled($cawangan)) {
            return collect();
        }

        return User::whereIn('role', [User::ROLE_PENGARAH, User::ROLE_KOORDINATOR])
            ->where('is_active', true)
            ->where('cawangan', $cawangan)
            ->get();
    }

    /** Best-effort fan-out to valid e-mail addresses (never throws). */
    private function send(Form $kes, Collection $users, string $tajuk, array $mesej): void
    {
        foreach ($users as $u) {
            if (! filled($u->email) || ! str_contains((string) $u->email, '@')) {
                continue;
            }
            try {
                Mail::to($u->email)->send(new AgihanTransisiMail($kes, $tajuk, $mesej));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
