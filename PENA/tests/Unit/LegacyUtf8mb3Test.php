<?php

namespace Tests\Unit;

use App\Services\LegacyUtf8mb3;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegacyUtf8mb3Test extends TestCase
{
    public function test_bmp_unicode_and_multibyte_values_within_character_limits_are_supported(): void
    {
        (new LegacyUtf8mb3)->assertSupported(['title' => 'Água, segurança e 泳池'], ['title' => 30]);
        $this->assertTrue(true);
    }

    public function test_astral_unicode_is_rejected_with_a_field_error_not_removed(): void
    {
        try {
            (new LegacyUtf8mb3)->assertSupported(['title' => 'Piscina 🌊']);
            $this->fail('Astral code points are not representable by utf8mb3.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('title', $error->errors());
            $this->assertStringContainsString('utf8mb3', $error->errors()['title'][0]);
        }
    }

    public function test_character_limit_counts_unicode_characters_not_bytes(): void
    {
        $validator = new LegacyUtf8mb3;
        $validator->assertSupported(['title' => str_repeat('á', 4)], ['title' => 4]);

        $this->expectException(ValidationException::class);
        $validator->assertSupported(['title' => str_repeat('á', 5)], ['title' => 4]);
    }
}
