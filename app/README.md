# FaithFellows app (Expo / React Native)

Mobile app for FaithFellows. It talks to the Laravel API in `../web` (`/api/v1`, Bearer tokens via Laravel Sanctum).

## Run it

```bash
npm install
cp .env.example .env     # then set EXPO_PUBLIC_API_URL to http://<your-LAN-IP>:8000/api
npx expo start           # scan the QR code with Expo Go
```

The Laravel server must be running and reachable from the phone (`php artisan serve --host=0.0.0.0 --port=8000` in `../web`).
Sign in with the demo member `member@faithfellows.test` / `password`.

## Structure

```
app/                 Expo Router screens (file = route)
  _layout.jsx          root layout: fonts, splash, auth provider
  index.jsx            entry: redirect to tabs or login
  (auth)/              login, register
  (tabs)/              home feed, churches, sermons, give, profile
  church/[slug].jsx    a church's page (cover, follow, service times, events, sermons, contact)
components/          shared UI (AppText, Button, Pill, StateView, FeedItem, SermonPlayer…)
  home/                the home screen's sections: banner carousel, church stories, live card, highlights…
lib/                 api.js (Axios + helpers), auth.jsx (session), session.js (secure token), useFetch.js
constants/theme.js   brand colours, fonts, spacing
assets/              icon, adaptive icon, splash (generated, brand magenta)
```

Every screen handles **loading**, **error** and **empty** states through `components/StateView.jsx`.

## Configuration

- `EXPO_PUBLIC_API_URL` — API root ending in `/api` (only `EXPO_PUBLIC_*` variables reach the app).
- `EXPO_PUBLIC_APP_ENV` — `development`, `staging` or `production`; picks the URL from `app.json → extra.apiUrl` when `EXPO_PUBLIC_API_URL` is not set.
- `app.json → extra.eas.projectId` — replace `REPLACE_WITH_EAS_PROJECT_ID` after running `npx eas-cli init`.

## Notes

- This is **Expo SDK 57**. APIs change between SDKs, so check the versioned docs (`https://docs.expo.dev/versions/v57.0.0/`)
  before relying on memory. Install native modules with `npx expo install <package>`.
- Audio uses `expo-audio` (`expo-av` no longer exists in this SDK).
- Check dependency health with `npx expo-doctor`; build a JS bundle without a device with `npm run bundle`.
- Accounts: people create an account with email and password in the app (`POST /api/v1/auth/register`) and sign in with
  `POST /api/v1/auth/token`. "Continue with Google" (`lib/google.js`) is built but switched off; set
  `EXPO_PUBLIC_GOOGLE_SIGNIN=1` in `.env` to show it once the Google setup in `../web/docs/API.md` ("Google sign-in
  through the browser") is done.
