# FaithFellows API v1

This is a JSON REST API for the FaithFellows mobile app and integrations.

**Base URL:** `https://faithfellows.com/api/v1`

## Conventions

- Send `Accept: application/json` and, for writes, `Content-Type: application/json`. All responses are JSON, including errors.
- **Authentication:** `Authorization: Bearer <token>` (Laravel Sanctum personal access tokens). Tokens expire after 180 days.
- **Rate limits:** counted for each person (their token), never for the address, because many phones share one (a church's Wi-Fi, a mobile network): 240 requests per minute for a signed-in person, 120 per address for people who are not signed in yet. A route with a limit of its own (a game's polling, giving, reactions, sign-in) is held to that one, and every route counts on its own (a game screen asking once a second never uses up the Start or Answer buttons). Sign-in and Google sign-in are limited to 60 per minute per address plus 5 failed sign-ins per email and address per minute, sign-up to 20 per minute and 300 a day per address, a password code to one a minute per email address. Giving is limited to 10 per minute. A `429` response includes `Retry-After`.
- **Pagination:** list endpoints return Laravel's `data`, `links` and `meta` envelope. Use `?page=` and, where offered, `?per_page=` (maximum 50).
- **Errors:** `401` unauthenticated, `403` forbidden or account suspended, `404` not found, `422` validation (`{"message": …, "errors": {field: [..]}}`), `429` throttled.
- **Times** are ISO 8601. **Money** is a number in UGX unless `currency` says otherwise.

---

## Health check

### `GET /api/ping`

Public and never cached. It lives at `/api/ping` (not under `/v1`). Returns `{"status": "ok", "service": "FaithFellows API", "version": "1.0.0", "release": "799c4cc 2026-10-14 10:20 UTC", "time": "2026-10-12T18:26:40+03:00"}`. `release` is the commit and time of the build that is live (`null` in development), so you can see that an update has landed. The mobile app and uptime monitors use it to tell "server unreachable" from other errors.

---

## Authentication

### `POST /auth/token`

Exchanges an email and password for a token. Super admin accounts are refused.

```json
{ "email": "member@example.com", "password": "secret", "device_name": "Grace's Tecno Spark" }
```

**201**

```json
{
  "token": "12|Jm7c0x…",
  "token_type": "Bearer",
  "expires_at": "2027-04-10T12:00:00+03:00",
  "user": { "id": 2, "name": "Grace Akello", "email": "…", "churches": [ { "id": 1, "slug": "grace-lira", "name": "Grace Community Church Lira", "logo_url": "…" } ], "unread_notifications": 3, "…": "…" }
}
```

### `POST /auth/register`

Creates a member account with email and password and signs it in. Same rules as the website's sign-up form.

```json
{ "name": "Grace Akello", "email": "grace@example.com", "phone": "0772 123 456", "password": "a-good-password-1",
  "password_confirmation": "a-good-password-1", "terms": true, "device_name": "Grace's iPhone" }
```

`phone` is optional. `terms` must be `true` (the person has agreed to the Terms of Service and Privacy Policy). Emails are trimmed and
lowercased, and an email that already belongs to an account, including a closed one, is refused with **422**. The password needs at
least 8 characters (letters and numbers, and not a known breached password, in production).

**201** returns the same body as `/auth/token` plus `"is_new_user": true`. The member is signed in at once; a "verify your email"
message is sent, and the email stays unverified until they follow it. Limited to 20 requests per minute and 300 per day per address (**429**).
### `POST /auth/google`

Signs in with Google, or creates the member account the first time. Send the **Google ID token** (a JWT the app receives from Google sign-in), not a password. The server checks Google's signature and that the token was issued for one of our OAuth client IDs (`GOOGLE_CLIENT_IDS`), so no client secret is involved.

```json
{ "credential": "eyJhbGciOiJSUzI1NiIs…", "device_name": "Grace's iPhone" }
```

**201** returns the same body as `/auth/token`, plus `"is_new_user": true|false` (use it to show a "follow a church" welcome).

- An existing member with the same email is signed in and linked to the Google account; no second account is created. If their email was never verified, any password and tokens on it are discarded first.
- New accounts are members, with the email marked verified and the Terms accepted (the app must show "By continuing you agree to the Terms and Privacy Policy").
- **401** the token could not be verified (wrong audience, expired, unverified email, bad signature). **403** the account is suspended, closed, platform staff, or linked to a different Google account. **503** Google sign-in is not configured. Limited to 60 requests per minute per address.
### Google sign-in through the browser (what the mobile app uses)

The app does not need a native Google library. It opens a page on our server in the system browser:

1. The app makes a random secret (`verifier`) and sends only its SHA-256 hash (`challenge`, base64url, 43 chars). It opens
   `GET /auth/google/app/start?redirect=<app link>&challenge=<challenge>`. The `redirect` must be the app's own link
   (`faithfellows://…`; `exp://…` only outside production), never a web address.
2. The server sends the person to Google. After they approve, Google returns to `GET /auth/google/app/callback`, where the
   server swaps Google's code for an ID token (using the OAuth client secret), verifies it, and finds or creates the member.
3. The browser is sent back to the app link with `?code=<one-time code>` (or `?error=cancelled|failed|denied&message=…`).
4. The app calls `POST /auth/google/exchange` with the code and its `verifier`:

```json
{ "code": "…40 characters…", "verifier": "…the secret from step 1…", "device_name": "Grace's iPhone" }
```

**201** returns the same body as `/auth/google` (token, user, `is_new_user`). A code works **once**, for **two minutes**, and
only with the verifier of the app that started the sign-in, so an intercepted link is useless. **401** means the code is wrong,
used, expired or the verifier does not match. Limited to 10 requests per minute.

The server needs `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `https://<your-site>/auth/google/app/callback` added as an
authorised redirect URI of the OAuth client in Google Cloud (set `GOOGLE_REDIRECT_URI` when the public address differs from `APP_URL`).
### `POST /auth/password/code` and `POST /auth/password/reset`

"Forgot password" inside the app. `POST /auth/password/code` with `{ "email" }` emails a 6-digit code (valid 15 minutes) and always answers
`202` — the same whether or not the address has an account. `POST /auth/password/reset` with `{ "email", "code", "password", "password_confirmation" }`
sets the new password and signs every device out (the app then signs in with the new password). Five wrong codes lock the code; ask for a new one.

### `GET /pages/{terms|privacy}`

The legal pages as blocks the app draws natively: `{ "data": { "slug", "title", "updated", "blocks": [ { "type": "heading|paragraph|bullet", "text" } ] } }`.
No sign-in needed. The text is read from the website's pages, so the two always agree.
### `DELETE /auth/token` 🔒

Revokes the token used for this request.

### `GET /me` 🔒

Returns the signed-in user: profile, preferences, followed churches and unread notification count.

---

## Churches

### `GET /churches`

This endpoint is public and lists active churches. Optional filters: `q` (name or pastor), `city`, `denomination`, `per_page`.

### `GET /churches/{slug}`

This endpoint is public. It returns church details, service times, accepted giving methods and the subscriber count. `contact` (phone, email, street address and social links) is filled in only for subscribers and the church's staff; for everyone else it is `null`. When a token is sent, `is_subscribed` says whether this member follows the church.

```json
{ "data": { "id": 1, "slug": "grace-lira", "name": "Grace Community Church Lira", "denomination": "Pentecostal",
  "location": { "city": "Lira", "district": "Lira", "lat": 2.2475, "lng": 32.9001 },
  "service_times": [ { "name": "Main Service", "day": "Sunday", "time": "10:00 AM – 12:30 PM", "language": "en" } ],
  "giving_methods": ["mtn_momo", "airtel_money", "stripe"], "subscribers": 47, "is_subscribed": true, "web_url": "https://faithfellows.com/churches/grace-lira" } }
```

