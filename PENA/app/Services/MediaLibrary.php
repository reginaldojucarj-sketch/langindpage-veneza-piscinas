<?php

namespace App\Services;

use App\Exceptions\ContentUnavailable;
use App\Models\AdminUser;
use App\Models\EditorialMedia;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;

class MediaLibrary
{
    public function __construct(
        private readonly EditorialActor $actors,
        private readonly ContentAudit $audit,
        private readonly ImageNormalizer $normalizer,
        private readonly MediaFileStorage $files,
        private readonly MediaUsage $usage,
    ) {}

    public function listing(): array
    {
        try {
            $uploaded = DB::table('pena_media as media')
                ->leftJoin('pena_admin_users as users', 'users.id', '=', 'media.uploaded_by')
                ->select([
                    'media.id', 'media.original_name as name', 'media.public_mime as mime', 'media.original_bytes as bytes',
                    'media.width', 'media.height', 'media.alt_text', 'media.is_active', 'media.version', 'media.created_at',
                    'users.name as uploaded_by_name',
                ])->orderByDesc('media.created_at')->limit(300)->get()
                ->map(function ($media) {
                    $item = (array) $media;
                    $item['source'] = 'upload';
                    $item['preview_url'] = route('media.public', ['media' => $item['id']]);
                    $item['selection_url'] = $item['preview_url'];
                    $item['selectable'] = (bool) $item['is_active'];
                    $item['public'] = true;

                    return $item;
                })->all();

            return ['uploaded' => $uploaded, 'legacy' => $this->legacyItems()];
        } catch (QueryException $error) {
            Log::error('Falha ao listar a biblioteca editorial de mídia.', ['sqlstate' => $error->errorInfo[0] ?? 'unknown']);

            throw new ContentUnavailable;
        }
    }

    /** @return list<array{id: string, name: string, width: int, height: int, is_active: bool}> */
    public function authorPhotoOptions(?string $currentMediaId): array
    {
        try {
            $options = DB::table('pena_media')
                ->select(['id', 'original_name as name', 'width', 'height', 'is_active'])
                ->where('is_active', true)
                ->orderByDesc('created_at')->limit(300)->get();

            if ($currentMediaId !== null && ! $options->contains(fn ($item) => $item->id === $currentMediaId)) {
                $current = DB::table('pena_media')
                    ->select(['id', 'original_name as name', 'width', 'height', 'is_active'])
                    ->where('id', $currentMediaId)->first();
                if ($current) {
                    $options->push($current);
                }
            }

            return $options->map(fn ($item) => [
                'id' => (string) $item->id,
                'name' => (string) $item->name,
                'width' => (int) $item->width,
                'height' => (int) $item->height,
                'is_active' => (bool) $item->is_active,
            ])->all();
        } catch (QueryException $error) {
            Log::error('Falha ao consultar imagens para autoria.', ['sqlstate' => $error->errorInfo[0] ?? 'unknown']);

            throw new ContentUnavailable;
        }
    }

