<?php

namespace Tests\Unit;

use App\Services\ImageNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ImageNormalizerTest extends TestCase
{
    public function test_pixel_budget_adapts_to_memory_and_accounts_for_gd_rotation_peak(): void
    {
        $mebibyte = 1024 * 1024;

        $this->assertSame(4_194_304, ImageNormalizer::pixelLimitForMemory(128 * $mebibyte, 32 * $mebibyte));
        $this->assertSame(ImageNormalizer::MAX_PIXELS, ImageNormalizer::pixelLimitForMemory(256 * $mebibyte, 32 * $mebibyte));
        $this->assertSame(0, ImageNormalizer::pixelLimitForMemory(64 * $mebibyte, 40 * $mebibyte));
        $this->assertSame(ImageNormalizer::MAX_PIXELS, ImageNormalizer::pixelLimitForMemory(-1, 120 * $mebibyte));
    }

    public function test_jpeg_exif_upload_obeys_a_real_128_mebibyte_process_limit(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagejpeg') || ! function_exists('exif_read_data')) {
            $this->markTestSkipped('GD e EXIF são necessários para o teste de upload.');
        }

        $accepted = $this->orientedJpeg(2400, 1800);
        $rejected = $this->orientedJpeg(4000, 3000);

        try {
            $acceptedProbe = $this->probe($accepted);
            $this->assertSame(['status' => 'accepted', 'width' => 1350, 'height' => 1800], $acceptedProbe);

            $rejectedProbe = $this->probe($rejected);
            $this->assertSame(['status' => 'rejected', 'field' => 'file', 'reason' => 'pixel_budget'], $rejectedProbe);
        } finally {
            @unlink($accepted);
            @unlink($rejected);
        }
    }

    private function probe(string $path): array
    {
        $process = new Process([PHP_BINARY, '-d', 'memory_limit=128M', __DIR__.'/../Support/image-normalizer-probe.php', $path]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().' '.$process->getOutput());
        $result = json_decode($process->getOutput(), true);
        $this->assertIsArray($result, 'stdout: '.$process->getOutput().' stderr: '.$process->getErrorOutput());

        return $result;
    }

    private function orientedJpeg(int $width, int $height): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pena-exif-');
        $image = imagecreatetruecolor($width, $height);

        try {
            if ($path === false || ! $image) {
                $this->fail('Não foi possível criar a imagem de teste.');
            }
            $this->assertTrue(imagejpeg($image, $path, 75));
            $jpeg = file_get_contents($path);
            $this->assertIsString($jpeg);

            // APP1/Exif, TIFF little-endian, orientation 6 (rotate 90 degrees clockwise).
            $exif = "Exif\0\0II".pack('vVv', 42, 8, 1)
                .pack('vvVvvV', 0x0112, 3, 1, 6, 0, 0);
            $segment = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
            $this->assertNotFalse(file_put_contents($path, substr($jpeg, 0, 2).$segment.substr($jpeg, 2)));
            $this->assertLessThanOrEqual(ImageNormalizer::MAX_BYTES, filesize($path));

            return $path;
        } finally {
            unset($image);
        }
    }
}
