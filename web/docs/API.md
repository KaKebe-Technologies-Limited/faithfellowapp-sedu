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

## Notifications 🔒

### `GET /notifications?unread=1`

```json
{ "data": [ { "id": "9b1e…", "category": "live", "church_id": 1, "title": "Grace Community Church Lira is live", "body": "…", "url": "…", "read": false, "created_at": "…" } ],
  "meta": { "current_page": 1, "last_page": 3, "unread": 4 } }
```

### `POST /notifications/read`

Marks everything as read, or only the given notifications when you pass `{"ids": ["9b1e…"]}`. Returns the new unread count.

---

## Versioning

Breaking changes go to `/api/v2`, and v1 keeps working for at least 12 months after v2 ships. Additive fields may appear at any time, so clients must ignore unknown keys.
