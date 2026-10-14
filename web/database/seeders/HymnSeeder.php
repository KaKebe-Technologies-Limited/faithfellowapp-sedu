<?php

namespace Database\Seeders;

use App\Models\Hymn;
use App\Models\Hymnal;
use Illuminate\Database\Seeder;

/**
 * The first hymnal: public-domain English hymns collected from the Open Hymnal Project (openhymnal.org), each with its
 * author, composer, tune and where the text came from. Run again at any time: hymns that already exist (even if the
 * super admin edited them) are left as they are, and only missing ones are added.
 *
 *   php artisan db:seed --class=HymnSeeder
 */
class HymnSeeder extends Seeder
{
    public function run(): void
    {
        $file = __DIR__.'/data/classic-hymns.json';
        if (! is_file($file)) {
            return;
        }
        $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        $hymnal = Hymnal::firstOrCreate(['slug' => $data['hymnal']['slug']], $data['hymnal'] + ['is_published' => true, 'sort' => 0]);

        foreach ($data['hymns'] as $row) {
            Hymn::firstOrCreate(['hymnal_id' => $hymnal->id, 'title' => $row['title']], [
                'number' => $row['number'], 'first_line' => $row['first_line'] ?: null, 'verses' => $row['verses'], 'refrain' => $row['refrain'],
                'author' => $row['author'] ?: null, 'translator' => $row['translator'] ?: null, 'composer' => $row['composer'] ?: null, 'arranger' => $row['arranger'] ?: null,
                'tune' => $row['tune'] ?: null, 'meter' => $row['meter'] ?: null, 'key_signature' => $row['key_signature'] ?: null, 'year_written' => $row['year_written'],
                'themes' => $row['themes'] ?: null, 'scripture' => $row['scripture'] ?: null, 'copyright_status' => $row['copyright_status'],
                'credit_note' => $row['credit_note'] ?: null, 'source_url' => $row['source_url'] ?: null, 'status' => 'published',
            ]);
        }
        $hymnal->refreshCount();
    }
}
