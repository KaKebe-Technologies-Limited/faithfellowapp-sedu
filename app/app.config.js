// Reads app.json and adds the settings that depend on the kind of build.
//
// Android blocks plain http:// addresses in an installed app. A test APK that talks to the API on a computer over the home
// Wi-Fi (the "local-wifi" build in eas.json) turns that block off with ALLOW_CLEARTEXT=1; the real builds never do, so they
// only ever talk to the live server over https.
//
// Push notifications on Android need Firebase: google-services.json from the Firebase console, saved in this folder (or its
// path in GOOGLE_SERVICES_JSON). Without the file the app still builds, but an Android phone cannot get a push token.
const fs = require('fs');
const path = require('path');

const googleServices = process.env.GOOGLE_SERVICES_JSON || (fs.existsSync(path.join(__dirname, 'google-services.json')) ? './google-services.json' : null);

module.exports = ({ config }) => ({
  ...config,
  android: { ...config.android, ...(googleServices ? { googleServicesFile: googleServices } : {}) },
  plugins: [
    ...(config.plugins ?? []),
    ['expo-build-properties', { android: { usesCleartextTraffic: process.env.ALLOW_CLEARTEXT === '1' } }],
  ],
});
