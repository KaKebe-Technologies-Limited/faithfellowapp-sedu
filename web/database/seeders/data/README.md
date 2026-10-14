# Hymn data

`classic-hymns.json` is the first hymnal ("Classic Hymns", 195 hymns), loaded by `HymnSeeder`.

**Source and licence.** The words, authors, composers, tunes and years were collected from the
[Open Hymnal Project](https://openhymnal.org/) (a mirror of its data is at https://github.com/mzealey/openhymnal), whose
hymns are in the public domain or freely distributable. Each hymn keeps the credit lines the source gives (`credit_note`) and a
`source_url` to the file it came from, and the hymn page in the app shows who wrote the words and the music, the licence and
where the text was collected.

**How it was made.** The Open Hymnal stores each hymn as an ABC music file. The words were read from the lyric lines, the
syllables joined, and the poem's lines rebuilt from the hymn's metre. Only hymns whose lines matched their metre exactly were
kept, hymns by living authors or composers were left out, and the hymns were numbered alphabetically.

**Adding more.** Add hymnals and hymns on the website: Super admin, Hymn book. Do not copy modern worship songs (Hillsong,
Bethel and the like) without a licence (for example CCLI), and do not copy a whole denominational hymnal without written
permission from its publisher.
