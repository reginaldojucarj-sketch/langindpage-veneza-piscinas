<?php

namespace App\Services;

use App\Exceptions\ContentUnavailable;
use App\Models\AdminUser;
use App\Models\EditorialAuthor;
use App\Models\EditorialMedia;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthorLibrary
{
    public function __construct(
        private readonly EditorialActor $actors,
        private readonly ContentAudit $audit,
    ) {}

    public function listing(?string $search = null): array
    {
        try {
            $query = DB::table('pena_authors as authors')
                ->leftJoin('PESSOA_pena as people', 'people.ID_PESSOA', '=', 'authors.person_id')
                ->select([
                    'authors.id', 'authors.legacy_author_id', 'authors.person_id', 'authors.signature', 'authors.slug',
                    'authors.description', 'authors.legacy_photo_url', 'authors.photo_media_id', 'authors.is_active',
                    'authors.updated_at', 'authors.version', 'people.NOME_PESSOA as person_first_name',
                    'people.SOBRENOME_PESSOA as person_last_name',
                ])
                ->selectSub(DB::table('pena_post_author_assignments')->selectRaw('COUNT(*)')
                    ->whereColumn('author_id', 'authors.id'), 'article_count')
                ->orderBy('authors.signature');

            if ($search !== null && trim($search) !== '') {
                $pattern = '%'.strtr(trim($search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                $query->where(function ($where) use ($pattern) {
                    foreach (['authors.signature', 'authors.slug', 'people.NOME_PESSOA', 'people.SOBRENOME_PESSOA'] as $column) {
                        $where->orWhereRaw($column." LIKE ? ESCAPE '!'", [$pattern]);
                    }
                });
            }

            return $query->limit(500)->get()->map(fn ($author) => (array) $author)->all();
        } catch (QueryException $error) {
            Log::error('Falha ao listar autores editoriais.', ['sqlstate' => $error->errorInfo[0] ?? 'unknown']);

            throw new ContentUnavailable;
        }
    }

    public function people(): array
    {
        try {
            return DB::table('PESSOA_pena')->select([
                'ID_PESSOA as id', 'NOME_PESSOA as first_name', 'SOBRENOME_PESSOA as last_name',
            ])->orderBy('NOME_PESSOA')->orderBy('SOBRENOME_PESSOA')->limit(500)->get()->map(fn ($person) => (array) $person)->all();
        } catch (QueryException $error) {
            Log::error('Falha ao consultar pessoas para o vínculo explícito de autoria.', ['sqlstate' => $error->errorInfo[0] ?? 'unknown']);

            throw new ContentUnavailable;
        }
    }

    public function create(AdminUser $actor, array $input): EditorialAuthor
    {
        $data = $this->validate($input);

        return $this->write(function () use ($actor, $data) {
            $fresh = $this->actors->fresh($actor);
            $author = new EditorialAuthor;
            $author->forceFill([
                'legacy_author_id' => null,
                'person_id' => (int) $data['person_id'],
                'signature' => $data['signature'],
                'slug' => $data['slug'],
                'description' => ($data['description'] ?? '') ?: null,
                'is_active' => true,
                'created_by' => $fresh->id,
                'updated_by' => $fresh->id,
                'version' => 1,
            ])->save();
            $this->audit->record($fresh->id, 'author.created', 'author', $author->id, ['fields' => ['person_id', 'signature', 'slug', 'description']]);

            return $author;
        });
    }

    public function update(AdminUser $actor, int $id, array $input): EditorialAuthor
    {
        $author = EditorialAuthor::query()->findOrFail($id);
        $data = $this->validate($input, $author);

        return $this->write(function () use ($actor, $author, $data) {
            $fresh = $this->actors->fresh($actor);
            $target = EditorialAuthor::query()->lockForUpdate()->findOrFail($author->id);
            if ((int) $data['expected_version'] !== (int) $target->version) {
                throw ValidationException::withMessages(['expected_version' => 'Este autor foi alterado desde que você abriu o formulário. Recarregue a página.']);
            }
            $selectedPhotoId = array_key_exists('photo_media_id', $data)
                ? ($data['photo_media_id'] ?: null)
                : $target->photo_media_id;
            if ($selectedPhotoId !== $target->photo_media_id && $selectedPhotoId !== null) {
                $photo = EditorialMedia::query()->where('id', $selectedPhotoId)
                    ->where('is_active', true)->lockForUpdate()->first();
                if (! $photo) {
                    throw ValidationException::withMessages(['photo_media_id' => 'A imagem foi desativada. Recarregue a página e selecione uma imagem ativa.']);
                }
            }

            $target->forceFill([
                'signature' => $data['signature'],
                'slug' => $data['slug'],
                'description' => ($data['description'] ?? '') ?: null,
                'photo_media_id' => $selectedPhotoId,
                'updated_by' => $fresh->id,
            ]);
            $changed = array_keys($target->getDirty());
            if ($changed !== []) {
                $target->version++;
                $target->save();
                $this->audit->record($fresh->id, 'author.updated', 'author', $target->id, ['fields' => $changed]);
            }

            return $target;
        });
    }

    public function deactivate(AdminUser $actor, int $id, int $expectedVersion): void
    {
        $this->setActive($actor, $id, $expectedVersion, false);
    }

    public function activate(AdminUser $actor, int $id, int $expectedVersion): void
    {
        $this->setActive($actor, $id, $expectedVersion, true);
    }

    private function setActive(AdminUser $actor, int $id, int $expectedVersion, bool $active): void
    {
        $this->write(function () use ($actor, $id, $expectedVersion, $active) {
            $fresh = $this->actors->fresh($actor);
            $author = EditorialAuthor::query()->lockForUpdate()->findOrFail($id);
            if ((int) $author->version !== $expectedVersion) {
                throw ValidationException::withMessages(['expected_version' => 'Este autor foi alterado desde que você abriu a página. Recarregue-a.']);
            }
            if ((bool) $author->is_active === $active) {
                return;
            }

            $author->is_active = $active;
            $author->updated_by = $fresh->id;
            $author->version++;
            $author->save();
            $this->audit->record($fresh->id, $active ? 'author.activated' : 'author.deactivated', 'author', $author->id, ['historical_signature_preserved' => true]);
        });
    }

    private function validate(array $input, ?EditorialAuthor $target = null): array
    {
        foreach (['signature', 'slug', 'description'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        if (isset($input['slug']) && is_string($input['slug'])) {
            $input['slug'] = strtolower($input['slug']);
        }

        $rules = [
            'signature' => ['required', 'string', 'min:2', 'max:50', Rule::unique('pena_authors', 'signature')->ignore($target?->id)],
            'slug' => ['bail', 'required', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('pena_authors', 'slug')->ignore($target?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
        ];

        if ($target) {
            $rules['expected_version'] = ['required', 'integer', 'min:1', 'max:2147483647'];
            $rules['photo_media_id'] = ['nullable', 'uuid'];
        } else {
            $rules['person_id'] = ['required', 'integer', 'min:1', Rule::exists('PESSOA_pena', 'ID_PESSOA')];
        }

        return Validator::make($input, $rules)->validate();
    }

    private function write(callable $operation): mixed
    {
        $this->actors->ensureStorage();
        try {
            return DB::transaction($operation, 3);
        } catch (ValidationException $error) {
            throw $error;
        } catch (QueryException $error) {
            Log::error('Falha ao gravar cadastro editorial.', ['sqlstate' => $error->errorInfo[0] ?? 'unknown']);
            if (in_array((string) ($error->errorInfo[1] ?? ''), ['1062', '19'], true)) {
                throw ValidationException::withMessages(['signature' => 'Já existe um autor com essa assinatura ou URL amigável.']);
            }

            throw new ContentUnavailable;
        }
    }
}
