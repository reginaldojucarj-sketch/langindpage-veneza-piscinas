<?php

namespace App\Support;

use App\Services\HtmlSanitizer;

final class LegacyPostData
{
    public static function fromRow(object $row, HtmlSanitizer $sanitizer): array
    {
        $data = self::summaryFromRow($row);
        $data['html'] = $sanitizer->sanitize((string) $row->html);

        return $data;
    }

    public static function summaryFromRow(object $row): array
    {
        // ID_PESSOA identifies the account/creator relation; it is not proof of editorial authorship.
        $author = trim((string) ($row->author_signature ?? ''));
        $managedImage = isset($row->managed_media_id) && $row->managed_media_id !== null
            ? url('/media/'.rawurlencode((string) $row->managed_media_id))
            : null;

        return [
            'id' => (int) $row->id,
            'title' => $row->title,
            'description' => $row->description,
            'snippet' => $row->snippet,
            'status' => $row->status,
            'highlight' => $row->highlight,
            'published_at' => $row->published_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'slug' => $row->slug,
            'keywords' => $row->keywords,
            'author' => $author ?: null,
            'category' => $row->category,
            'categories' => $row->categories ?: $row->category,
            'image' => $row->post_image ?: ($row->media_image ?: $managedImage),
            'image_name' => $row->image_name ?: ($row->managed_media_alt ?? null),
            'sort_order' => $row->sort_order === null ? null : (int) $row->sort_order,
        ];
    }
}
