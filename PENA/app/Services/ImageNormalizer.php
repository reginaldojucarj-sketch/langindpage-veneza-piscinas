<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ImageNormalizer
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public const MAX_DIMENSION = 6000;

    public const MAX_PIXELS = 20_000_000;

    public const GD_PEAK_BYTES_PER_PIXEL = 8;

    // Reserve space for the resized GD surface, encoded output, upload buffer and PHP overhead.
    public const MEMORY_HEADROOM_BYTES = 64 * 1024 * 1024;

    public const MAX_DERIVATIVE_DIMENSION = 1800;

    public function process(UploadedFile $file): ProcessedImage
    {
        $path = $file->getRealPath();
        $name = $file->getClientOriginalName();

        if (! $file->isValid() || $path === false || ! is_file($path)) {
            $this->invalid('O arquivo está vazio ou não foi recebido corretamente.');
        }
        $fileSize = @filesize($path);
        $bytes = $fileSize === false ? 0 : (int) $fileSize;
        if ($bytes < 1) {
            $this->invalid('O arquivo está vazio ou não foi recebido corretamente.');
        }
        if ($bytes > self::MAX_BYTES) {
            $this->invalid('Cada imagem pode ter no máximo 2 MB.');
        }
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, '..')) {
            $this->invalid('O nome do arquivo contém um caminho ou trecho não permitido.');
        }
        if (preg_match('/\.(?:php\d*|phtml|phar|pht|html?|shtml|svg)\.[^.]+$/i', $name)) {
            $this->invalid('Não use extensão dupla no nome do arquivo.');
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $extensionMimes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'webp' => 'image/webp',
        ];
        if (! isset($extensionMimes[$extension])) {
            $this->invalid('Formatos aceitos: JPEG, PNG e WebP. SVG, PDF, vídeo e arquivos executáveis não são aceitos.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        $image = @getimagesize($path);
        if ($mime !== $extensionMimes[$extension] || ! is_array($image) || ($image['mime'] ?? null) !== $mime) {
            $this->invalid('A extensão, a assinatura e o conteúdo real do arquivo precisam corresponder.');
        }

        [$width, $height, $imageType] = [$image[0] ?? 0, $image[1] ?? 0, $image[2] ?? 0];
        $expectedType = match ($mime) {
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png' => IMAGETYPE_PNG,
            'image/webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : -1,
        };
        $maxPixels = $this->currentPixelLimit();
        if ($imageType !== $expectedType || $width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION || ($width * $height) > $maxPixels) {
            $this->invalid('A imagem excede o limite de dimensões ou o limite seguro de pixels para a memória disponível no servidor.');
        }

        $decoder = match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') && function_exists('exif_read_data') ? 'imagecreatefromjpeg' : null,
            'image/png' => function_exists('imagecreatefrompng') ? 'imagecreatefrompng' : null,
            'image/webp' => function_exists('imagecreatefromwebp') && function_exists('imagewebp') ? 'imagecreatefromwebp' : null,
        };
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagecopyresampled') || ! $decoder) {
            $this->invalid('Este servidor não tem suporte completo para normalizar esse formato de imagem.');
        }

        $source = @$decoder($path);
        if (! $source instanceof \GdImage) {
            $this->invalid('Não foi possível decodificar a imagem.');
        }

        try {
            if ($mime === 'image/jpeg') {
                $source = $this->orientJpeg($source, $path);
            }
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $scale = min(1, self::MAX_DERIVATIVE_DIMENSION / max($sourceWidth, $sourceHeight));
            $targetWidth = max(1, (int) floor($sourceWidth * $scale));
            $targetHeight = max(1, (int) floor($sourceHeight * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($mime !== 'image/jpeg') {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
            }

            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
            ob_start();
            $encoded = match ($mime) {
                'image/jpeg' => imagejpeg($target, null, 82),
                'image/png' => imagepng($target, null, 8),
                'image/webp' => imagewebp($target, null, 80),
            };
            $derivative = ob_get_clean();
            unset($target);

            if (! $encoded || ! is_string($derivative) || $derivative === '') {
                $this->invalid('Não foi possível gerar uma versão segura da imagem.');
            }

            return new ProcessedImage(
                sourceMime: $mime,
                publicMime: $mime,
                extension: $mime === 'image/jpeg' ? 'jpg' : ($mime === 'image/png' ? 'png' : 'webp'),
                width: $targetWidth,
                height: $targetHeight,
                originalBytes: $bytes,
                sha256: hash_file('sha256', $path),
                derivative: $derivative,
            );
        } finally {
            unset($source);
        }
    }

    private function orientJpeg(\GdImage $image, string $path): \GdImage
    {
        $metadata = @exif_read_data($path, 'IFD0', true, false);
        $orientation = (int) ($metadata['IFD0']['Orientation'] ?? $metadata['Orientation'] ?? 1);
        $flip = static function (\GdImage $image, int $mode): \GdImage {
            imageflip($image, $mode);

            return $image;
        };

        $rotated = match ($orientation) {
            2 => $flip($image, IMG_FLIP_HORIZONTAL),
            4 => $flip($image, IMG_FLIP_VERTICAL),
            3, 5, 6, 7, 8 => imagerotate($image, match ($orientation) {
                3 => 180,
                5, 6 => -90,
                7, 8 => 90,
            }, 0) ?: $image,
            default => $image,
        };

        if (in_array($orientation, [5, 7], true)) {
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
        }

        return $rotated;
    }

    public static function pixelLimitForMemory(int $limitBytes, int $usedBytes): int
    {
        if ($limitBytes < 0) {
            return self::MAX_PIXELS;
        }

        $available = $limitBytes - $usedBytes - self::MEMORY_HEADROOM_BYTES;

        return min(self::MAX_PIXELS, intdiv(max(0, $available), self::GD_PEAK_BYTES_PER_PIXEL));
    }

    private function currentPixelLimit(): int
    {
        $configured = trim((string) ini_get('memory_limit'));
        if ($configured === '-1') {
            return self::MAX_PIXELS;
        }

        if (! preg_match('/^(\d+)\s*([KMG]?)B?$/i', $configured, $matches)) {
            // Unknown INI syntax must fail closed to a conservative 128 MiB budget.
            $limitBytes = 128 * 1024 * 1024;
        } else {
            $unit = strtoupper($matches[2] ?? '');
            $power = match ($unit) {
                'G' => 3,
                'M' => 2,
                'K' => 1,
                default => 0,
            };
            $limitBytes = (int) $matches[1] * (1024 ** $power);
        }

        return self::pixelLimitForMemory($limitBytes, memory_get_usage(true));
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
