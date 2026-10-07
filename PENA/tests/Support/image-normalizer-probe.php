<?php

use App\Services\ImageNormalizer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$path = $argv[1] ?? '';
if (! is_file($path) || ini_get('memory_limit') !== '128M') {
    fwrite(STDERR, "Invalid test fixture or memory limit.\n");
    exit(2);
}

try {
    $image = (new ImageNormalizer)->process(new UploadedFile($path, 'fixture.jpg', 'image/jpeg', UPLOAD_ERR_OK, true));
    echo json_encode(['status' => 'accepted', 'width' => $image->width, 'height' => $image->height], JSON_THROW_ON_ERROR);
} catch (ValidationException $error) {
    $errors = $error->errors();
    echo json_encode([
        'status' => 'rejected',
        'field' => array_key_first($errors),
        'reason' => str_contains($errors['file'][0] ?? '', 'limite seguro de pixels') ? 'pixel_budget' : 'other',
    ], JSON_THROW_ON_ERROR);
}
