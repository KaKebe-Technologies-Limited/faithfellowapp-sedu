# How the documentation was made

`FaithFellows-Documentation.html` and `.pdf` are built from the screenshots in `../screens`. To refresh them after the app changes:

1. Start the local servers: `php artisan serve` (port 8000) and the app's web preview with `EXPO_PUBLIC_API_URL=http://localhost:8000/api npx expo start --web --port 8084`.
2. `prep.php` gives the demo member and the demo church a little life and writes `token.txt` (run it with `php artisan tinker --execute="require 'prep.php';"` from `web/`).
3. In a folder with `puppeteer-core` installed (`PUPPETEER_SKIP_DOWNLOAD=1 npm i puppeteer-core`), run `node shoot.mjs`. It signs in with the `DEMO_*` accounts from `web/.env` (local development only) and saves the pictures into `../screens`.
4. `python build_docs.py` writes the HTML, then `node render_pdf.mjs` prints it to the PDF with Chrome.

Notes for the pictures:

- Only the development database and the demo accounts are used. Take care that no real person shows up: crop the portal pages above lists of people, and swap any real name for an example name before shooting.
- The business figures in the document are the plan list prices; nothing in it is a forecast.
- The printed pages are not deployed: `deploy/shared-hosting/build.py` leaves `docs/documentation` out of the live site.
