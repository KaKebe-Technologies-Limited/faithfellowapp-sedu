# Putting FaithFellows on Google Play

App id (package name): `tech.kakebe.faithfellows` · Version in this guide: 1.1.2 · Pictures for the listing are in this folder.

Google changes its rules often. Where a screen in Play Console looks different from this guide, follow the screen; the answers stay the same.

## 0. What was prepared for you

- **Account deletion** (Google requires it): Me → Delete my account in the app, and a public page at https://faithfellows.com/delete-account.
- **Privacy policy** updated for the app: https://faithfellows.com/privacy.
- **Permissions trimmed:** the microphone permission was removed (the camera only scans QR tickets). What is left: internet, camera (church staff scanning tickets), approximate and precise location (optional, "believers near you"), notifications, and media playback in the background (radio and podcasts).
- **Android 16 (API 36)**, which Google now requires for new apps, is what this Expo/React Native version builds for.
- **Pictures:** `icon-512.png`, `feature-graphic-1024x500.png`, `screenshots/phone-01.png` … `phone-08.png`.

## 1. Build the file Google wants (an .aab)

From the `app` folder on your PC (you are already logged in to Expo as `sedrickotolo`):

```bash
npx eas-cli build -p android --profile production
```

It takes about 25 minutes. When it finishes, open the build page on https://expo.dev, and download the `.aab` file. Expo keeps the signing key safe; Google re-signs the app for the store (Play App Signing), so you never handle a key yourself.

## 2. Create the app in Play Console

1. https://play.google.com/console → **Create app**.
2. App name: **FaithFellows: Church & Bible** (28 characters; Google allows 30). Default language: English (United Kingdom or United States). **App**, **Free**.
3. Tick the declarations (developer programme policies, US export laws) and create it.

## 3. Fill the "Set up your app" tasks (App content)

Use the answers in section 6. The list: privacy policy, app access, ads, content rating, target audience, data safety, government apps, financial features, health, advertising ID, and the foreground service declaration.

## 4. Store listing (Grow → Store presence → Main store listing)

Paste the texts from section 5 and upload: icon (512), feature graphic (1024×500), and the 8 phone screenshots. Category: **Lifestyle** (or Books & Reference). Contact: email `service@kakebe.tech`, website https://faithfellows.com, phone 0393249845.

## 5. Listing texts

**Short description (80 max):**
Bible, hymns, prayer, mobile money giving and your church family in one app.

**Full description:**

FaithFellows brings your church family into your pocket. Free for every member, light on data, made in Lira, Uganda.

READ AND PRAY
• The Bible in the version you love, with highlights, notes and verse sharing
• A daily inspiration, a verse of the day, hymns, sermons and devotionals
• Gospel radio and faith-based podcasts that keep playing when your screen is off
• A prayer wall to ask for prayer and to pray for others

GIVE WITH JOY
• Give your tithes and offerings to your church by mobile money (MTN and Airtel)
• Choose what your gift is for, support fundraisers, and get a receipt and a thank-you by email

BELONG
• Follow your church and see its sermons, events, live services and announcements
• Events with tickets and QR check-in
• Meet believers who share your faith (adults only; others only see roughly how far away you are, never where)
• Bible quiz games to play with friends, reading plans and reading circles

GROW
• Private figures for your time in the Bible, listening and sharing, just for you

FOR CHURCHES
Church leaders can post announcements, run events and prayer, host quizzes, go live and see giving records from the app. Register your church at faithfellows.com.

Questions? service@kakebe.tech

**Release notes (first release):** Welcome to FaithFellows. Read the Bible, pray, give and grow with your church family.

## 6. Answers for the forms

**Privacy policy URL:** https://faithfellows.com/privacy

**App access:** the app needs a sign-in. Choose "All or some functionality is restricted" and give Google a reviewer account: create an ordinary member account in the app (for example `play.review@…`), follow one church, and write the email and password here. Do not put these in the repository. Add one line: "Sign in with email and password. Google sign-in is optional."

**Ads:** No ads.

**Target audience:** 13 and over (13–15, 16–17, 18+). Not designed for children. The "meet believers" feature is limited to people 18 and over.

