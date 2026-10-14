// Reads app.json and adds the one setting that depends on the kind of build.
//
// Android blocks plain http:// addresses in an installed app. A test APK that talks to the API on a computer over the home
// Wi-Fi (the "preview" build in eas.json) turns that block off with ALLOW_CLEARTEXT=1; the real "production" builds never do,
// so they only ever talk to the live server over https.
module.exports = ({ config }) => ({
  ...config,
  plugins: [
    ...(config.plugins ?? []),
    ['expo-build-properties', { android: { usesCleartextTraffic: process.env.ALLOW_CLEARTEXT === '1' } }],
  ],
});
