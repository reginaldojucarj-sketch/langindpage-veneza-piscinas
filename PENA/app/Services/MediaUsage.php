<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MediaUsage
{
    public function isInUse(string $id): bool
    {
        return DB::table('pena_authors')->where('photo_media_id', $id)->exists()
            || DB::table('pena_post_media_assignments')->where('media_id', $id)->exists()
            || DB::table('POST_pena')->where('URL_IMAGEM_POST', 'LIKE', '%'.$id.'%')->exists()
            || DB::table('POST_pena')->where('CONTEUDO_POST', 'LIKE', '%'.$id.'%')->exists();
    }
}
