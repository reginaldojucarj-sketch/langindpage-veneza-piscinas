<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class LegacyUtf8mb3
{
    /** @param array<string, string> $fields field name => submitted value */
    public function assertSupported(array $fields, array $characterLimits = []): void
    {
        $errors = [];

        foreach ($fields as $field => $value) {
            if (preg_match('//u', $value) !== 1) {
                $errors[$field] = 'O texto contém uma sequência de caracteres inválida.';

                continue;
            }

            if (preg_match('/[\x{10000}-\x{10FFFF}]/u', $value) === 1) {
                $errors[$field] = 'Este campo não aceita caracteres fora do conjunto atual do banco (utf8mb3).';

                continue;
            }

            $limit = $characterLimits[$field] ?? null;
            if ($limit !== null && mb_strlen($value, 'UTF-8') > $limit) {
                $errors[$field] = "Este campo aceita no máximo {$limit} caracteres.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
