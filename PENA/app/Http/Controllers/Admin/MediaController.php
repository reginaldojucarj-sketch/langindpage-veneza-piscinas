<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ContentUnavailable;
use App\Http\Controllers\Controller;
use App\Services\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function __construct(private readonly MediaLibrary $media) {}

    public function index(): View
    {
        return view('admin.media', $this->media->listing());
    }

    public function store(Request $request): RedirectResponse|Response
    {
        $data = $request->validate([
            'file' => ['required', 'file'],
            'alt_text' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->media->upload($request->user(), $data['file'], $data['alt_text']);
        } catch (ContentUnavailable) {
            $correlationId = (string) Str::uuid();

            return response()->view('errors.503', ['exception' => null, 'correlationId' => $correlationId], 503)
                ->header('X-Correlation-ID', $correlationId);
        }

        return redirect()->route('admin.media.index')->with('status', 'Imagem enviada e normalizada com segurança.');
    }

    public function updateAltText(Request $request, string $media): RedirectResponse|Response
    {
        try {
            $this->media->updateAltText($request->user(), $media, $request->all());
        } catch (ContentUnavailable) {
            $correlationId = (string) Str::uuid();

            return response()->view('errors.503', ['exception' => null, 'correlationId' => $correlationId], 503)
                ->header('X-Correlation-ID', $correlationId);
        }

        return redirect()->route('admin.media.index')->with('status', 'Texto alternativo atualizado.');
    }

    public function deactivate(Request $request, string $media): RedirectResponse|Response
    {
        try {
            $this->media->deactivate($request->user(), $media);
        } catch (ContentUnavailable) {
            $correlationId = (string) Str::uuid();

            return response()->view('errors.503', ['exception' => null, 'correlationId' => $correlationId], 503)
                ->header('X-Correlation-ID', $correlationId);
        }

        return redirect()->route('admin.media.index')->with('status', 'Mídia desativada. O arquivo privado foi preservado.');
    }

    public function activate(Request $request, string $media): RedirectResponse|Response
    {
        try {
            $this->media->activate($request->user(), $media);
        } catch (ContentUnavailable) {
            $correlationId = (string) Str::uuid();

            return response()->view('errors.503', ['exception' => null, 'correlationId' => $correlationId], 503)
                ->header('X-Correlation-ID', $correlationId);
        }

        return redirect()->route('admin.media.index')->with('status', 'Mídia reativada.');
    }
}
