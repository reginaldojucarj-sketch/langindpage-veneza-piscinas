<?php

namespace App\Http\Controllers;

use App\Services\MediaLibrary;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicMediaController extends Controller
{
    public function __invoke(string $media, MediaLibrary $library): StreamedResponse
    {
        [$stream, $mime] = $library->publicDerivative($media);

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="imagem-normalizada"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox; img-src 'self' data:; style-src 'none'; script-src 'none'",
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'Cache-Control' => 'no-store',
        ]);
    }
}
