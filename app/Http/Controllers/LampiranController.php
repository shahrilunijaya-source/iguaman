<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\UploadedFile;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Lampiran (case attachments). Files stored on the private repository disk and
// streamed back through auth — legal documents are never web-public.
// W6 — 25 MB cap; new files land on the dedicated `repositori` disk, reads fall
// back to the legacy `local` disk so pre-switch attachments stay reachable.
class LampiranController extends Controller
{
    /** Legacy disk attachments were stored on before the W6 repository switch. */
    private const LEGACY_DISK = 'local';
    private const DIR = 'lampiran';

    public function store(Request $request, Form $kes): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['nullable', 'string', 'max:255'],
            'fail' => ['required', 'file', 'max:25600', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $file = $request->file('fail');
        $path = $file->store(self::DIR, $this->writeDisk());

        $row = UploadedFile::create([
            'nama' => $data['nama'] ?: $file->getClientOriginalName(),
            'file_name' => basename($path),
            'file_path' => $path,
            'file_type' => strtolower($file->getClientOriginalExtension() ?: $file->extension()),
            'id_kes' => $kes->id,
            'uploaded_at' => now(),
        ]);

        Audit::log('uploaded_files', $row->id, Audit::INSERT, "Lampiran dimuat naik untuk kes #{$kes->id}: {$row->nama}");

        return redirect()->route('kes.show', $kes)->with('status', 'Lampiran dimuat naik.');
    }

    public function download(UploadedFile $lampiran): StreamedResponse
    {
        $disk = $this->diskFor($lampiran->file_path);
        abort_if($disk === null, 404, 'Fail tidak dijumpai.');

        return Storage::disk($disk)->download($lampiran->file_path, $lampiran->nama);
    }

    public function destroy(Form $kes, UploadedFile $lampiran): RedirectResponse
    {
        abort_unless((int) $lampiran->id_kes === (int) $kes->id, 404);

        if ($disk = $this->diskFor($lampiran->file_path)) {
            Storage::disk($disk)->delete($lampiran->file_path);
        }
        $name = $lampiran->nama;
        $lampiran->delete();

        Audit::log('uploaded_files', $lampiran->id, Audit::DELETE, "Lampiran dipadam dari kes #{$kes->id}: {$name}");

        return redirect()->route('kes.show', $kes)->with('status', 'Lampiran dipadam.');
    }

    /** Active write disk for new attachments (config-driven, defaults to repositori). */
    private function writeDisk(): string
    {
        return config('filesystems.lampiran_disk', 'repositori');
    }

    /** Resolve which configured disk actually holds a file: repository first, then legacy. */
    private function diskFor(string $path): ?string
    {
        foreach ([$this->writeDisk(), self::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }
}
