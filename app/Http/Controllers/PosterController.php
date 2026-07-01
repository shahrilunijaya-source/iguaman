<?php

namespace App\Http\Controllers;

use App\Http\Requests\PosterRequest;
use App\Models\Poster;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

// e-Poster management - announcements / notices (posters). Image stored on the
// private `local` disk; Poster model uses non-standard date columns ($timestamps=false).
class PosterController extends Controller
{
    private const DISK = 'local';

    private const DIR = 'poster';

    public function index(Request $request): View
    {
        $q = $request->input('q');

        $poster = Poster::query()
            ->when($q, fn ($w, $v) => $w->where('tajuk_poster', 'like', "%{$v}%"))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('poster.index', ['poster' => $poster, 'q' => $q]);
    }

    public function create(): View
    {
        return view('poster.form', ['poster' => new Poster, 'mode' => 'create']);
    }

    public function store(PosterRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['tajuk_poster', 'details_poster', 'status_poster']) + [
            'created_by' => $request->user()->name,
            'created_at' => now(),
        ];

        if ($request->hasFile('imej')) {
            $data['image_path'] = $request->file('imej')->store(self::DIR, self::DISK);
        }

        $poster = Poster::create($data);

        Audit::log('posters', $poster->id, Audit::INSERT, "Poster ditambah: {$poster->tajuk_poster}");

        return redirect()->route('poster.index')->with('status', 'Poster ditambah.');
    }

    public function edit(Poster $poster): View
    {
        return view('poster.form', ['poster' => $poster, 'mode' => 'edit']);
    }

    public function update(PosterRequest $request, Poster $poster): RedirectResponse
    {
        $data = $request->safe()->only(['tajuk_poster', 'details_poster', 'status_poster']) + [
            'modified_by' => $request->user()->name,
            'modified_at' => now(),
        ];

        if ($request->hasFile('imej')) {
            if ($poster->image_path) {
                Storage::disk(self::DISK)->delete($poster->image_path);
            }
            $data['image_path'] = $request->file('imej')->store(self::DIR, self::DISK);
        }

        $poster->update($data);

        Audit::log('posters', $poster->id, Audit::UPDATE, "Poster dikemaskini: {$poster->tajuk_poster}");

        return redirect()->route('poster.index')->with('status', 'Poster dikemaskini.');
    }

    public function destroy(Poster $poster): RedirectResponse
    {
        $tajuk = $poster->tajuk_poster;
        $id = $poster->id;

        if ($poster->image_path) {
            Storage::disk(self::DISK)->delete($poster->image_path);
        }

        $poster->delete();

        Audit::log('posters', $id, Audit::DELETE, "Poster dipadam: {$tajuk}");

        return redirect()->route('poster.index')->with('status', 'Poster dipadam.');
    }
}
