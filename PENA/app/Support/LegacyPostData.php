<?php

namespace App\Support;

final class LegacyPostData
{
    public static function fromRow(object $row): array
    {
        $author = trim((string) ($row->author_signature ?: trim(($row->first_name ?? '').' '.($row->last_name ?? ''))));

        return [
            'id' => (int) $row->id,
            'title' => $row->title,
            'html' => $row->html,
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
            'image' => $row->post_image ?: $row->media_image,
            'image_name' => $row->image_name,
            'sort_order' => $row->sort_order === null ? null : (int) $row->sort_order,
        ];
    }
}
