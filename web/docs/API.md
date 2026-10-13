# FaithFellows API v1

This is a JSON REST API for the FaithFellows mobile app and integrations.

**Base URL:** `https://faithfellows.app/api/v1`

## Conventions

- Send `Accept: application/json` and, for writes, `Content-Type: application/json`. All responses are JSON, including errors.
- **Authentication:** `Authorization: Bearer <token>` (Laravel Sanctum personal access tokens). Tokens expire after 180 days.
- **Rate limits:** 60 requests per minute per user (or IP). Token creation is limited to 10 per minute, plus 5 failed sign-ins per email/IP per minute. Giving is limited to 10 per minute. A `429` response includes `Retry-After`.
- **Pagination:** list endpoints return Laravel's `data`, `links` and `meta` envelope. Use `?page=` and, where offered, `?per_page=` (maximum 50).
- **Errors:** `401` unauthenticated, `403` forbidden or account suspended, `404` not found, `422` validation (`{"message": …, "errors": {field: [..]}}`), `429` throttled.
- **Times** are ISO 8601. **Money** is a number in UGX unless `currency` says otherwise.

---

## Health check

### `GET /api/ping`

Public and never cached. It lives at `/api/ping` (not under `/v1`). Returns `{"status": "ok", "service": "FaithFellows API", "version": "1.0.0", "time": "2026-10-12T18:26:40+03:00"}`. The mobile app and uptime monitors use it to tell "server unreachable" from other errors.

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
message is sent, and the email stays unverified until they follow it. Limited to 5 requests per minute and 30 per day per address (**429**).
### `POST /auth/google`

Signs in with Google, or creates the member account the first time. Send the **Google ID token** (a JWT the app receives from Google sign-in), not a password. The server checks Google's signature and that the token was issued for one of our OAuth client IDs (`GOOGLE_CLIENT_IDS`), so no client secret is involved.

```json
{ "credential": "eyJhbGciOiJSUzI1NiIs…", "device_name": "Grace's iPhone" }
```

**201** returns the same body as `/auth/token`, plus `"is_new_user": true|false` (use it to show a "follow a church" welcome).

- An existing member with the same email is signed in and linked to the Google account; no second account is created. If their email was never verified, any password and tokens on it are discarded first.
- New accounts are members, with the email marked verified and the Terms accepted (the app must show "By continuing you agree to the Terms and Privacy Policy").
- **401** the token could not be verified (wrong audience, expired, unverified email, bad signature). **403** the account is suspended, closed, platform staff, or linked to a different Google account. **503** Google sign-in is not configured. Limited to 10 requests per minute.
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
  "giving_methods": ["mtn_momo", "airtel_money", "stripe"], "subscribers": 47, "is_subscribed": true, "web_url": "https://faithfellows.app/churches/grace-lira" } }
```

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
- `phone` is required for mobile money.

**201**

```json
{ "data": { "reference": "FF-8Q2MZ4", "status": "processing", "amount": 20000, "currency": "UGX", "method": "mtn_momo", "church": { "…": "…" } },
  "next": { "action": "approve_on_phone", "redirect_url": null, "message": "Approve the payment on your phone", "poll_url": "https://faithfellows.app/api/v1/giving/FF-8Q2MZ4" } }
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

`GET /me` also returns `bio`, `city`, `country`, `gender`, `date_of_birth`, `interests`, `directory_opt_in`, `open_to_connect`, `unread_messages` and `connection_requests`.
`PATCH /me` `{ name?, phone?, country?, city?, gender?, date_of_birth?, bio?, interests?: [..8 words], directory_opt_in?, open_to_connect? }` saves them — turning either
"find me" switch on needs a birth date of someone aged 18 or over (`422` on `date_of_birth` otherwise). `POST /me/avatar` (multipart `avatar`), `DELETE /me/avatar`,
`PUT /me/password` `{ current_password, password, password_confirmation }` (other phones are signed out).

### Finding and connecting with people

