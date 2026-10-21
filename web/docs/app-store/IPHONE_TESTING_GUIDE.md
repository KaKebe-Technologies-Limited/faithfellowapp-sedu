# FaithFellows on iPhone: packaging the app for testers

The app is already written for iPhone (the same code as Android, bundle id `tech.kakebe.faithfellows`). What an iPhone needs is a **build signed by
Apple**. Apple only allows that with a paid developer account, and signing in to it needs your Apple ID and a code sent to your phone, so these steps are
yours to run; everything else is prepared.

## What you need first

| You need | Notes |
|---|---|
| **Apple Developer Program** membership | US$99 a year, at developer.apple.com/programs. As a person it is approved within a day or two. As a company (Kakebe Technologies Limited) Apple asks for a D-U-N-S number, which can take a week or more. Without this membership nobody can install the app on an iPhone. |
| An **Apple ID** with two-factor sign-in | The one that owns the membership. |
| This computer, signed in to Expo | `npx eas-cli whoami` should say `sedrickotolo`. If not: `npx eas-cli login`. |

## Way 1 (recommended): TestFlight

TestFlight is Apple's own tester app. Testers install **TestFlight** from the App Store, accept your invitation and get the app, and every new build
reaches them automatically.

### 1. Make the app's page in App Store Connect (once)

1. Go to **appstoreconnect.apple.com** and sign in with the Apple ID of the membership.
2. **Apps** → **+** → **New App**. Platform **iOS**, name **FaithFellows**, language English, **Bundle ID `tech.kakebe.faithfellows`**
   (if it is not in the list yet, step 2 below creates it: do step 2 first and come back), SKU `faithfellows`, full access.

### 2. Build the iPhone app (about 30 to 40 minutes, done on Expo's computers)

Open a terminal in the `app` folder of the project and run:

```bash
cd C:\xampp\htdocs\faithfellowapp-sedu\app
npx eas-cli build --platform ios --profile production
```

The first time it asks you to **sign in to your Apple account** (Apple ID, password, the code Apple sends you) and then offers to create what Apple needs:
answer **yes** to each: the distribution certificate, the provisioning profile, the push-notification key and the bundle id. Expo keeps them for the next
builds. When it finishes it prints a link to the build.

### 3. Send it to TestFlight

```bash
npx eas-cli submit --platform ios --latest
```

It uploads the build to App Store Connect (it asks for the Apple ID again, or an App Store Connect API key if you prefer). After about 10 to 20 minutes
Apple finishes processing and the build shows under **App Store Connect → FaithFellows → TestFlight**.

### 4. Add the testers

* **Your own team (up to 100 people, no review):** App Store Connect → **Users and Access** → invite them (role **Developer** or **Marketing** is enough),
  then **TestFlight → Internal Testing → +** → make a group and tick them. They get an email, install **TestFlight**, and tap **Install**.
* **Anyone else (up to 10,000 people):** **TestFlight → External Testing → +** → make a group and either add their emails or turn on the **Public Link** and
  send them the link. The first build of each version goes through Apple's **Beta App Review** (usually a day or less). Fill in **Test Information**:
  what to try, your contact email, and **a test account for the reviewer** (an email and password that sign in to the app; make one for this).

## Way 2: straight to chosen iPhones (no TestFlight)

For a handful of phones you can skip TestFlight. Each tester's iPhone must be registered first (it counts against Apple's limit of 100 a year):

```bash
npx eas-cli device:create
```

It prints a link and a QR code. Each tester opens it on their iPhone and installs a small profile, which tells Apple the phone's number. Then:

```bash
npx eas-cli build --platform ios --profile preview-ios
```

When the build is done, Expo gives an **install link**; testers open it on the registered iPhone and tap Install. (A new phone needs `device:create` and a
new build.)

## Updates after that

* **Words, screens and fixes** (everything we have been publishing with `eas update`) reach iPhones the same way as Android: publish for both with

  ```bash
  npx eas-cli update --channel production --environment production --platform all -m "what changed"
  ```

  Testers get it the next time they open the app. (Until now updates were published for Android only; use `--platform all` from now on.)
* **A new package, permission or plugin** needs a new build: raise `version` in `app/app.json` (for example 1.1.3), then repeat steps 2 and 3. The app and the
  Android build share the version, so raise it for both.

## Things worth knowing

* **Not yet tried on a real iPhone.** The app was checked in a browser and on Android. Please watch these first on an iPhone: sign-in (email and Google),
  radio and sermon audio with the screen locked, the notification pop-ups (verse of the day, messages, new podcasts), the share sheet, location
  ("people near me") and picking a profile photo.
* **Notifications** work in TestFlight builds without anything else from you: Expo creates the Apple push key in step 2.
* **Before the public App Store** (not needed for TestFlight): Apple requires **Sign in with Apple** in an app that offers Google sign-in (rule 4.8), iPad
  screenshots (the app says it supports iPad), the privacy answers ("App Privacy") and a support page. The in-app **delete my account** and the privacy
  page are already there. Tell me when you get that far and I will add Sign in with Apple.
* **Export compliance** is already answered in the app (`ITSAppUsesNonExemptEncryption: false`: it only uses ordinary HTTPS), so TestFlight will not ask for
  every build.
