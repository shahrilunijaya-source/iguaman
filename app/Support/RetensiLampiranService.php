<?php

namespace App\Support;

use App\Models\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * W6 — case-attachment retention. Legal documents are kept for a statutory minimum of
 * 7 years; past that they may be disposed of. This service identifies expired attachments
 * and (only when explicitly told to) purges them from disk + the registry with an audit
 * trail. The default mode is report-only so the destructive step is always deliberate.
 */
class RetensiLampiranService
{
    public const RETENTION_YEARS = 7;

    /** Legacy disk older attachments may still live on (pre-W6 repository switch). */
    private const LEGACY_DISK = 'local';

    /** Attachments uploaded before the retention cutoff. */
    public function expired(): Collection
    {
        $cutoff = now()->subYears(self::RETENTION_YEARS)->toDateString();

        return UploadedFile::query()
            ->whereNotNull('uploaded_at')
            ->whereDate('uploaded_at', '<', $cutoff)
            ->get();
    }

    /**
     * Walk the expired set. When $purge is false (default) nothing is deleted — the callback
     * still fires so the caller can report. Returns [count, purged].
     *
     * @return array{count:int, purged:int}
     */
    public function run(bool $purge = false, ?callable $onEach = null): array
    {
        $expired = $this->expired();
        $purged = 0;

        foreach ($expired as $file) {
            if ($purge) {
                $this->purge($file);
                $purged++;
            }
            if ($onEach) {
                $onEach($file, $purge);
            }
        }

        return ['count' => $expired->count(), 'purged' => $purged];
    }

    /** Delete one attachment from whichever disk holds it, then the registry row. */
    private function purge(UploadedFile $file): void
    {
        foreach ([config('filesystems.lampiran_disk', 'repositori'), self::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($file->file_path)) {
                Storage::disk($disk)->delete($file->file_path);
            }
        }

        $id = $file->id;
        $name = $file->nama;
        $file->delete();

        Audit::log('uploaded_files', $id, Audit::DELETE,
            'Lampiran dilupuskan (retensi '.self::RETENTION_YEARS.' tahun): '.$name, 'Sistem (Retensi)');
    }
}
