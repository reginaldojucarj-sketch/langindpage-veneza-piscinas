<?php

namespace App\Http\Requests;

use App\Services\PostEditor;
use Illuminate\Foundation\Http\FormRequest;

class PostContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit-content') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:71'],
            'slug' => [$this->route('post') ? 'required' : 'nullable', 'string', 'max:200'],
            'html' => ['required', 'string'],
            'snippet' => ['nullable', 'string', 'max:156'],
            'description' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && strlen(trim($value)) > PostEditor::DESCRIPTION_MAX_BYTES) {
                    $fail('A descrição excede o limite de 65.535 bytes do banco de dados.');
                }
            }],
            'keywords' => ['nullable', 'string', 'max:200'],
            'cover_url' => ['nullable', 'string', 'max:200'],
            'author_id' => ['required', 'integer', 'min:1'],
            'media_id' => ['nullable', 'uuid'],
            'main_category_id' => ['required', 'integer', 'min:1'],
            'category_ids' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'category_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'expected_fingerprint' => [$this->route('post') ? 'required' : 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ];
    }
}