**Content rating (IARC questionnaire):** category *Reference / Social*; violence, sexuality, bad language, drugs, gambling: none. Users can interact (chat, prayer wall) and members can see approximate distance to each other: answer yes where asked. No digital goods are sold. Expect a rating like "Everyone" or "Teen".

**Data safety — collected, optional or needed:**

| Type | Collected | Why |
|---|---|---|
| Name, email, user id | Yes | Account, app functionality |
| Phone number | Yes (optional) | Mobile money, contact |
| Date of birth, gender | Yes (optional) | Age checks for meeting people, personalisation |
| Religious beliefs (churches followed, prayers) | Yes | App functionality |
| Approximate and precise location | Yes (optional) | Believers and churches near you |
| Photos | Yes (only pictures you choose) | Profile and event pictures |
| Messages and other user content (chat, prayer, comments) | Yes | App functionality |
| Purchase history / other financial info / payment info (gift records, mobile money number) | Yes | Giving, receipts |
| App interactions (your own Bible and listening figures) | Yes | Personalisation (shown to you) |
| Device or other IDs (push token) | Yes | Notifications |

- **Shared with third parties:** No. Payment processors (MTN, Airtel, ioTec), Google (sign-in), Expo and Firebase (notifications) act on our behalf as service providers.
- **Encrypted in transit:** Yes (https). **Users can ask for deletion:** Yes. **Deletion web address:** https://faithfellows.com/delete-account
- **Audio, contacts, calendar, health, web history:** not collected.

**Financial features:** the app lets members give to churches with mobile money. Declare it honestly, choosing the closest option on screen (payments / donations / other). It is not a bank, loan or investment product. Donations to churches and tickets for real events do not need Google Play billing.

**Advertising ID:** No.

**Foreground service (media playback):** the radio and podcasts keep playing with the screen off, with controls on the lock screen. Google asks for a short video (30 seconds): start the radio, lock the phone, show the notification with the play/pause controls, and unlock.

**Government app / health / news:** No.

## 7. Upload and test

1. **Testing → Internal testing → Create new release.** Accept *Play App Signing* when asked.
2. Upload the `.aab` from step 1. Release name: `1.1.2`. Paste the release notes. **Save → Review → Start rollout to internal testing.**
3. **Testers tab:** create a list with your own email and the people who will test; copy the *opt-in link*; each person opens it on the phone, taps *Become a tester*, then installs from Google Play.
4. Test the real thing: sign in, read the Bible, play radio with the screen off, give a small gift, and delete a throw-away account.
5. If anyone has the old APK (1.1.1) installed from the Expo link, they must **uninstall it first**. The Play version is signed with a different key and cannot be installed over it.

## 8. Production

- If your Play developer account is a **personal account created after 13 November 2023**, Google requires a **closed test with at least 12 testers opted in for 14 days in a row** before you can apply for production (Dashboard → "Apply for production"). **Organisation accounts** (a registered company) are exempt. Check the Dashboard: it tells you exactly what is still needed.
- **Production → Create new release** (you can reuse the same file from internal testing), add the countries (Uganda first, or all), and **Send for review**. The first review can take several days.

## 9. After it is live

1. Super admin → Settings → **App download link**: paste https://play.google.com/store/apps/details?id=tech.kakebe.faithfellows. The "Download the app" buttons on the website then open Google Play.
2. Google sign-in: in Google Cloud, make sure the consent screen is **In production** (not "Testing"), or only listed test users can sign in.
3. Updates:
   - Changes to text, screens and logic go out by themselves: from `app/` run `npx eas-cli update --channel production --environment production --platform android -m "what changed"` (the same version number).
   - A new package, permission or plugin needs a new version number in `app/app.json`, a new `.aab` build, and a new release in Play Console.

## 10. If Google asks questions

- *Why does the app use location?* To show churches and believers near you. Optional, only while using the app, others see only a rough distance.
- *Why does it use the camera?* Church staff scan guests' QR tickets at events. Nothing is recorded.
- *Is giving a digital good?* No: it is a donation to a church, paid by mobile money outside Google Play billing, which Google's payments policy allows for donations and real-world events.
