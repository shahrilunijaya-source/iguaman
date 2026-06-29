<?php

namespace App\Support;

use App\Models\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Persists panel-lawyer registration/profile PDF documents to private storage and the
 * uploaded_files table, keyed by IC (kpBaru) + doc_type. On re-upload it replaces the
 * previous file + row for that doc_type, so a lawyer's profile holds one current copy
 * of each of the 18 document types. Used by registration and self-service profile update.
 */
class LawyerDocuments
{
    private const DISK = 'local';

    /**
     * @param  array<int,string>  $fields  doc-type field names present on the request
     */
    public static function store(Request $request, string $kpBaru, string $nama, array $fields): int
    {
        $safeKp = preg_replace('/[^A-Za-z0-9]/', '', $kpBaru);
        $saved = 0;

        foreach ($fields as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }

            // Replace any existing document of this type for this lawyer.
            $existing = UploadedFile::where('kpBaru', $kpBaru)->where('doc_type', $field)->get();
            foreach ($existing as $row) {
                if ($row->file_path) {
                    Storage::disk(self::DISK)->delete($row->file_path);
                }
                $row->delete();
            }

            $fileName = "{$safeKp}_{$field}.pdf";
            $path = $request->file($field)->storeAs("peguam/{$safeKp}", $fileName, self::DISK);

            UploadedFile::create([
                'nama' => $nama,
                'kpBaru' => $kpBaru,
                'doc_type' => $field,
                'file_name' => $fileName,
                'file_path' => $path,
                'file_type' => 'application/pdf',
            ]);
            $saved++;
        }

        return $saved;
    }
}
