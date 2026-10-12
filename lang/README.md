# Translations

The UI is written in English, and every string goes through `__()`. Each JSON file maps an English string to its translation; a missing key falls back to English.

| File | Language | Status |
|------|----------|--------|
| `sw.json` | Kiswahili | Draft, **needs native-speaker review** |
| `lg.json` | Luganda | Draft, **needs native-speaker review** |
| `luo.json` | Luo (Lango/Acholi) | Draft, **needs native-speaker review**. Several terms (e.g. "Live", "Email") are deliberately kept in English. |

These drafts cover navigation, buttons, the prayer wall, giving and check-in. They were prepared by the development team, not by professional translators. Please have a fluent church member from each language group review them before marketing in that language.

## How to review or extend

1. Open the JSON file and correct the value on the right. Never change the English key on the left.
2. Keep placeholders such as `:church` and `:name` exactly as they are.
3. To add a new string, copy its exact English text from the Blade view (the text inside `__('…')`).
4. Members choose their language under **Profile → Preferences**. Visitors can add `?lang=sw` (or `lg`, `luo`) to any URL.

Validation and authentication messages fall back to English. To translate them, publish Laravel's language files with `php artisan lang:publish` and add `lang/{locale}/validation.php`.
