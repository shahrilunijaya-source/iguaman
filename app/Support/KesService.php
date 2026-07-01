<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Form;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ARCH-02 - litigation-case core, extracted from KesController so the create/update
 * rules live in a testable service like every sibling domain (KhidmatProses, LejarTuntutan,
 * Pengantaraan …) rather than inline in the transport layer.
 */
class KesService
{
    public function __construct(private NoFailGenerator $noFail) {}

    /**
     * Register a new litigation case. Stamps the legacy audit columns, then generates
     * the file number when the officer left it blank - both in one transaction so a
     * case never persists without its running no_fail (legacy generated it at intake).
     */
    public function cipta(array $data, User $actor): Form
    {
        $data['created_at'] = now();
        $data['tarikh_daftar'] ??= now()->toDateString();
        $data['didaftarkan_oleh'] = $actor->name;
        $data['diterima'] ??= ''; // NOT NULL in legacy schema

        return DB::transaction(function () use ($data) {
            $kes = Form::create($data);

            if (blank($kes->no_fail)) {
                $kes->update(['no_fail' => $this->noFail->generate($kes)]);
            }

            return $kes;
        });
    }

    /** Update a case, stamping the KP-kemaskini timestamp (legacy tarikh_KPKemaskini). */
    public function kemaskini(Form $kes, array $data): Form
    {
        $kes->update($data + ['tarikh_KPKemaskini' => now()]);

        return $kes;
    }
}