Only adults with a birth date can be found or connect. A member is found by people of their own churches when `directory_opt_in` is on, and by every other adult on the
app when `open_to_connect` is on. Cards carry an `age_range` bracket (`18–24`, `25–34`…), never a birthday, and no email or phone.

- `GET /people?scope=church|everyone&church=&q=&country=UG&city=&gender=&interest=&age_min=&age_max=` → paged `data` of `{ id, name, avatar_url, bio, city, country, gender, age_range, interests, shared_churches, relation }` (`relation`: `none`, `pending_sent`, `pending_received`, `connected`) and `viewer: { can_connect, discoverable }`.
- `GET /people/{id}` the same card for one person (`404` when they cannot be found by you).
- `POST /people/{id}/connect` `{ message? }` ("Say Hey"); asking someone who already asked you connects you. At most 25 requests wait at a time.
- `GET /connections` → `{ friends, incoming, outgoing }`; `POST /connections/{id}/respond` `{ action: accept|decline }`; `DELETE /people/{id}/connection` cancels a request or disconnects.
- `POST /people/{id}/block`, `DELETE /people/{id}/block`, `GET /people/blocked`: blocking works both ways, removes the connection and hides each from the other.

### Messages

Only between connected members. `GET /messages` (conversations with `unread`), `GET /messages/unread` → `{ unread }`, `GET /messages/{userId}?after=<id>|before=<id>` (a thread, oldest first; polling with `after`; marks theirs as read), `POST /messages/{userId}` `{ body }`.
Notifications for requests carry `kind` (`connection_request`, `connection_accepted`) and `ref` (the person's id).

### Study groups

- `GET /groups?type=&q=` → `{ mine, pending, discover, types }` (cards with `cover_url`, `schedule`, `members_count`, `my_status`, `my_role`). `GET /groups/{id}` adds `members`, `announcements`, `meetings`, `can_lead`, `pending_members`.
- `POST /groups/{id}/join` (a request the leader approves), `DELETE /groups/{id}/leave`.
- The group's conversation: `GET /groups/{id}/posts?after=|before=`, `POST …/posts` `{ body }`, `DELETE …/posts/{postId}` (author or leader). Only active members and leaders may read and write.
- Leader tools: `GET …/candidates?q=`, `POST …/members` `{ user_id }`, `PUT …/members/{membershipId}` `{ action: approve|remove|member|assistant|leader }`, `POST …/meetings` `{ meeting_date, topic?, visitors?, present: [userIds] }`, `POST …/announcements` `{ title, body }`.

### Quiz games

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

## Gospel radio 🔒

One station for the whole app. The super admin adds the songs at `/super-admin/radio` on the website (the title, artist, length and cover are read from each file); the app plays them round the clock in a loop, and everyone hears the same song at the same moment because "now playing" is worked out from the clock.

- `GET /radio` → `{ enabled, name, tagline, songs, server_time, now }`. `now` is `null` when the radio is off or has no songs; otherwise `{ track, offset, started_at, ends_at, upcoming: [4 songs with starts_at], recent: [5 songs with played_at] }`. A song is `{ id, title, artist, duration, cover_url, stream_url }`. The app plays `track` from `offset` seconds, moves on through `upcoming` by itself, and compares with the clock each time a song starts.
- `GET /radio/stream/{id}` (not under `/api`, no sign-in) streams a song with byte-range support, so a phone can start part-way into it. `GET /radio/cover/{id}` is the cover picture; a song without one gets a drawn worship scene (`/art/radio/{id}`).
- Website (super admin): `GET/POST /super-admin/radio` (upload, one song per request), `PUT /super-admin/radio/{song}` (title, artist, on or off), `POST /super-admin/radio/{song}/move` `{ direction: up|down }`, `DELETE /super-admin/radio/{song}`, `PUT /super-admin/radio/settings` `{ name, enabled }`. From the command line: `php artisan radio:add "song.mp3" --title= --artist=`.

---

## Versioning

Breaking changes go to `/api/v2`, and v1 keeps working for at least 12 months after v2 ships. Additive fields may appear at any time, so clients must ignore unknown keys.