### `GET /churches/{slug}/page`

Everything a church's page in the app shows besides the details above, in one request. Public: a token adds what the church keeps for its members (members-only sermons, events and notices, and the songs of its channel). Fields: `stats` (`followers`, `sermons`, `events`), `live` and `next_live` (a live service, as in `GET /live`), `sermons` (the 12 newest, with `audio_url` and `video_url`), `events` (the next 10), `campaigns` (active, with `raised`, `goal`, `percent`), `devotional`, `updates` (news with `images`), `announcements` (up to 3, urgent first), `prayer.open_count`, `giving.available`, `gallery` (`url`, `thumb`), `socials` (`platform`, `url`) and `channel`: the church's own radio channel (`slug`, `name`, `tagline`, `songs`, `can_listen`) or `null`; `tracks` (`id`, `title`, `artist`, `duration`, `cover_url`, `stream_url`) are listed only when `can_listen` is true (the person follows the church or works for it). `404` for a church that is not active.

### Church join links

Every church has an invite link, `https://faithfellows.com/join/{CODE}` (a code of six characters, `FF`, two digits and two letters, like `FF47KM`; a church can ask for a new one, which retires the old link). The page it opens shows the church and a button that opens the app (`faithfellows://join/{CODE}`; on Android through an `intent://` link that falls back to the download, with the code in the Play Store `referrer`), the download buttons (Super admin → Settings: Android and iPhone links) and the code. The app keeps the code until the person has an account, then follows the church for them. `join_url` is on `GET /churches/{slug}` and on each church of `GET /home` (null until the database update that adds the codes has been applied).