    public function upload(AdminUser $actor, UploadedFile $file, string $altText): EditorialMedia
    {
        $altText = trim($altText);
        validator(['alt_text' => $altText], ['alt_text' => ['required', 'string', 'max:255']])->validate();
        $image = $this->normalizer->process($file);
        $id = (string) Str::uuid();
        $filename = $id.'.'.$image->extension;
        $originalsDisk = config('pena.originals_disk');
        $derivativesDisk = config('pena.derivatives_disk');
        $originalPath = $filename;
        $derivativePath = $filename;
        $originalStored = false;
        $derivativeStored = false;

        $this->actors->ensureStorage();
        $stream = @fopen($file->getRealPath(), 'rb');
        if (! is_resource($stream)) {
            throw new ContentUnavailable;
        }

        try {
            // Mark before calling the adapter: a failing write may have left a partial private file.
            $originalStored = true;
            if (! $this->files->putStream($originalsDisk, $originalPath, $stream)) {
                throw new ContentUnavailable;
            }

            $derivativeStored = true;
            if (! $this->files->put($derivativesDisk, $derivativePath, $image->derivative)) {
                throw new ContentUnavailable;
            }
            fclose($stream);
            $stream = null;

            return DB::transaction(function () use ($actor, $id, $file, $altText, $image, $originalPath, $derivativePath) {
                $fresh = $this->actors->fresh($actor);
                $media = new EditorialMedia;
                $media->forceFill([
                    'id' => $id,
                    'sha256' => $image->sha256,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'source_mime' => $image->sourceMime,
                    'public_mime' => $image->publicMime,
                    'original_bytes' => $image->originalBytes,
                    'width' => $image->width,
                    'height' => $image->height,
                    'alt_text' => $altText,
                    'original_path' => $originalPath,
                    'derivative_path' => $derivativePath,
                    'is_active' => true,
                    'uploaded_by' => $fresh->id,
                ])->save();

                $this->audit->record($fresh->id, 'media.uploaded', 'media', $id, [
                    'mime' => $image->publicMime, 'bytes' => $image->originalBytes,
                    'width' => $image->width, 'height' => $image->height,
                ]);

                return $media;
            }, 3);
        } catch (ValidationException $error) {
            $this->cleanup($originalsDisk, $originalPath, $originalStored, $derivativesDisk, $derivativePath, $derivativeStored);
            throw $error;
        } catch (QueryException $error) {
            $this->cleanup($originalsDisk, $originalPath, $originalStored, $derivativesDisk, $derivativePath, $derivativeStored);
            if (in_array((string) ($error->errorInfo[1] ?? ''), ['1062', '19'], true)) {
                throw ValidationException::withMessages(['file' => 'Esta imagem já existe na biblioteca.']);
            }
            Log::error('Falha ao registrar os metadados de uma mídia.', ['sqlstate' => $error->errorInfo[0] ?? 'unknown']);

            throw new ContentUnavailable;
        } catch (ContentUnavailable $error) {
            $this->cleanup($originalsDisk, $originalPath, $originalStored, $derivativesDisk, $derivativePath, $derivativeStored);
            Log::error('Falha ao gravar arquivo da biblioteca de mídia.');

            throw $error;
        } catch (\Throwable $error) {
            $this->cleanup($originalsDisk, $originalPath, $originalStored, $derivativesDisk, $derivativePath, $derivativeStored);
            Log::error('Falha inesperada na biblioteca de mídia.', ['exception_type' => $error::class]);

            throw new ContentUnavailable;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function publicDerivative(string $id): array
    {
        try {
            $media = EditorialMedia::query()->where('id', $id)->where('is_active', true)->first();
            abort_unless($media, 404);
            $stream = $this->files->readStream(config('pena.derivatives_disk'), $media->derivative_path);
            if (! is_resource($stream)) {
                throw new ContentUnavailable;
            }
        } catch (QueryException $error) {
            Log::error('Falha ao consultar metadados de mídia pública.', ['sqlstate' => $error->errorInfo[0] ?? 'unknown']);

            throw new ContentUnavailable;
        } catch (FilesystemException $error) {
            Log::error('Falha ao ler derivada de mídia.', ['exception_type' => $error::class]);

            throw new ContentUnavailable;
        }

        return [$stream, $media->public_mime];
    }

    public function deactivate(AdminUser $actor, string $id): void
    {
        $this->actors->ensureStorage();
        try {
            DB::transaction(function () use ($actor, $id) {
                $fresh = $this->actors->fresh($actor);
                $media = EditorialMedia::query()->lockForUpdate()->findOrFail($id);
                if ($this->usage->isInUse($id)) {
                    throw ValidationException::withMessages(['media' => 'Esta mídia está vinculada a um artigo ou autor e não pode ser desativada.']);
                }
                if (! $media->is_active) {
                    return;
                }
                $media->is_active = false;
                $media->save();
                $this->audit->record($fresh->id, 'media.deactivated', 'media', $id);
            }, 3);
        } catch (ValidationException $error) {
            throw $error;
        } catch (QueryException $error) {
            Log::error('Falha ao desativar mídia.', ['sqlstate' => $error->errorInfo[0] ?? 'unknown']);

            throw new ContentUnavailable;
        }
    }

    public function updateAltText(AdminUser $actor, string $id, array $input): void
    {
        $data = validator($input, [
            'alt_text' => ['required', 'string', 'max:255'],
            'expected_version' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ])->validate();
        $data['alt_text'] = trim($data['alt_text']);
        validator($data, ['alt_text' => ['required', 'string', 'max:255']])->validate();
        $this->actors->ensureStorage();

        try {
            DB::transaction(function () use ($actor, $id, $data): void {
                $fresh = $this->actors->fresh($actor);
                $media = EditorialMedia::query()->lockForUpdate()->findOrFail($id);
                if ((int) $media->version !== (int) $data['expected_version']) {
                    throw ValidationException::withMessages(['expected_version' => 'Esta imagem foi alterada desde que você abriu o formulário. Recarregue a página.']);
                }
                if ($media->alt_text === $data['alt_text']) {
                    return;
                }

                $media->alt_text = $data['alt_text'];
                $media->version++;
                $media->save();
                $this->audit->record($fresh->id, 'media.alt_text_updated', 'media', $id, ['fields' => ['alt_text']]);
            }, 3);
        } catch (ValidationException $error) {
            throw $error;
        } catch (QueryException $error) {
            Log::error('Falha ao atualizar texto alternativo da mídia.', ['sqlstate' => $error->errorInfo[0] ?? 'unknown']);

            throw new ContentUnavailable;
        }
    }

    public function activate(AdminUser $actor, string $id): void
    {
        $this->actors->ensureStorage();
        try {
            DB::transaction(function () use ($actor, $id) {
                $fresh = $this->actors->fresh($actor);
                $media = EditorialMedia::query()->lockForUpdate()->findOrFail($id);
                if (! $media->is_active) {
                    $media->is_active = true;
                    $media->save();
                    $this->audit->record($fresh->id, 'media.activated', 'media', $id);
                }
            }, 3);
        } catch (QueryException $error) {
            Log::error('Falha ao reativar mídia.', ['sqlstate' => $error->errorInfo[0] ?? 'unknown']);

            throw new ContentUnavailable;
        }
    }

    private function legacyItems(): array
    {
        $items = [];
        $seen = [];
        $legacyRows = DB::table('IMAGENS_pena')->select([
            'ID_IMAGENS as legacy_id', 'ENDERECO_IMAGENS as url', 'NOME_IMAGENS as name',
        ])->orderBy('ID_IMAGENS')->limit(500)->get();

        foreach ($legacyRows as $row) {
            $url = trim((string) $row->url);
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $preview = $this->safeLegacyPreview($url);
            $items[] = [
                'id' => 'legacy-image-'.$row->legacy_id,
                'name' => trim((string) $row->name) ?: 'Imagem legada '.$row->legacy_id,
                'source' => 'legacy', 'preview_url' => $preview,
                'selection_url' => $preview, 'selectable' => $preview !== null,
                'references' => null, 'public' => true,
            ];
        }

        $covers = DB::table('POST_pena')->select('URL_IMAGEM_POST as url')
            ->selectRaw('COUNT(*) as reference_count')
            ->whereNotNull('URL_IMAGEM_POST')->where('URL_IMAGEM_POST', '<>', '')
            ->groupBy('URL_IMAGEM_POST')->orderByDesc('reference_count')->limit(500)->get();

        foreach ($covers as $cover) {
            $url = trim((string) $cover->url);
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $preview = $this->safeLegacyPreview($url);
            $items[] = [
                'id' => 'legacy-cover-'.substr(hash('sha256', $url), 0, 24),
                'name' => 'Capa legada', 'source' => 'legacy', 'preview_url' => $preview,
                'selection_url' => $preview, 'selectable' => $preview !== null,
                'references' => (int) $cover->reference_count, 'public' => true,
            ];
        }

        return array_merge($items, $this->legacyEmbeddedItems($seen));
    }

    /** @param array<string, bool> $seen @return list<array<string, mixed>> */
    private function legacyEmbeddedItems(array &$seen): array
    {
        $references = [];
        DB::table('POST_pena')->select(['ID_POST', 'CONTEUDO_POST'])->orderBy('ID_POST')->chunk(50, function ($posts) use (&$references): void {
            foreach ($posts as $post) {
                $document = new \DOMDocument;
                $previousErrors = libxml_use_internal_errors(true);
                try {
                    $document->loadHTML(
                        '<!doctype html><html><body>'.(string) $post->CONTEUDO_POST.'</body></html>',
                        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD,
                    );
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previousErrors);
                }
                foreach ($document->getElementsByTagName('img') as $image) {
                    $url = trim(html_entity_decode((string) $image->getAttribute('src'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($url !== '') {
                        $references[$url] = ($references[$url] ?? 0) + 1;
                    }
                }
            }
        });

        $items = [];
        foreach ($references as $url => $count) {
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $preview = $this->safeLegacyPreview($url);
            $items[] = [
                'id' => 'legacy-inline-'.substr(hash('sha256', $url), 0, 24),
                'name' => 'Imagem incorporada em artigo', 'source' => 'legacy', 'preview_url' => $preview,
                'selection_url' => $preview, 'selectable' => $preview !== null,
                'references' => (int) $count, 'public' => true,
            ];
        }

        return $items;
    }

    private function safeLegacyPreview(string $url): ?string
    {
        if (preg_match('/[\x00-\x20\\\\]/', $url) || str_starts_with($url, '//')) {
            return null;
        }
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }
        $path = rawurldecode((string) ($parts['path'] ?? ''));
        if (preg_match('#(?:^|/)\.\.(?:/|$)#', $path) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $allowedHosts = array_values(array_unique(array_merge(config('pena.legacy_media_hosts', []), [$appHost])));
        if (! isset($parts['host'])) {
            if (isset($parts['scheme']) || $path === '') {
                return null;
            }

            return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
        }

        $host = strtolower((string) $parts['host']);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'https' || ! in_array($host, $allowedHosts, true) || isset($parts['port'])) {
            return null;
        }

        return 'https://'.$host.$path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function cleanup(string $originalsDisk, string $originalPath, bool $originalStored, string $derivativesDisk, string $derivativePath, bool $derivativeStored): void
    {
        foreach ([[$originalsDisk, $originalPath, $originalStored], [$derivativesDisk, $derivativePath, $derivativeStored]] as [$disk, $path, $stored]) {
            if (! $stored) {
                continue;
            }
            try {
                $this->files->delete($disk, $path);
            } catch (\Throwable $error) {
                Log::critical('Não foi possível limpar um arquivo órfão da biblioteca privada.', ['exception_type' => $error::class]);
            }
        }
    }
}
