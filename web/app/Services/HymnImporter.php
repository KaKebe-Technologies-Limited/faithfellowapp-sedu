<?php

namespace App\Services;

use App\Models\Hymn;
use App\Models\Hymnal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Loads a whole hymnal from a JSON file (a hymn book that was typed or converted from a PDF), so hundreds of hymns do not
 * have to be added one by one. Run again with a corrected file to update the same hymns: a hymn is found by its hymnal,
 * its part of the book and its number.
 *
 * The file looks like this (verses may be `{ "type": "text", "label": "Coda", "lines": [...] }` for a block printed as it is):
 *
 *   { "hymnal": { "name": "...", "language": "lg", "denomination": "Catholic", "description": "...", "source_name": "...",
 *                 "sections": [{ "name": "Ez’Amayingira", "meaning": "Entrance hymns" }] },
 *     "hymns": [{ "section": "Ez’Amayingira", "number": 1, "title": "...", "author": "...", "composer": "...", "copyright_status": "hymnal",
 *                 "verses": [{ "number": 1, "lines": ["..."] }], "refrain": { "lines": ["..."] } }] }
 */
class HymnImporter
{
    /** @return array{hymnal: Hymnal, created: int, updated: int, skipped: int} */
    public function import(array $data, bool $publish = true): array
    {
        $info = $data['hymnal'] ?? null;
        if (! is_array($info) || blank($info['name'] ?? null)) {
            throw new InvalidArgumentException('The file needs a "hymnal" with a name.');
        }
        if (! array_key_exists($info['language'] ?? 'en', Hymnal::LANGUAGES)) {
            throw new InvalidArgumentException('Unknown language "'.$info['language'].'". Use one of: '.implode(', ', array_keys(Hymnal::LANGUAGES)).'.');
        }

        return DB::transaction(function () use ($data, $info, $publish) {
            $slug = $info['slug'] ?? Hymnal::uniqueSlug($info['name']);
            $hymnal = Hymnal::withTrashed()->firstOrNew(['slug' => $slug]);
            $hymnal->fill([
                'name' => $info['name'], 'language' => $info['language'] ?? 'en', 'denomination' => $info['denomination'] ?? null, 'description' => $info['description'] ?? null,
                'sections' => $info['sections'] ?? null, 'source_name' => $info['source_name'] ?? null, 'source_url' => $info['source_url'] ?? null,
                'sort' => $info['sort'] ?? $hymnal->sort ?? 0,
            ]);
            if (! $hymnal->exists) {
                $hymnal->is_published = $publish;
            }
            $hymnal->deleted_at = null;
            $hymnal->save();

            $order = collect($hymnal->sections ?? [])->pluck('name')->values()->flip();
            $created = $updated = $skipped = 0;
            foreach ($data['hymns'] ?? [] as $row) {
                $verses = collect($row['verses'] ?? [])->map(fn ($v) => [...$v, 'lines' => array_values(array_filter(array_map('trim', $v['lines'] ?? []), fn ($l) => $l !== ''))])
                    ->filter(fn ($v) => $v['lines'] !== [])->values()->all();
                if (blank($row['title'] ?? null) || $verses === []) {
                    $skipped++;

                    continue;
                }
                $refrain = ! empty($row['refrain']['lines']) ? ['lines' => array_values($row['refrain']['lines'])] : null;
                $section = $row['section'] ?? null;
                $hymn = Hymn::withTrashed()->firstOrNew(['hymnal_id' => $hymnal->id, 'section' => $section, 'number' => $row['number'] ?? null]);
                $existed = $hymn->exists;
                $hymn->fill([
                    'section_order' => $section !== null ? (($order[$section] ?? -1) + 1) : 0,
                    'title' => $row['title'], 'first_line' => $row['first_line'] ?? null, 'verses' => $verses, 'refrain' => $refrain,
                    'author' => $row['author'] ?? null, 'translator' => $row['translator'] ?? null, 'composer' => $row['composer'] ?? null, 'arranger' => $row['arranger'] ?? null,
                    'tune' => $row['tune'] ?? null, 'meter' => $row['meter'] ?? null, 'key_signature' => $row['key_signature'] ?? null, 'year_written' => $row['year_written'] ?? null,
                    'themes' => $row['themes'] ?? null, 'scripture' => $row['scripture'] ?? null, 'copyright_status' => $row['copyright_status'] ?? ($info['copyright_status'] ?? 'hymnal'),
                    'credit_note' => $row['credit_note'] ?? null, 'source_url' => $row['source_url'] ?? null, 'audio_url' => $row['audio_url'] ?? null, 'status' => $row['status'] ?? 'published',
                ]);
                $hymn->deleted_at = null;
                $hymn->save();
                $existed ? $updated++ : $created++;
            }
            $hymnal->refreshCount();

            return ['hymnal' => $hymnal->refresh(), 'created' => $created, 'updated' => $updated, 'skipped' => $skipped];
        });
    }
}
