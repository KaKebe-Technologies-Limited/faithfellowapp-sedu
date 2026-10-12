# Super admin guide

This guide is for **Kakebe Technologies staff** who run the FaithFellows platform. Sign in at `/login` with a super admin account; you land on **Super admin → Dashboard**. Turn on two-factor sign-in under **Profile → Preferences**.

## Daily routine (about 10 minutes)

1. **Dashboard:** check pending church applications, open support tickets, new contact messages and the last 30 days of giving.
2. **Support tickets → Needs reply:** answer anything marked *Urgent* or *High* first.
3. **Moderation:** churches moderate their own communities. Here you only handle reports older than 48 hours and repeat offenders.
4. **Payment gateways:** look for falling success rates and stale pending payments.

## Churches

**Churches** lists every tenant. You can filter by status: pending, active, suspended or archived.

- **Approving an application:** open the church, check the details (name, location, pastor, phone; call them if in doubt), then click **Approve**. Approval creates the preset staff roles, starts the free-hosting trial (12 months by default, set in **Settings**), and emails the church admin. If an application isn’t genuine, **Archive** it with a reason.
- **Suspending:** used for abuse or non-payment beyond the grace period. A suspended church's pages and giving go offline, but its data is kept. **Reactivate** restores everything.
- **Archiving:** for churches that leave. Data stays for audit, and the church disappears from the directory.
- **Featured:** featured churches appear first in the directory.

## Billing

- **Plans:** price, interval and limits (storage, stream hours, admins, branches). The default plan is the UGX 60,000/month package.
- **Subscriptions:** each church's plan, billing status (trial, active, past due, suspended), trial end and paid-until date. You can extend a trial or change a plan here.
- **Invoices:** `ff:billing` (06:00 daily) issues invoices 7 days before each period ends, sends reminders, and suspends after the grace period (`FF_GRACE_DAYS`). Churches pay by mobile money or card from **Billing & plan**. Bank payments are marked paid here manually. **Generate now** runs the billing cycle immediately.

## Users

**Users** lets you search by name, email or phone and filter by admins, unverified or suspended accounts. Export to CSV for reporting.

From a user's page you can:

- **Suspend**, which signs the user out everywhere immediately.
- **Reactivate**.
- **Mark email as verified**, for members who can't receive email.
- **Reset two-factor** for a user locked out of their account.
- **Impersonate** a user to reproduce a problem they report. A red banner shows while you are impersonating. Every action is logged with your name in the audit log. Click **Stop impersonating** when done. You can't impersonate other super admins.

## Support tickets

Ticket statuses: **Needs reply** (open) → **Waiting on church** (pending, after you reply) → **Resolved** / **Closed**. A church reply always reopens the ticket.

- Assign tickets to yourself or a colleague, and set the priority.
- **Internal notes** (the toggle under the reply box) are visible only to Kakebe staff.
- The church admin gets every reply by email and in-app.

## Moderation

Members report posts, comments, prayer requests, testimonies and live-chat messages. Church moderators (staff with *Moderate comments, posts & testimonies*) handle their own reports first.

On **Moderation**:

- **Hide** removes the content: a comment, post or prayer is hidden, and a testimony is unpublished.
- **Dismiss** means the content is fine, and a flagged comment becomes visible again.
- **Suspend author** hides the content and suspends the account. Use it for spam bots and repeated abuse.
- **Repeat offenders** lists members with two or more comments hidden after reports.

## Content and messages

- **Blog:** articles for the public `/blog`. Use the Resources, Product updates and Church growth categories. A future publish date schedules the post. Cover images should be 1600×900.
- **Contact inbox:** messages from the public contact form. **Reply by email** sends from the platform address with your name in the signature.

## Analytics

**Analytics** shows platform growth, giving processed, MRR, live services, SMS spend, churches by district and the largest churches, for the last 30 days, 90 days or 12 months. Download the **PDF report** for board meetings, or **Churches CSV** for the per-church breakdown.

## Settings

- **Global announcement banner:** shown at the top of every page. Use it for planned maintenance.
- **Maintenance notice for admins:** shown only on church admin dashboards.
- **Accept new church applications:** turn off to pause the application form.
- **Free trial length**, **support email** and **support phone**.
- **Integrations** shows whether email, SMS, WhatsApp, web push, real-time broadcasting, the queue and live streaming are configured on the server. The keys themselves live in the server's `.env` file, and are never stored in the database or shown on screen.

## Payment gateways

Each provider card shows sandbox or live mode, attempts, success rate and volume for the last 30 days, the time of the last successful payment, and the webhook URL to register with the provider.

**Switch off** a gateway during a provider outage. It disappears from every church's giving form, and the API rejects it until you switch it back on. Cash and bank recording are never affected.

## Audit log

Every admin action is recorded: approvals, role changes, refunds, moderation, settings changes, impersonation, and API tokens issued. Filter by church, action, user or date, and export to CSV. Platform-level actions have no church.

## Security notes

- Never share or reuse super admin accounts. Each staff member has their own.
- Super admins **cannot** read confidential counseling notes, by design.
- Super admin accounts can't create API tokens.
- Report suspected breaches to the Team Lead immediately. Rotate `APP_KEY` only with an engineer, because doing so invalidates encrypted pastoral notes.
