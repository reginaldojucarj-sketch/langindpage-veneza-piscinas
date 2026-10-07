<?php

$hosts = array_filter(array_map('strtolower', array_map('trim', explode(',', (string) env('PENA_LEGACY_MEDIA_HOSTS', 'pena.venezapiscinas.com.br,venezapiscinas.com.br')))));

return [
    'legacy_media_hosts' => array_values(array_unique($hosts)),
    'originals_disk' => 'pena_originals',
    'derivatives_disk' => 'pena_derivatives',
    // Never enable against the current MyISAM source or while an uncoordinated legacy writer is active.
    'editorial_writes_enabled' => env('PENA_EDITORIAL_WRITES_ENABLED', false),
    // Set only after the api subdomain points at this Laravel public directory.
    'api_host' => env('PENA_API_HOST') ?: null,
];