- `GET /join/{code}` (public, 60 a minute): which church a code is. `404` for an unknown code or a church that is not active. The code may be typed in any case. Response: `{ "data": { "code": "FF47KM", "church": { "id", "slug", "name", "logo_url", "tagline", "city", "denomination", "followers" }, "requires_approval": false } }`.
- `POST /join/{code}/follow` 🔒: follows that church, with the source `invite` (it shows as "Invitation" in the church's subscriber figures). `200` with `{ "status": "active" | "pending", "message", "data" }`; a church that approves new members answers `pending`; a member at the limit of 3 churches gets `422` on `church`.
- `POST /join/match` (public, 20 a minute) `{ platform: android|ios }`: for the app opened for the first time after an install. Answers `{ "data": { ...as above } | null }`: the church whose link this phone's address opened in the last 24 hours, only when exactly one church was opened from that address, and only once per opening. It is a guess (many phones share one address), so the app asks the person before using it. The addresses are kept only as a keyed hash and forgotten after 3 days.
- `GET /admin/{church}/invite` 🔒 (any staff): `{ ready, code, url, message, stats: { opened, opened_30, joined, joined_30 } }`.

### Reactions 🔒

`POST /react/{type}/{id}` `{ "type": "like" | "love" | "smile" | "amen" | "pray" | "celebrate" }` presses a reaction on a piece of church content (`type` in the path is one of `post`, `sermon`, `testimony`, `campaign`, `campaign_update`, `prayer`, `announcement`). A person has one reaction per item: pressing another moves it, pressing the same again takes it back. Answers `{ "counts": { "like": 3, "love": 5 }, "mine": "love" | null }`. `403` when the person may not read the item. `like` and `smile` need the database update `2026_01_30_000001`; before it is applied they answer `422` ("Reactions are being switched on"). `GET /announcements/{id}` carries `reactions`: `types` (the three it offers, in order: `[{ "key": "like", "emoji": "👍" }, ...]`), `counts` and `mine`.

### `POST /churches/{slug}/subscription` 🔒

Follows a church. The response is `201` with `{"status": "active" | "pending"}`. The status is `pending` when the church approves new members. A member can follow at most 3 churches at a time (pending requests count). A fourth returns `422` with an error on `church`.

### `DELETE /churches/{slug}/subscription` 🔒

Unfollows the church.

---

## Feed and content 🔒

### `GET /home` 🔒

Everything the app's home screen shows above the feed, in one request (all content is limited to churches the member follows):

```json
{ "data": {
  "slides": [ { "kind": "slide", "image": "…", "image_sm": "…", "title": "Welcome home", "subtitle": "…", "church": { "id": 1, "slug": "grace-lira", "name": "…", "logo_url": "…" } } ],
  "churches": [ { "id": 1, "slug": "grace-lira", "name": "…", "logo_url": "…", "is_live": false } ],
  "live": [ { "…": "live stream (as /live), live ones first" } ],
  "announcements": [ { "id": 7, "title": "…", "body": "short text", "urgent": true, "published_at": "…", "church": { "…": "…" } } ],
  "events": [ { "…": "next upcoming events (as /events)" } ],
  "devotional": { "id": 3, "title": "…", "scripture": "Psalm 119:105", "scripture_text": "…", "excerpt": "…", "publish_date": "2026-10-12", "church": { }, "web_url": "…" },
  "campaigns": [ { "id": 2, "title": "…", "goal": 150000000, "raised": 22700000, "percent": 15.1, "currency": "UGX", "givers": 40, "ends_at": null, "cover_url": null, "church": { }, "web_url": "…" } ],
  "sermons": [ { "…": "latest sermons (as /sermons)" } ]
} }
```

`slides` are the churches' banner photos taking turns between the churches (a church needs at least 4 photos). A church without enough
photos falls back to its cover photo (`"kind": "cover"`), so a member who follows any church always gets a banner. Announcements list
urgent ones first, `devotional` is the newest from the last 7 days (or `null`), and ended campaigns are left out.
### `GET /feed?before=<ISO time>`

Returns the home feed across every church the member follows, newest first. Each item has `type` (`post`, `sermon`, `devotional`, `announcement`, `event`, `live`, `campaign_update`, `prayer` or `testimony`), `at`, `church` (with `logo_url`) and an `item` payload. Beyond `id`, `title` and `body`, the payload carries what a card needs: `scripture` / `scripture_text` / `web_url` for devotionals, `urgent` for announcements, `images` (up to 4 URLs) for posts and `author` for testimonies (`null` when anonymous). To fetch the next page, pass `before=<next_before>`.

```json
{ "data": [ { "type": "sermon", "at": "2026-10-11T10:00:00+03:00", "church": { "id": 1, "slug": "grace-lira", "name": "…" },
  "item": { "id": 12, "title": "Faith that works", "preacher": "Rev. Daniel Okello", "audio_url": "…", "duration_seconds": 2710 } } ],
  "next_before": "2026-10-04T08:12:00+03:00" }
```

### `GET /sermons?church=<id>&q=<text>` and `GET /sermons/{id}`
`GET /sermons` also takes `preacher=<exact name>`, `series=<id>`, `page` and `per_page` (up to 50); each sermon carries `series_id` and `listens`. `GET /sermons/browse` is the Sermons page in one request: `{ featured, preachers, latest, popular, series, total }`: `featured` is up to six sermons by different preachers (the ones with a real picture first, then the most listened; newest sermons fill it up to three when few preachers are named), `preachers` is `[{ name, sermons, latest_at }]`, `latest` the ten newest, `popular` the eight most listened, `series` is `[{ id, title, sermons, cover_url, church }]`; everything is limited to what the member may see.


Lists or shows sermons. Members-only sermons appear only for churches the member follows. Each sermon includes `audio_url`, `video_url` (an embed or file URL) and `thumbnail_url`.

### `GET /events?church=<id>` and `GET /events/{id}`

Upcoming published events.

### `GET /live`

Services that are live now or scheduled, for the member's churches. Open `watch_url` in a web view to get the HLS or audio-only player.

---

### Events: seats, tickets and questions

`GET /events/{id}` now carries everything the event screen needs: `description`, `agenda`, `speakers`, `tickets` (`id`, `name`, `price`, `remaining`, `on_sale`),
`seats_left`, `going_count`, `qa_enabled`, `questions`, `is_past` and `my_rsvp` (`null`, or `{ id, status, quantity, amount, code, qr, ticket, checked_in }`).
`code` and `qr` (the text the door scanner reads) appear only once the seat is confirmed.

- `POST /events/{id}/rsvp` `{ quantity?, status?: going|maybe, event_ticket_id? }` → `201` with the RSVP and `next.action`: `done` (free, seat confirmed) or `pay` (paid event: seat held until paid).
- `DELETE /events/{id}/rsvp` cancels the reservation.
- `POST /rsvps/{id}/pay` `{ method, phone? }` pays for a held seat; same answer as giving (`data` is the donation, `next.action` is `approve_on_phone` or `redirect`).
- `GET /tickets` lists your upcoming tickets (going, or held and waiting for payment).
- `POST /events/{id}/questions` `{ body }` sends a question to the speakers (only when `qa_enabled`).

Every event, sermon, devotional and campaign has a `cover_url` (sermons: `thumbnail_url`): the church's own photo, or a generated picture that fits the title
(`/art/{kind}/{id}`), so there is never an empty image.

### Devotionals, announcements and campaigns

- `GET /devotionals` (newest first, paged) and `GET /devotionals/{id}` (`body` and `prayer` as plain text, `audio_url`, `cover_url`).
- `GET /posts/{id}` → one church update with **all** its photos (`images`), `body`, `church`. `404` unless it is published and the member may see it (members-only updates stay with the church's members). The feed carries the first few photos of each update.
- `GET /announcements` and `GET /announcements/{id}` (`body` in full, `urgent`, `image_url`).
- `GET /campaigns?church=<id>` and `GET /campaigns/{id}`: `goal`, `raised`, `percent`, `givers`, `days_left`, `is_open`, and on the detail `milestones`, `updates`, `leaderboard` (opted-in, never anonymous), `recent_givers` and `comments`.
- `POST /campaigns/{id}/comments` `{ body }` adds a word of encouragement.

### Live services

- `GET /live/{id}`: `status`, `chat_enabled`, `viewers`, and `playback` (`video` / `audio` are HLS playlists, `embed` is a YouTube or Facebook page; all `null` unless the service is live) plus `recording` when the church kept one.
- `POST /live/{id}/heartbeat` (every ~30 s while watching) → `{ viewers, status }`.
- `GET /live/{id}/messages?after=<id>` → `{ data: [ { id, name, body, at, mine } ], hidden: [ids] }`; `POST /live/{id}/messages` `{ body }` (only while live).
## Giving 🔒

### `POST /churches/{slug}/give`

```json
{ "amount": 20000, "category": "tithe", "method": "mtn_momo", "phone": "0772123456", "is_anonymous": false }
```

- `method` is one of the church's `giving_methods`: `mtn_momo`, `airtel_money`, `iotec` or `stripe`.
- `category` is one of `tithe`, `offering`, `project`, `building_fund`, `missions`, `welfare` or `special`. Omit it when you pass `campaign_id`.
- `phone` is required for mobile money. The payment goes through ioTec Pay: its prompt on the giver's phone says "Gift to <church> — <category>" and the payment in ioTec carries the church and the reference. The response's `next` has `gateway` (`iotec` when ioTec Pay carries it) and a `message` naming the church. `GET /churches/{slug}/giving` sends each method's `phone_pattern` (a regular expression) so the app checks the number the same way the server does; while ioTec's test mode is on (`IOTEC_SANDBOX=true`, local machines only) it also accepts ioTec's test numbers such as `0111777771`.

**201**

```json
{ "data": { "reference": "FF-8Q2MZ4", "status": "processing", "amount": 20000, "currency": "UGX", "method": "mtn_momo", "church": { "…": "…" } },
  "next": { "action": "approve_on_phone", "redirect_url": null, "message": "Approve the payment on your phone", "poll_url": "https://faithfellows.com/api/v1/giving/FF-8Q2MZ4" } }
```

How to handle each `next.action`:

- `approve_on_phone`: show "Check your phone and enter your PIN", then poll `poll_url` every 3–5 seconds for up to 2 minutes.
- `redirect`: open `redirect_url` (card checkout), then poll.
- `done`: the gift is already confirmed.

### `GET /giving/{reference}`

Returns the gift status: `pending`, `processing`, `succeeded`, `failed` or `refunded`. While a gift is pending, the server re-checks with the gateway on each call. After success, `receipt_url` links to the PDF receipt (requires a web session).

### `GET /giving`

The member's giving history, paginated.

---

### More giving

- `GET /churches/{slug}/giving`: what the give screen shows — `methods` (`type`, `label`, `needs_phone`), `offline` (bank, cash, with instructions), `categories`, open `campaigns`, `quick_amounts`, `saved_phone`, `diaspora`.
- `POST /churches/{slug}/give` also accepts `recurring` (`weekly|monthly|quarterly`; the first gift is charged now), `is_anonymous`, `show_on_leaderboard`, `note`, `campaign_team_id`, `pledge_id`.
  A card payment returns `next.action = "redirect"` with a secure checkout address (the app opens it in an in-app browser sheet); afterwards the card page returns to `/pay/return/{reference}`, a plain "back to the app" page.
- `GET /giving/summary`: this year's total, per church, open `pledges` and `recurring` gifts. `GET /giving?year=` lists gifts.
- `GET /giving/{reference}/receipt`: the receipt as data (number, amount, what for, how paid, church), once paid.
- `POST /churches/{slug}/pledges` `{ amount, frequency, installments, campaign_id?, … }`, `DELETE /pledges/{id}` cancels one, `PUT /recurring/{id}` `{ status: active|paused|cancelled }`.
## Prayer wall 🔒

### `GET /prayer?status=open|answered`

Lists requests the member may see. Pastor-only requests appear only to their author and the pastoral team. Each item includes `prayed`, which is true when this member has already prayed.

### `POST /prayer`

```json
{ "church_id": 1, "title": "Healing", "body": "Please pray for my mother…", "visibility": "church", "is_anonymous": false, "chain_enabled": true }
```

`visibility` is `public`, `church` or `pastor`. Public and church requests are created with `status: "pending"` and appear on the wall only after a pastor approves them. Until then only the author and the pastoral team can see them, and nobody can pray on them yet. Pastor-only requests are never shared; the pastor's private reply is shown to the author in the app.

### `GET /prayer?status=open|answered|mine`, `GET /prayer/{id}`, `POST /prayer/{id}/answered`, `DELETE /prayer/{id}`

`status=mine` lists your own requests in every state (including those waiting for a pastor's approval). The detail shows `chain_enabled`, your pastors' reply
(`pastor_response`, only to the author) and whether you prayed. `POST …/answered` `{ answer_note? }` and `DELETE` are for the author only.
### `POST /prayer/{id}/pray`

Toggles "I prayed" and returns `{"prayed": true, "count": 15}`.

---

## Bible, notes and reading plans 🔒

### The Bible

- `GET /bible` → `translations` (`key`, `name`, `short`, `language`), the member's `translation`, the 66 `books` (`code`, `name`, `chapters`, `testament`, `aliases` — the abbreviations people type, so the app can read references offline), highlight `colors` and the latest `highlights`.
- `GET /bible/{book}/{chapter}?t=<translation>` (book is the 3-letter code, e.g. `JHN`) → `verses` as `[ { v, t, color } ]` (`color` is the member's highlight: `yellow|green|blue|pink|null`), `previous` / `next` chapter. `503` with a friendly message when the Bible text service is unreachable (chapters are cached after the first read).
- `GET /bible/passage?ref=Matthew 3-5` (also `John 3:16`, `Genesis 1-2; Psalm 1`) → `sections` per chapter. `404` when the reference is not understood.
- `POST /bible/highlights` `{ verses: ["JHN.3.16", …], color: "yellow" | null }` (null clears), `GET /bible/highlights` lists them with verse text where the chapter is cached. Sending the same highlight again is harmless (the app replays highlights made offline).
- `PUT /bible/translation` `{ translation }` saves the member's choice.
- `GET /bible/download/{book}?t=<translation>&have=1,2,3` → a whole book for reading offline: `chapters` as `{ "4": [ { v, t } ] }` (only those not in `have`), `missing` (chapters the server does not hold yet), `total` and `fetched`. The server answers at once with what it has and fetches up to five more chapters from the Bible provider each time, so the app asks again until `missing` is empty (throttled to 120 a minute). The app keeps what it receives on the phone; `php artisan bible:warm kjv` fills the server's cache in advance (about 45 minutes per translation).

### Notes

One note per thing being read (`context`: `bible:JHN.3`, `sermon:12`, `devotional:7`, `plan:4:2`, or a free `note:…`). `POST /notes` with a `context` is create-or-update, and the app makes up the `context` of a free note itself (`note:` + random letters), so a note written offline can be sent again safely and keeps its identity. `GET /notes?q=&kind=` (pinned first, paged), `GET /notes/context?context=`, `POST /notes` (creates or continues the note of that `context`; without one, a new free note), `GET|PATCH|DELETE /notes/{id}`, `POST /notes/{id}/pin`. Notes are private; another member gets `404`.

### Reading plans and circles

- `GET /reading-plans` → `mine` (with `progress`: `percent`, `next_day`, `streak`…), `available` (published plans of the member's churches) and `circles`.
- `GET /reading-plans/{id}?day=` → the day (`passages`, `reflection`, `done`), the `days` strip and the member's circles for the plan. `POST …/start`, `POST …/complete` `{ day }`, `DELETE …` (leave).
- Reading circles (up to six members from one church: the owner and five partners): `POST /reading-plans/{id}/circles` `{ name, reading_time: "06:00", reading_days: [0-6] }`, `POST /reading-circles/join` `{ code }`, `GET /reading-circles/{id}?day=` (members, who has read, their private summaries), `POST /reading-circles/{id}/days/{day}` `{ summary? }`, `PUT /reading-circles/{id}` (owner), `DELETE /reading-circles/{id}/leave`.
## People, groups, games and messages 🔒

### My profile

`GET /me` also returns `is_super_admin` (true for the platform owner, who can open any church's admin tools), `features` (`{ podcasts, hymns, radio }`, each true or false: which platform features this person sees, see "Who sees podcasts, hymns and radio" below), `bio`, `city`, `country`, `gender`, `date_of_birth`, `interests`, `directory_opt_in`, `open_to_connect`, `unread_messages` and `connection_requests`.
`PATCH /me` `{ name?, nickname? (2–30 characters, no phone number or address; empty goes back to the first name), phone?, country?, city?, gender?, date_of_birth?, bio?, interests?: [..8 words], directory_opt_in?, open_to_connect? }` saves them — turning either
"find me" switch on needs a birth date of someone aged 18 or over (`422` on `date_of_birth` otherwise). `POST /me/avatar` (multipart `avatar`), `DELETE /me/avatar`,
`PUT /me/password` `{ current_password, password, password_confirmation }` (other phones are signed out).
`DELETE /me` `{ email }` deletes the signed-in member's account (the member confirms by typing their own email address; `422` on `email` if it is not theirs, `403` with a message for church owners and platform accounts, who must contact support). Personal data is removed and the token stops working; gifts and receipts stay without a name. Same rules as the website's Delete account and the public page `/delete-account`.

### Finding and connecting with people

Only adults with a birth date can be found or connect. A member is found by people of their own churches when `directory_opt_in` is on, and by every other adult on the
app when `open_to_connect` is on. Cards carry an `age_range` bracket (`18–24`, `25–34`…), never a birthday, and no email or phone.

- `GET /people?scope=church|nearby|everyone&radius=5|10|25|50|100|200&church=&q=&country=UG&city=&gender=&interest=&age_min=&age_max=` → `nearby` lists people closest to you first (needs your location, see below) and each card then carries `distance_label` (`Within 5 km`, `About 10 km away`… never exact); the other scopes leave it `null`.  paged `data` of `{ id, name, avatar_url, bio, city, country, gender, age_range, interests, shared_churches, relation }` (`relation`: `none`, `pending_sent`, `pending_received`, `connected`) and `viewer: { can_connect, discoverable, has_location }`.
- `GET /people/{id}` the same card for one person (`404` when they cannot be found by you).
- `POST /people/{id}/connect` `{ message? }` ("Say Hey"); asking someone who already asked you connects you. At most 25 requests wait at a time.
- `GET /connections` → `{ friends, incoming, outgoing }`; `POST /connections/{id}/respond` `{ action: accept|decline }`; `DELETE /people/{id}/connection` cancels a request or disconnects.
- `POST /people/{id}/block`, `DELETE /people/{id}/block`, `GET /people/blocked`: blocking works both ways, removes the connection and hides each from the other.

### Near me

`PUT /me/location` `{ latitude, longitude }` saves the member's approximate area; the server rounds it to about a kilometre (two decimals) and never shows it to anyone, other members only see the bucketed distance. `DELETE /me/location` forgets it. `GET /me` returns `has_location`. A member who has not shared a location gets `422` on `scope=nearby`.

### Keeping talk inside the app

Phone numbers, email addresses and links that open WhatsApp, Telegram, Signal or Messenger (also written with spaces, dots, words such as "zero seven seven", or `+256…`) are refused with `422` on the field that carried them (`body`, `message`, `bio`, `title`, comments, group posts, prayer requests…). Church admins see a member's contact in the admin area; members never see each other's. A gift's phone number goes to the payment network and the church's admin screens only.

### Messages

Only between connected members. **One message until they reply:** after you send the first message to someone who has never written to you, `POST /messages/{userId}` answers `422` on `body` ("Wait for … to reply…") until they answer once; after that you can talk freely. The thread's `partner.waiting_for_reply` says which state you are in. `GET /messages` (conversations with `unread`), `GET /messages/unread` → `{ unread }`, `GET /messages/{userId}?after=<id>|before=<id>` (a thread, oldest first; polling with `after`; marks theirs as read), `POST /messages/{userId}` `{ body }`.
Notifications for requests carry `kind` (`connection_request`, `connection_accepted`) and `ref` (the person's id).

### Study groups

- `GET /groups?type=&q=` → `{ mine, pending, discover, types }` (cards with `cover_url`, `schedule`, `members_count`, `my_status`, `my_role`). `GET /groups/{id}` adds `members`, `announcements`, `meetings`, `can_lead`, `pending_members`.
- `POST /groups/{id}/join` (a request the leader approves), `DELETE /groups/{id}/leave`.
- The group's conversation: `GET /groups/{id}/posts?after=|before=`, `POST …/posts` `{ body }`, `DELETE …/posts/{postId}` (author or leader). Only active members and leaders may read and write.
- Leader tools: `GET …/candidates?q=`, `POST …/members` `{ user_id }`, `PUT …/members/{membershipId}` `{ action: approve|remove|member|assistant|leader }`, `POST …/meetings` `{ meeting_date, topic?, visitors?, present: [userIds] }`, `POST …/announcements` `{ title, body }`.

### Quiz games

Two kinds of game. A **church's game** (hosted from the admin API, on a screen in church) is *live*: the host moves everyone along together. A game a **member starts** (`POST /games/start`) is *self-paced* (`mode: "self"` in the state): every player has their own place in it, so someone who joins late, even days later, starts at the first question; answering shows the result at once and moves on after a moment (or at once with `POST /games/{id}/next`), and a question nobody answers closes by itself when its time is up. Every finished run counts in the quiz's records. A game room stays open to friends with its PIN for 7 days.

`GET /games` → `{ live, recent, mine }`, `POST /games/join` `{ pin }` → `{ data: { id } }`, `GET /games/{id}` the game's state (poll about once a second: `status` `lobby|question|reveal|finished`, the question and its time left, the live `leaderboard`, `me` with score and rank, and — only after the reveal — which option was right), `POST /games/{id}/answer` `{ option, question }`.
## Notifications 🔒

### Push to the phone

The app asks permission, gets an Expo push token and registers it; the server then pushes church news, connection requests and chat messages to every phone the member has the app on (up to eight), through Expo's push service. "Push" in a member's settings covers the browser and the phone alike.

- `POST /devices` `{ token: "ExponentPushToken[…]", platform: "ios|android", name?, app_version? }` → `{ registered: true }` (`201` new, `200` known). A token belongs to one phone: signing in as someone else on it moves it.
- `DELETE /devices` `{ token }` when the member signs out or turns notifications off on the phone.
- `POST /devices/test` → `{ devices, sent }`: a test notification to the member's own phones (5 a minute); `422` when no phone is registered.
- What a push carries: `title`, `body`, `subtitle` (the church), the app badge (unread notifications plus unread messages) and `data` the app opens when tapped: `url` (a website address the app maps to its own screen), or `kind` + `ref` for `post`, `message` (`ref` = the sender), `connection_request` and `connection_accepted`. Android channels: `default`, `messages`, `live`, `urgent`.
- Chat pushes are sent at most once a minute per conversation, never in quiet hours, and only when the member keeps "People & messages" on push.

### Settings

- `GET /notification-settings` → `categories` (`key`, `label`, `push`, `email`), `quiet_hours` (`{ start, end }` or null), `digest_mode`, `churches` (`id`, `name`, `muted`) and `devices` (phones registered).
- `PUT /notification-settings` `{ categories?: { announcement: { push, email } }, quiet_hours?: { start: "22:00", end: "06:00" } | null, digest_mode?, muted_churches?: [ids] }` → the new settings. These are the member's choices for every church (the website's "all churches" row); a church-specific choice made on the website still wins for that church.

### `GET /notifications?unread=1`

```json
{ "data": [ { "id": "9b1e…", "category": "live", "church_id": 1, "title": "Grace Community Church Lira is live", "body": "…", "url": "…", "read": false, "created_at": "…" } ],
  "meta": { "current_page": 1, "last_page": 3, "unread": 4 } }
```

### `POST /notifications/read`

Marks everything as read, or only the given notifications when you pass `{"ids": ["9b1e…"]}`. Returns the new unread count.

## Church staff 🔒

Everything a pastor or church worker does in the app. The rules are the website's: the same permissions (`church.staff` for any staff role, then `church.can:<permission>` per tool), the same validation and the same notifications. A user who is not staff of that church gets `403`, and so does a role that may not use the tool. `{church}` is the church's slug, and every record is looked up inside that church (another church's id is a `404`).

- `GET /admin/churches` → the churches the signed-in user is staff of, each with `title`, `is_admin` and `permissions`. `GET /me` also returns `staff_of`, which is what makes the app show "Manage …" on the Me tab.
- `GET /admin/{church}/dashboard` → `stats` (members, new this month, pending members, pending and private prayers, open reports, upcoming events, giving this month), the live service, the next event and the latest pending prayers. A figure the role may not see is `null`.

| Permission | Endpoints |
|---|---|
| `content.manage` | `GET/POST /announcements`, `DELETE /announcements/{id}`; `GET /posts`, `POST /posts` (multipart: `body?`, `visibility: public|members`, `images[]` up to six, `published_at?` to schedule; a photo alone is enough), `PUT /posts/{id}` `{ body?, visibility?, is_pinned?, status?: published|hidden }`, `DELETE /posts/{id}`; `GET/POST /devotionals`, `DELETE …/{id}`; `GET/POST /sermons`, `DELETE …/{id}`. Publishing notifies followers, as on the website. |
| `events.manage` | `GET/POST /events`, `GET/PUT/DELETE /events/{id}` (`show` adds the stats, the guest list and everything the edit form starts from: `starts_local` / `ends_local` as `Y-m-d H:i`, `online_url`, `tickets`; a `PUT` leaves tickets alone unless `tickets` is sent, and a cover picture goes in a multipart `PUT` with `_method=PUT`), `POST /events/{id}/check-in` `{ code }` — a ticket code, or the whole URL a QR ticket holds (120 a minute). |
| `prayer.moderate` | `GET /prayer?status=pending\|private\|wall\|declined\|hidden`, `POST /prayer/{id}/approve`, `…/decline` `{ note? }`, `…/respond` `{ response }`, `…/hide`. |
| `members.manage` | `GET /members?status=&stage=&q=` (default: active and waiting), `PUT /members/{subscriptionId}` `{ action?: approve\|block\|unblock, stage? }`. |
| `notifications.send` | `GET /broadcasts` (the last 30 and the categories), `GET /broadcasts/estimate?audience=&stages[]=` (how many phones), `POST /broadcasts` `{ title, body, category, is_urgent?, send: now\|later, scheduled_at?, audience: members\|staff\|group\|stage, stages?, small_group_id? }` (10 a minute). |
| `community.moderate` | `GET /reports?status=open\|actioned\|dismissed`, `POST /reports/{id}/hide`, `…/dismiss`. |
| `quizzes.manage` | `GET/POST /quizzes` `{ title, questions: [{ question, options[], correct }] }`, `GET/PUT/DELETE /quizzes/{id}` (`PUT` takes the same body and updates questions in place so finished games keep their answers; `422` while a game is being played with the quiz), `POST /quizzes/{id}/host` `{ notify? }`, `GET /quiz-sessions/{id}` (host view: PIN, players, the question and answers per option, leaderboard; poll once a second), `POST /quiz-sessions/{id}/start\|reveal\|next\|end\|cancel`. |
| `live.manage` | `GET/POST /live` `{ title, scheduled_at, source_type, external_url?, chat_enabled }`, `POST /live/{id}/start`, `…/stop`. |
| `giving.view` | `GET /giving/summary` (today, week, month, year, by category and method, pending), `GET /giving` (latest gifts). |

Messages sent from the app (`POST /broadcasts`) go out as push and email, as each member prefers (never SMS or WhatsApp, which cost money).

Left on the website on purpose: recording cash gifts, refunds and payouts, roles and billing, the church profile and slider photos, the payout set-up, and bulk imports and exports.

## The super admin managing any church 🔒

A super admin is not on any church's staff but holds every permission in every church. On the website, Super admin, Churches, **Manage** (or **Manage this church** on a church's page) opens that church's admin area (`/church/{slug}/admin`) with a bar that says whose church it is and a button back; each visit is written to the audit log (`church.managed`). In the app, `GET /admin/churches` returns every active church for a super admin (`?q=` finds by name or town, 40 at most) with title "Super admin", and all `/admin/{church}/...` calls work. Everyone else still gets only the churches they work for.

## Who sees podcasts, hymns and radio 🔒

Podcasts, the hymn book and the gospel radio belong to the whole platform. The super admin decides, church by church (Super admin, **App features**), whose members see them; everything is on for every church until it is switched off.

- A person with **no church yet** (a new sign-up) always sees all three. A super admin too.
- Someone who follows or works for churches sees a feature when **at least one** of those (active) churches has it on.
- `GET /me` returns `features: { podcasts, hymns, radio }`; the app hides the tiles that are `false`.
- The server enforces it: `/podcasts*`, `/podcast-episodes/*`, `/hymnals*`, `/hymns*` and `/radio` answer `403 { "message": "Podcasts are not switched on for your church yet.", "feature": "podcasts" }` for someone who does not see that feature.
- Website, super admin: `GET /super-admin/features` (every church with three switches), `PUT /super-admin/features` (save the page: `ids[]` and `on[podcasts|hymns|radio][]`), `POST /super-admin/features/all` `{ feature, value }` (one feature on or off for every church). Changes are written to the audit log.

## Gospel radio 🔒

One station for the whole app. The super admin adds the songs at `/super-admin/radio` on the website (the title, artist, length and cover are read from each file); the app plays them round the clock in a loop, and everyone hears the same song at the same moment because "now playing" is worked out from the clock.

- `GET /radio` → `{ enabled, name, tagline, songs, server_time, now }`. `now` is `null` when the radio is off or has no songs; otherwise `{ track, offset, started_at, ends_at, upcoming: [4 songs with starts_at], recent: [5 songs with played_at] }`. A song is `{ id, title, artist, duration, cover_url, stream_url }`. The app plays `track` from `offset` seconds, moves on through `upcoming` by itself, and compares with the clock each time a song starts.
- `GET /radio/stream/{id}` (not under `/api`, no sign-in) streams a song with byte-range support, so a phone can start part-way into it. `GET /radio/cover/{id}` is the cover picture; a song without one gets a drawn worship scene (`/art/radio/{id}`).
- Website (super admin): `GET/POST /super-admin/radio` (upload, one song per request), `PUT /super-admin/radio/{song}` (title, artist, on or off), `POST /super-admin/radio/{song}/move` `{ direction: up|down }`, `DELETE /super-admin/radio/{song}`, `PUT /super-admin/radio/settings` `{ name, enabled }`. From the command line: `php artisan radio:add "song.mp3" --title= --artist=`.

### Radio channels

The radio is now several channels, each playing round the clock in a loop, the same for everyone at the same moment: the platform's **Gospel radio** (songs) and **Podcast radio** (teaching, filled by the super admin), and one channel for **each church** (filled by the church). `GET /radio` is still the Gospel radio, exactly as above.

- **A church has two channels** (`church-{id}-songs`, `kind: music`, its radio of songs; and `church-{id}`, `kind: talk`, its podcast channel). `GET /radio/stations` lists them **church first**: the member's church songs radio, then the Gospel radio, then the church podcast channels, then the Podcast radio, so the app's Home radio plays the church's songs by default and the Gospel radio only while the church has none (a channel with nothing on is not listed, and asking for an empty songs radio answers the Gospel radio with `fallback: true`). `GET /churches/{slug}/page` has `radio` (the songs radio) next to `channel` (the podcast channel), the same shape.
- **Broadcast to everyone.** When the super admin broadcasts a track, every channel answer (`GET /radio`, `GET /radio/stations/{slug}`) has `now.track` = that track from its start (with `offset`, empty `upcoming`) and `broadcast: { title, message }` (null otherwise); when the track ends the channel is back on its own clock. Phones moving to it is the same re-check that follows Play now (every ~10 s while playing).
- `GET /radio/stations` → the channels this person can hear, Gospel radio first: `[{ id, slug, kind: music|talk, name, tagline, church: {id, name}|null, songs }]`. Only channels that are on and have something in them. A church's channel (`slug: church-{id}`) is listed for the people who follow that church or work for it.
- Every channel answer (`GET /radio`, `GET /radio/stations/{slug}`) has `scheduled: { track, starts_at } | null`: the next song an admin has scheduled to go on air ("Don Moen Nonstop at 4 pm"). At that time `now` changes to it by itself; the app polls every 10 seconds while playing, so listeners move to it within seconds.
- `GET /podcasts/overview` also returns `sections: [{ key, label, count, podcasts: [podcast cards] }]`: a shelf for each category that has approved podcasts, Faith and Motivation first, up to six newest per shelf and the total in `count` (the list of categories is `faith`, `motivation`, `teaching`, `sermons`, `devotional`, `prayer`, `worship`, `testimonies`, `family`, `youth`, `kids`, `leadership`, `missions`, `interviews`).
- **Comments** (podcast channels and episodes; the music radio has none): `GET /radio/stations/{slug}/comments` and `POST /radio/stations/{slug}/comments` for the Podcast radio (`podcast`) or a church's channel (`church-{id}`, people of that church only); `GET /podcast-episodes/{id}/comments` and `POST …` for an episode. `GET` takes `per_page` (up to 30) and `page` and answers `{ data: [{ id, body, created_at, mine, can_delete, author: { id, name, avatar_url } }], meta: { total, current_page, last_page } }`, newest first; `POST { body }` (2 to 600 characters, no contact details) answers `201` with the comment. `DELETE /podcast-comments/{id}` (the author, the podcast's maker or a church's staff, the super admin) and `POST /podcast-comments/{id}/report { reason: spam|abuse|inappropriate|false_information|other, details? }` (a reported comment is hidden until a moderator decides).
- `GET /radio/stations/{slug}` (`gospel`, `podcast` or `church-{id}`) → the same shape as `GET /radio` for that channel (`now` is `null` when it is off or empty), plus `id`, `slug`, `kind` and `church`. `404` for a church channel the person does not follow.
- Website, super admin: `GET /super-admin/radio?station=gospel|podcast` (the page for a platform channel); the upload (`POST /super-admin/radio/chunk`, in 4 MB pieces: `upload_id`, `index`, `total`, `name`, `chunk`, `station`), `PUT /super-admin/radio/settings` (`station`, `name`, `enabled`) and `PUT /super-admin/radio/channels/{id}/block` (switch a church's channel off or back on; the church cannot undo it).
- Website, church (permission `media.manage`): `GET /church/{church}/admin/radio` (the church's channel is made the first time the page opens), `POST …/radio/chunk` (pieces, up to 150 MB per file, counted in the church's storage), `PUT …/radio/settings` `{ name, enabled }`, `PUT …/radio/{id}`, `POST …/radio/{id}/move`, `DELETE …/radio/{id}`.
- Files are streamed by `GET /radio/stream/{id}` as before. Until the database update that adds channels has been applied, only the Gospel radio exists.


---

## Hymn book 🔒

Hymnals and hymns are added by the super admin on the website (`/super-admin/hymnals`); members read them. Only published hymnals and hymns are returned; anything else is `404`.

- `GET /hymnals` → `{ hymnals: [{ id, name, slug, language, language_label, denomination, description, hymns_count, source_name, source_url }], favorites, languages: [{ key, label }] }`.
- `GET /hymnals/{id}?q=&theme=` → the hymnal card plus `sections: [{ name, meaning, count }]` (the parts of a printed book, in book order, only parts that have hymns; empty for a book without parts), `themes: [{ name, count }]` and `hymns: [{ id, number, section, title, first_line, author, themes, has_audio, favorite, hymnal, search }]` (no lyrics; in book order, part by part; `search` is the lower-case words, so the app can filter instantly and offline).
- `GET /hymnals/{id}/download` → the same with every hymn in full (below) and `downloaded_at`, to keep on the phone.
- `GET /hymns/{id}` → a hymn: `section` and `section_meaning` (the part of the book, e.g. "Ez’Amayingira" / "Entrance hymns"), `sections` in singing order (`[{ type: verse|refrain|text, label, lines: [] }]`, the refrain after every verse; a `text` block is printed as it is in the book, with no verse number and no refrain after it, `label` is its caption or empty), `credits: [{ label, value }]` (Part of the book, Words or Words and music, Translated by, Music, Setting, Copyright, Text collected from; "Not named in the book" when a printed book names nobody), `credit_note`, `translator`, `composer`, `arranger`, `tune`, `meter`, `key_signature`, `year_written`, `scripture`, `copyright_status`, `copyright_label`, `source_url`, `audio_url`, `sheet_music_url`, `share_text` (the whole hymn with credits, for WhatsApp), `favorite`, `prev_id`, `next_id`.
- `GET /hymns/search?q=&hymnal=&language=` → up to 40 hymn cards: `q` is a number, a title, a first line, an author or any words.
- `GET /hymns/favorites`, `POST /hymns/{id}/favorite`, `DELETE /hymns/{id}/favorite` → `{ favorite, favorites_count }`.
- Website, super admin: `GET/POST /super-admin/hymnals`, `…/{hymnal}` (list of its hymns, edit, update, delete), `…/{hymnal}/hymns` (create, edit, update, delete). Lyrics are typed as paragraphs separated by an empty line; a paragraph starting `Refrain:` or `Chorus:` is the refrain. The author of the words is required; copyright is `public_domain`, `licensed`, `original` or `hymnal` (from a printed hymn book; the only status that may have no named author); audio and sheet-music links must be https. A hymnal can have parts ("Parts of the book": one per line, `Name | meaning`), each hymn is put in a part, and a number can repeat across parts but not inside one. Blocks that are printed as they are start with `Text:`, `Intro:`, `Coda:`, `Note:`, `Response:` or `Call:`.
- Load the classic hymnal with `php artisan db:seed --class=HymnSeeder` (safe to run again; it only adds what is missing).
- Load a whole hymnal from a JSON file with `php artisan hymns:import path/to/book.json` (`--hidden` keeps a new hymnal out of the app until it is published). Running it again with a corrected file updates the same hymns (found by hymnal, part and number). The file is `{ "hymnal": { name, slug?, language, denomination?, description?, source_name?, source_url?, sort?, sections: [{ name, meaning }] }, "hymns": [{ section?, number, title, author?, composer?, translator?, arranger?, tune?, meter?, themes?, copyright_status?, credit_note?, source_url?, verses: [{ number, lines: [] } | { type: "text", label, lines: [] }], refrain?: { lines: [] } }] }`. Hymns with no title or no words are skipped and counted. Languages: `en`, `lg` (Luganda), `laj` (Lango), `ach` (Acholi), `luo`, `teo`, `nyn`, `cgg`, `xog`, `myx`, `alz`, `lgg`, `kdj`, `sw`, `fr`.

## Home 🔒

`GET /home` also returns: `verse` (the verse of the day in the member's translation, `null` when the Bible service cannot be reached), `top_sermons` (the two most listened sermons, each a sermon with `listens`), `prayers: { items (the newest three open requests, with `prayed`), open_count }` and `quiz: { live, played, best_score }`. `POST /sermons/{id}/listen` counts a listen (once per member every half hour) and returns `{ listens }`.

## Podcasts 🔒

Faith-based podcasts made by members on the website (`/podcast-studio`). A podcast is listed in the app only after the super admin approves it; episodes are links to audio kept on a host site. Everything below lists only approved podcasts and episodes that are published and already out; anything else is `404`.

- `GET /podcasts/overview` → `{ categories: [{ key, label, count }], following: [card], new_episodes: [episode], podcasts: [card] }`.
- `GET /podcasts/overview` also returns `ours`: **Our Podcasts**, a block for each church the member follows (up to four; the platform's own podcast collection only when none of their churches has episodes): `{ slug, name, church: {id, slug, name, logo_url} | null, total, episodes: [{ id, title, artist, duration, cover_url, stream_url }] }`, the newest twenty episodes first, to be played on demand one after another (they are the tracks of the church's podcast channel; the live loop is `GET /radio/stations/{slug}`). A church whose channel is empty, switched off or blocked is left out.
- `GET /podcasts?q=&category=&following=1&page=` → paged `data` of cards, `q` searches name, host and summary. A card is `{ id, name, host, summary, category, category_label, cover_url, episodes, followers, latest_at, following }`.
- `GET /podcasts/{id}` → a card plus `description`, `website_url`, `language`, `episodes_count`, `episodes` (the first 30, newest first) and `episodes_last_page`. `GET /podcasts/{id}/episodes?page=` the rest.
- An episode is `{ id, title, summary, audio_url, duration, season, number, published_at, podcast: { id, name, host, cover_url } }`. The app plays `audio_url` directly.
- `POST /podcasts/{id}/follow`, `DELETE /podcasts/{id}/follow` → `{ following, followers }`.
- `POST /podcast-episodes/{id}/played` → `{ plays }`, called when an episode starts.
- Notes while listening use the context `podcast:{episodeId}` (`kind: "podcast"`).
- Website, podcaster: `GET/POST /podcast-studio`, `…/{podcast}` (show, edit, update, delete), `…/{podcast}/episodes` (create, edit, update, delete). The link is checked when it is added: `https` only, no private or local addresses, not a web page, and on one of the super admin's host sites when any are set.
- RSS import (website, podcaster): `GET/POST /podcast-studio/import` `{ feed_url, host_name, category?, faith_based }` makes the podcast and its episodes from a podcast host's RSS feed (https, public address, up to 6 MB and 300 episodes; one feed per podcast), `POST /podcast-studio/{podcast}/sync` checks it now, `DELETE /podcast-studio/{podcast}/feed` disconnects it. `php artisan podcasts:sync-feeds` (hourly in the schedule) checks every connected, pending or approved podcast that was not checked in the last 30 minutes: new episodes are added, changed ones updated, episodes the host no longer lists become drafts. A problem is kept in `feed_error` and shown on the podcast's page.
- Public website: `GET /podcasts?q=&category=` (list), `GET /podcasts/{slug}` (episodes with players), `GET /podcasts/{slug}/embed` (a small player for an iframe on any website; the only page that other sites may frame). Only approved podcasts, and only episodes that are out, are shown.
- Website, super admin: `GET /super-admin/podcasts?status=`, `…/{podcast}`, `POST …/approve`, `POST …/reject` `{ reason }`, `POST …/hide`, `PUT /super-admin/podcasts/hosts` `{ hosts }` (comma separated host sites; empty allows any `https` site). The podcaster gets a notification of each decision.

## Versioning

Breaking changes go to `/api/v2`, and v1 keeps working for at least 12 months after v2 ships. Additive fields may appear at any time, so clients must ignore unknown keys.

## App 1.1 additions 🔒

**Bible versions.** `GET /bible` lists `translations` (`key`, `name`, `short`, `language`, `offline`) and the member's `translation`; `PUT /bible/translation {translation}` changes it. The default is `esv` once the server has `ESV_API_KEY`, otherwise `kjv`. Books come in canon order (the position in the list is the book number, 1 to 66). `GET /bible/{book}/{chapter}` and `GET /bible/passage` also answer `copyright` (shown under the text) and, for a chapter, `keep_offline: false` for licensed text (the ESV), which the app must not save; `GET /bible/download/{book}` has nothing to give for those.

**Community games.** `GET /games/library` (the quizzes of the churches the member follows first, as category `my-church` ("From your church", with `church`), then the 25 ready-made quizzes by category; each with `my_best` and `my_runs` for the signed-in member; a church's quiz is played at the member's own pace like the others, and only its church's members can start it or see its records), `POST /games/start {quiz_id}` (the member becomes host and a player; answers `id` and `pin`), `GET /games/friends?session=<id>` (people played with, and connections, with `invited` / `joined`), `POST /games/{id}/invite {user_ids}`, `POST /games/{id}/begin` (in a self-paced game **each player** starts their own run at question 1, whenever they like; players are told nothing and nobody is waited for; in an older host-paced game the host starts it and everyone joined or invited is notified), `POST /games/{id}/next` (leave the answer screen for the next question; does nothing while a question is open), `POST /games/{id}/cancel` (the host closes the room). In a self-paced game `POST /games/{id}/answer` answers `{ locked, correct, points, data: <the game's state> }`, so the result shows at once; the state has `mode: "self"`, the player's own `index`, `me` (score, rank, streak, `correct_count`, `seconds`) and a `leaderboard` of the game's players, each with `at` (the question they are on) and `done`.

**Records.** `GET /games/records?quiz_id=&period=today|week|all&scope=everyone|church` answers the quiz's best finished run of each person (`rows`: rank, `name`, score, correct, total, seconds, `plays`, `me`), the viewer's own row in `me`, and `players` / `plays` totals. A day starts at midnight and a week on Monday, in the app's timezone (Africa/Kampala). `scope=church` keeps to people who follow a church the member follows. People show by their **nickname** (`nickname` in `PATCH /me`), else their first name; full names are never sent, and people the member blocked (or who blocked them) are left out. `POST /games/join {pin}` accepts a community PIN from anyone; the state at `GET /games/{id}` adds `community`, `is_host` and `host_name`. `GET /games` also lists `invites` and `hosting`. Notifications for an invitation and a start have `kind: "game"` and `ref: <game id>`.

**The app's own pictures.** `GET /app/media` (no sign-in, cached 5 minutes) → `{ data: { opening: [{id, url}], welcome: [{id, url}], banner: [{id, url, title, subtitle}], version } }`: the pictures the platform admin set in Super admin → App media, in order, switched-on ones only. An empty `welcome` or `banner` list means the app uses the photos built into it; an empty `opening` list means the app opens straight to Home, with no opening flash (no photos are built into the app for it). The app fetches the pictures once and keeps them on the phone for the next start.

**How the app opens.** `GET /app/opening` (no sign-in) → `{ data: { enabled, seconds (2–20, default 6), speed (1–10, default 5), title, text } }`: the flash of rolling photos when the app is opened, set in Super admin → Settings. The app keeps the last answer for the next start.

**Paid gifts are found.** `GET /giving` (the giver's history) first asks ioTec about the giver's own gifts that are still waiting (created more than 45 seconds and less than 3 days ago, at most 5, at most every 30 seconds), `GET /campaigns/{id}` corrects the campaign's saved total if it fell behind, and in the church admin the finance pages do the same for the church (`POST /church/{church}/admin/giving/refresh` is the "Check payments now" button), so a gift approved on the phone is counted even when the gateway's callback, the queue worker and the cron job did nothing.

**Churches to follow.** `POST /churches/suggest` `{ latitude?, longitude? }` (both or neither) answers up to 8 churches the member does not follow yet: the nearest to the phone first (within 100 km, with `distance_label`; the place is rounded to about 1 km, used for this answer only and **never saved**), then churches in the member's own city, then their country, then the best known. Each has `id`, `slug`, `name`, `logo_url`, `city`, `denomination`, `followers`, `why` ("Less than 1 km away", "In Lira"…) and `distance_label` (null without a place); the answer also says `located` and how many churches the member `following`. The app shows it as a pop-up to a member who follows none; following is `POST /churches/{slug}/subscription`. `GET /home` lists the member's `churches` with `city`.

**Grow your circle.** `GET /people?scope=recommended|church|nearby|everyone` (`recommended` is ranked and each person has `reasons`; `radius` 5, 10, 25, 50, 100 or 200 for `nearby`). `GET /churches/nearby?radius=` lists churches close to the member's shared place, nearest first, with `distance_label` (`has_location: false` until a place is shared).

**Giving.** `GET /churches/{slug}/giving` adds `reasons` (what the church lets people give for); `POST /churches/{slug}/give` takes `reason_id` instead of `category` (or `campaign_id` for a fundraiser). `GET /campaigns/{id}` lists `recent_givers` (most recent first, 12). Admin: `GET|POST /admin/{church}/giving/reasons`, `PUT|DELETE /admin/{church}/giving/reasons/{id}` (permission `giving.manage` to change), and `GET /admin/{church}/giving/summary` adds `by_reason`, `fees_month`, `net_month`, `givers_month`, `awaiting_payout`.

**Home.** `GET /home` adds `highlights` (slider items with a picture each), `inspiration` (`slot`, `label`, `body`, `reference`) and `podcasts` (the top four); `sermons` falls back to public sermons for a member with no church yet.

**My statistics.** `POST /me/activity {events: [{kind, seconds | count, day?}]}` reports small pieces of activity (kinds: `read`, `radio`, `podcast` in seconds; `chapters`, `share_scripture`, `share_inspiration`, `open` as counts; at most 40 events, an hour of seconds each, days within the last week). `GET /me/stats?period=week|month|all` answers `totals` (minutes read and listened, chapters, shares, opens), `rings` (Bible, Listening and Sharing as percents of gentle goals), `week` (day by day), `streak`, `other` (highlights, notes, games, prayers, given this year, churches, connections), the latest three `notes`, and `profile` (`percent`, `missing`, `phone_missing`). `GET /giving/summary` now also lists the member's `churches`, so the Give tab can name the church they follow.

**Swipe to reply.** `POST /messages/{userId}` and `POST /groups/{id}/posts` accept `reply_to` (the id of an earlier message of the same chat or group; another conversation's message answers `422` on `reply_to`). A message that answers another carries `reply_to`: `{ id, body (the first 140 characters), mine }` in a private chat, and `{ id, body, author, mine }` in a group (`body` is "This message was deleted." when the original was removed). It needs the database update `2026_02_02`; until it is applied chats work as before.

**Grow your circle puts the same church first.** In `GET /people?scope=recommended` a shared church is the strongest tie: those who follow a church you follow come first, with `reasons` such as "Follows Grace Church too", and two members who are in the same country, away from the country of the church they both follow, also get "Also in United Kingdom".

**The platform admin in the app.** Super admins only (`403` for everyone else), under `/platform`: `GET /overview` (churches by status, people, `needs_attention` {applications, podcasts, reports, old_reports}, the radio `broadcast` in progress, `phones`); `GET /churches?status=&q=` and `POST /churches/{slug}/approve`, `…/decline`, `…/suspend`, `…/reactivate`; `GET /users?q=` and `POST /users/{id}/churches {church_ids[], stage?, notify?}` (puts a person into churches at once); `GET /reports?status=open|actioned|dismissed` and `POST /reports/{id} {action: hide|dismiss|suspend}`; `GET /podcasts?status=` and `POST /podcasts/{id} {action: approve|reject|hide, reason}`; `POST /announce {title, body}` (a notification to every phone); `GET /radio` and `POST` / `DELETE /radio/broadcast`. What is hard to undo (decline, suspend, reactivate, suspending a person from a report, announce, start and stop a broadcast) needs the admin's `password` in the body (`422` on `password` when it is missing or wrong; accounts that sign in with Google only have none to ask for). Every action is written to the audit log.
