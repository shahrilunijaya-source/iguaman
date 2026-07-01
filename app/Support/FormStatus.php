<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CODE-04 — forms.status text values (the litigation-case decision status), previously
 * bare string literals scattered across KeputusanController and the report layer. A typo
 * in a `where('status', ...)` silently matched nothing; naming them here prevents that.
 * (Distinct from StatusAgihan, which is the numeric assignment-state machine.)
 */
class FormStatus
{
    public const DITERIMA = 'Diterima';

    public const DITOLAK = 'Ditolak';

    public const FAIL_TUTUP = 'Fail Tutup';

    /** A case in one of these has a final outcome and must not be re-decided (PROC-12). */
    public const TERMINAL = [self::DITERIMA, self::DITOLAK, self::FAIL_TUTUP];
}
