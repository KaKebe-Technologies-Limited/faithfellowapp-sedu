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
### `DELETE /auth/token` 🔒

Revokes the token used for this request.

### `GET /me` 🔒

Returns the signed-in user: profile, preferences, followed churches and unread notification count.

---

## Churches

### `GET /churches`

This endpoint is public and lists active churches. Optional filters: `q` (name or pastor), `city`, `denomination`, `per_page`.

### `GET /churches/{slug}`

This endpoint is public. It returns church details, service times, accepted giving methods and the subscriber count. `contact` (phone, email, street address and social links) is filled in only for subscribers and the church's staff; for everyone else it is `null`.

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

### `GET /feed?before=<ISO time>`

Returns the home feed across every church the member follows, newest first. Each item has `type` (`post`, `sermon`, `devotional`, `announcement`, `event`, `live`, `campaign_update`, `prayer` or `testimony`), `at`, `church` and an `item` payload. To fetch the next page, pass `before=<next_before>`.

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

## Prayer wall 🔒

### `GET /prayer?status=open|answered`

Lists requests the member may see. Pastor-only requests appear only to their author and the pastoral team. Each item includes `prayed`, which is true when this member has already prayed.

### `POST /prayer`

```json
{ "church_id": 1, "title": "Healing", "body": "Please pray for my mother…", "visibility": "church", "is_anonymous": false, "chain_enabled": true }
```

`visibility` is `public`, `church` or `pastor`. Public and church requests are created with `status: "pending"` and appear on the wall only after a pastor approves them. Until then only the author and the pastoral team can see them, and nobody can pray on them yet. Pastor-only requests are never shared; the pastor's private reply is shown to the author in the app.

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
