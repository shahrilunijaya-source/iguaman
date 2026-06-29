<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\UploadedFile;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Lampiran (case attachments). Files stored on the private `local` disk and
// streamed back through auth — legal documents are never web-public.
class LampiranController extends Controller
{
    private const DISK = 'local';
    private const DIR = 'lampiran';

    public function store(Request $request, Form $kes): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['nullable', 'string', 'max:255'],
            'fail' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $file = $request->file('fail');
        $path = $file->store(self::DIR, self::DISK);

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
        abort_unless(Storage::disk(self::DISK)->exists($lampiran->file_path), 404, 'Fail tidak dijumpai.');

        return Storage::disk(self::DISK)->download($lampiran->file_path, $lampiran->nama);
    }

    public function destroy(Form $kes, UploadedFile $lampiran): RedirectResponse
    {
        abort_unless((int) $lampiran->id_kes === (int) $kes->id, 404);

        Storage::disk(self::DISK)->delete($lampiran->file_path);
        $name = $lampiran->nama;
        $lampiran->delete();

        Audit::log('uploaded_files', $lampiran->id, Audit::DELETE, "Lampiran dipadam dari kes #{$kes->id}: {$name}");

        return redirect()->route('kes.show', $kes)->with('status', 'Lampiran dipadam.');
    }
}
