<?php

namespace App\Services;

final readonly class ProcessedImage
{
    public function __construct(
        public string $sourceMime,
        public string $publicMime,
        public string $extension,
        public int $width,
        public int $height,
        public int $originalBytes,
        public string $sha256,
        public string $derivative,
    ) {}
}
