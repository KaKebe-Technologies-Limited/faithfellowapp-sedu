This is the FaithFellows mobile app (Expo SDK 57, React Native, JavaScript). It talks to the Laravel API in `../web` (`/api/v1`).
Prioritize mobile-first patterns, performance and cross-platform compatibility.

## Expo changes every SDK — check the docs

Read the major version of `expo` in `package.json`, then use the matching versioned docs
(`https://docs.expo.dev/versions/v<major>.0.0/`) or the index at `https://docs.expo.dev/llms.txt`
before writing code against an Expo, EAS or React Native API. Do not rely on memory.

## Commands

```bash
npx expo install <package>   # always use this instead of npm install for Expo/native packages
npx expo start               # dev server (scan the QR code with Expo Go)
npx expo-doctor              # diagnose dependency and config issues
npx expo export --platform android --output-dir dist   # build a JS bundle to catch errors without a device
```

## Project rules

- Routes live in `app/` (Expo Router; every file is a screen, `_layout.jsx` defines navigators). Shared code lives
  outside it: `components/`, `lib/`, `constants/`.
- JavaScript only (`.js` / `.jsx`) for now. Every file starts with a short comment saying what it is for.
- Every screen handles loading, error and empty states — use `components/StateView.jsx`.
- Brand: `COLORS.primary` is `#BC2649`; fonts are Clarity City (the website font) via `AppText`. Take colours and spacing from `constants/theme.js`.
- All API calls go through `lib/api.js` (Bearer token from secure storage, friendly `errorMessage()`); use `lib/useFetch.js` for GETs.
- Only `EXPO_PUBLIC_*` environment variables reach the app. Keep `app/.env` out of git (use `.env.example`).
- Do not edit `ios/` or `android/` by hand; configure native behaviour in `app.json` and config plugins.
- Expo Go only includes Expo's bundled native modules. A library with its own native code needs a development build.
- Audio uses `expo-audio` (`expo-av` was removed in this SDK).
