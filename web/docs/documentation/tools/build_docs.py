"""Builds the FaithFellows documentation (one HTML page that prints to a clean A4 PDF) from the screenshots in web/docs/documentation/screens."""
import base64
import html
import pathlib

ROOT = pathlib.Path(r'C:\xampp\htdocs\faithfellowapp-sedu')
DOCS = ROOT / 'web' / 'docs' / 'documentation'
SCREENS = DOCS / 'screens'
FONTS = ROOT / 'app' / 'node_modules' / '@expo-google-fonts' / 'clarity-city'
LOGO = ROOT / 'app' / 'assets' / 'logo-stacked.png'
MARK = ROOT / 'app' / 'assets' / 'logo-mark.png'


def b64(path):
    return base64.b64encode(path.read_bytes()).decode()


def font(weight, name):
    f = FONTS / f'{weight}{name}' / f'ClarityCity_{weight}{name}.ttf'
    return f"@font-face{{font-family:'Clarity City';font-weight:{weight};src:url(data:font/ttf;base64,{b64(f)}) format('truetype');}}"


FONT_CSS = '\n'.join([font(400, 'Regular'), font(500, 'Medium'), font(600, 'SemiBold'), font(700, 'Bold')])
missing = []


def img(name, alt=''):
    p = SCREENS / f'{name}.jpg'
    if not p.exists():
        missing.append(name)
        return ''
    return f'<img src="screens/{name}.jpg" alt="{html.escape(alt or name)}">'


def phones(items, cols=None):
    """A row of phone screens: items are (file, caption)."""
    items = [(n, c) for n, c in items if (SCREENS / f'{n}.jpg').exists()]
    if not items:
        return ''
    cols = cols or min(len(items), 3)
    cells = ''.join(f'<figure class="phone-fig"><div class="phone">{img(n, c)}</div><figcaption>{c}</figcaption></figure>' for n, c in items)
    return f'<div class="phones cols-{cols}">{cells}</div>'


def wide(name, caption):
    if not (SCREENS / f'{name}.jpg').exists():
        missing.append(name)
        return ''
    return f'<figure class="wide"><div class="browser"><div class="bar"><i></i><i></i><i></i></div>{img(name, caption)}</div><figcaption>{caption}</figcaption></figure>'


def box(title, body, tone='soft'):
    return f'<div class="box {tone}"><strong>{title}</strong><div>{body}</div></div>'


def steps(items):
    return '<ol class="steps">' + ''.join(f'<li>{i}</li>' for i in items) + '</ol>'


def ul(items):
    return '<ul>' + ''.join(f'<li>{i}</li>' for i in items) + '</ul>'


def table(head, rows, cls=''):
    h = ''.join(f'<th>{c}</th>' for c in head)
    r = ''.join('<tr>' + ''.join(f'<td>{c}</td>' for c in row) + '</tr>' for row in rows)
    return f'<table class="{cls}"><thead><tr>{h}</tr></thead><tbody>{r}</tbody></table>'


def stat(value, label):
    return f'<div class="stat"><b>{value}</b><span>{label}</span></div>'


CSS = FONT_CSS + """
:root{--wine:#4A0F29;--wine2:#7D1A39;--brand:#BC2649;--mist:#FFE9F5;--blush:#F6F0F3;--ink:#1A1A1A;--grey:#575757;--muted:#8B7A83;--green:#1E9E5A;--blue:#2D7FF9;}
*{box-sizing:border-box}
@page{size:A4;margin:16mm 15mm 18mm;@bottom-left{content:"FaithFellows · Documentation";font-family:'Clarity City',Arial,sans-serif;font-size:8pt;color:#8B7A83}@bottom-right{content:"Page " counter(page);font-family:'Clarity City',Arial,sans-serif;font-size:8pt;color:#8B7A83}}
@page :first{margin:0;@bottom-left{content:none}@bottom-right{content:none}}
html{font-family:'Clarity City','Segoe UI',Arial,sans-serif;color:var(--grey);font-size:10.4pt;line-height:1.55;-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{margin:0;background:#fff}
h1,h2,h3,h4{color:var(--ink);line-height:1.2;margin:0}
h1{font-size:25pt;font-weight:700}
h2{font-size:16pt;font-weight:700;margin:20pt 0 6pt;color:var(--wine2)}
h3{font-size:12pt;font-weight:700;margin:14pt 0 4pt}
h4{font-size:10.5pt;font-weight:700;margin:10pt 0 2pt}
p{margin:0 0 7pt}
a{color:var(--brand)}
ul,ol{margin:0 0 8pt;padding-left:17pt}li{margin-bottom:2.5pt}
strong{color:var(--ink)}
.cover{width:210mm;height:297mm;background:linear-gradient(155deg,#2A0B17 0%,#4A0F29 38%,#8E1C37 78%,#BC2649 100%);color:#fff;position:relative;overflow:hidden;page-break-after:always;padding:26mm 20mm}
.cover .halo{position:absolute;right:-60mm;top:-50mm;width:170mm;height:170mm;border-radius:50%;background:rgba(255,255,255,.07)}
.cover .halo2{position:absolute;left:-50mm;bottom:-60mm;width:130mm;height:130mm;border-radius:50%;background:rgba(255,255,255,.05)}
.cover .logo{background:#fff;border-radius:7mm;padding:6mm 9mm;display:inline-block}.cover .logo img{height:22mm;display:block}
.cover h1{color:#fff;font-size:44pt;margin-top:14mm;letter-spacing:-.5pt}
.cover .tag{font-size:17pt;color:#FFD9E4;margin-top:4mm;max-width:130mm}
.cover .what{margin-top:9mm;font-size:11.5pt;color:#fff;opacity:.9;max-width:128mm}
.cover .phones-row{position:absolute;left:20mm;right:20mm;bottom:25mm;display:flex;gap:7mm;align-items:flex-end}
.cover .phones-row .phone{width:47mm;border-color:#0f0508;box-shadow:0 8mm 14mm rgba(0,0,0,.35)}
.cover .phones-row .phone:nth-child(2){width:52mm;transform:translateY(-8mm)}
.cover .foot{position:absolute;left:20mm;right:20mm;bottom:10mm;font-size:9.5pt;color:#FFD9E4;display:flex;justify-content:space-between}
.toc{page-break-after:always;padding-top:4mm}.toc h1{margin-bottom:8mm}
.toc ol{list-style:none;padding:0;columns:2;column-gap:12mm}.toc li{break-inside:avoid}.toc li.part{break-after:avoid}.toc li{display:flex;gap:4mm;align-items:baseline;border-bottom:1px dotted #d8c9d0;padding:2.2mm 0;font-size:10.4pt;margin:0}
.toc li b{color:var(--brand);width:8mm;flex:none}.toc li.part{border:0;margin-top:5mm;font-weight:700;color:var(--wine2);font-size:9.5pt;letter-spacing:1.2pt;text-transform:uppercase}
.part-title{page-break-before:always;background:linear-gradient(135deg,#4A0F29,#BC2649);color:#fff;border-radius:6mm;padding:14mm 12mm;margin:0 0 8mm}
.part-title small{display:block;letter-spacing:2pt;text-transform:uppercase;color:#FFD9E4;font-size:9pt;margin-bottom:3mm}.part-title h1{color:#fff;font-size:28pt}.part-title p{color:#FFE9F5;margin:3mm 0 0;font-size:11.5pt}
section{break-inside:auto}
.lead{font-size:12.4pt;color:var(--ink);line-height:1.5;margin-bottom:9pt}
.box{border-radius:3.5mm;padding:4mm 5mm;margin:6pt 0 9pt;break-inside:avoid;border-left:1.6mm solid var(--brand);background:var(--mist)}
.box strong{display:block;color:var(--wine2);margin-bottom:1mm}.box.green{background:#E8F6EE;border-color:var(--green)}.box.green strong{color:#0E6B3B}.box.blue{background:#EAF2FF;border-color:var(--blue)}.box.blue strong{color:#1D5FD1}.box.warn{background:#FFF3E0;border-color:#E8A317}.box.warn strong{color:#9A5B00}
.box div p:last-child{margin-bottom:0}
.grid2,.grid3,.grid4{margin-bottom:10pt}.newpage{break-before:page}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:5mm}.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:4.5mm}.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:3.5mm}
.card{border:.3mm solid #EADCE3;border-radius:4mm;padding:4mm 4.5mm;background:#fff;break-inside:avoid}.card h4{margin-top:0;color:var(--wine2)}.card p:last-child,.card ul:last-child{margin-bottom:0}
.card .ico{width:9mm;height:9mm;border-radius:3mm;background:var(--mist);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:13pt;margin-bottom:2mm}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:3.5mm;margin:6pt 0 10pt}.stat{background:var(--blush);border-radius:4mm;padding:4mm;text-align:center;break-inside:avoid}.stat b{display:block;font-size:15pt;color:var(--brand);line-height:1.1}.stat span{font-size:8.6pt;color:var(--grey)}
table{width:100%;border-collapse:collapse;margin:5pt 0 10pt;font-size:9.4pt}thead{display:table-header-group}tr{break-inside:avoid}h2,h3,h4{break-after:avoid}table.keep{break-inside:avoid}th{background:var(--wine2);color:#fff;text-align:left;padding:2.2mm 3mm;font-weight:600}td{padding:2.2mm 3mm;border-bottom:.3mm solid #EADCE3;vertical-align:top}tr:nth-child(even) td{background:#FBF7F9}
.steps{counter-reset:s;list-style:none;padding:0}.steps li{counter-increment:s;position:relative;padding-left:9mm;margin-bottom:3mm;break-inside:avoid}.steps li:before{content:counter(s);position:absolute;left:0;top:-.3mm;width:6mm;height:6mm;border-radius:50%;background:var(--brand);color:#fff;font-size:8.5pt;font-weight:700;display:flex;align-items:center;justify-content:center}
.phones{display:grid;gap:5mm;margin:7pt 0 10pt;justify-items:center;break-inside:avoid}.cols-1{grid-template-columns:1fr}.cols-2{grid-template-columns:repeat(2,1fr)}.cols-3{grid-template-columns:repeat(3,1fr)}.cols-4{grid-template-columns:repeat(4,1fr)}
.phone-fig{margin:0;text-align:center;width:100%}.phone{border:1.8mm solid #17090E;border-radius:7mm;overflow:hidden;background:#17090E;width:46mm;margin:0 auto;box-shadow:0 3mm 6mm rgba(74,15,41,.18)}.cols-4 .phone{width:38mm;border-width:1.4mm;border-radius:5.5mm}.cols-2 .phone{width:56mm}.cols-1 .phone{width:62mm}.phone img{display:block;width:100%}
figcaption{font-size:8.6pt;color:var(--muted);margin-top:2mm;line-height:1.35}
.wide{margin:7pt auto 10pt;break-inside:avoid;width:88%}.browser{border:.35mm solid #D9C8D1;border-radius:3mm;overflow:hidden;box-shadow:0 2.5mm 6mm rgba(74,15,41,.12)}.browser .bar{background:#F1E7EC;padding:2mm 3mm;display:flex;gap:1.4mm}.browser .bar i{width:2.2mm;height:2.2mm;border-radius:50%;background:#D8BBC8;display:block}.browser img{display:block;width:100%}
.flow{display:flex;gap:2mm;align-items:stretch;margin:6pt 0 10pt;break-inside:avoid}.flow div{flex:1;background:var(--blush);border-radius:3.5mm;padding:3mm 2.5mm;text-align:center;font-size:8.8pt;position:relative}.flow div b{display:block;color:var(--brand);font-size:11pt}.flow div:not(:last-child):after{content:'›';position:absolute;right:-3.2mm;top:50%;transform:translateY(-50%);color:var(--brand);font-size:13pt;z-index:2}
.arch{display:grid;grid-template-columns:1fr 1fr 1fr;gap:3mm;margin:6pt 0 10pt;break-inside:avoid}.arch div{border-radius:4mm;padding:4mm;font-size:9pt}.arch .a{background:var(--mist)}.arch .b{background:#EAF2FF}.arch .c{background:#E8F6EE}.arch b{display:block;color:var(--ink);margin-bottom:1mm;font-size:10pt}.arch .wideb{grid-column:1/-1;background:var(--blush);text-align:center}
.pill{display:inline-block;background:var(--mist);color:var(--wine2);border-radius:99px;padding:.4mm 2.6mm;font-size:8.6pt;font-weight:600;margin:0 .6mm .8mm 0}
.quote{border-left:1.6mm solid var(--brand);padding:1mm 0 1mm 5mm;margin:8pt 0;font-size:12.5pt;color:var(--wine2);font-style:italic}
footer.end{margin-top:14mm;padding-top:5mm;border-top:.4mm solid #EADCE3;font-size:9pt;color:var(--muted);display:flex;justify-content:space-between}
"""

LOGO_B64 = b64(LOGO)
MARK_B64 = b64(MARK)

# ──────────────────────────────────────────────────────────────────────────────────────────────────────────
cover = f"""
<div class="cover">
  <div class="halo"></div><div class="halo2"></div>
  <div class="logo"><img src="data:image/png;base64,{LOGO_B64}" alt="FaithFellows"></div>
  <h1>FaithFellows</h1>
  <div class="tag">Empowering your ministry in the digital age</div>
  <div class="what">The complete picture: the idea and the problem it solves, how the platform works, the business model and the impact, and a step-by-step user guide for members, churches and the platform owner.</div>
  <div class="phones-row">
    <div class="phone">{img('app-home-explore', 'Home')}</div>
    <div class="phone">{img('app-profile-progress', 'Profile')}</div>
    <div class="phone">{img('app-give-form', 'Give')}</div>
  </div>
  <div class="foot"><span>Kakebe Technologies Limited · Lira, Uganda</span><span>Documentation · App version 1.1.1 · October 2026</span></div>
</div>
"""

toc_items = [
    ('part', 'Part A · The idea'), ('1', 'FaithFellows on one page'), ('2', 'The problem'), ('3', 'The solution'), ('4', 'Who it serves and the impact'), ('5', 'The need, and why now'), ('6', 'The business model'), ('7', 'Roadmap and what it takes to launch'),
    ('part', 'Part B · How it works'), ('8', 'The platform in plain words'), ('9', 'A church’s journey'), ('10', 'A member’s journey'), ('11', 'How giving works'), ('12', 'How the monthly fee works'), ('13', 'Privacy, safety and trust'),
    ('part', 'Part C · User guide'), ('14', 'Members: getting started'), ('15', 'Members: the Home screen'), ('16', 'Members: Bible'), ('17', 'Members: Games'), ('18', 'Members: Grow your circle'), ('19', 'Members: giving and fundraisers'), ('20', 'Members: your profile and statistics'), ('21', 'Members: more to explore'),
    ('22', 'Church leaders: the portal'), ('23', 'Church leaders: money and records'), ('24', 'Church leaders: people and subscribers'), ('25', 'Church staff in the app'), ('26', 'The platform owner (super admin)'),
    ('part', 'Part D · Reference'), ('27', 'Questions and answers'), ('28', 'Key figures and settings'), ('29', 'Contact'),
]
toc = '<div class="toc"><h1>Contents</h1><ol>' + ''.join(
    (f'<li class="part">{t}</li>' if n == 'part' else f'<li><b>{n}</b><span>{t}</span></li>') for n, t in toc_items) + '</ol></div>'


def part(label, title, intro):
    return f'<div class="part-title"><small>{label}</small><h1>{title}</h1><p>{intro}</p></div>'


A = part('Part A', 'The idea', 'What FaithFellows is, the problem it solves, who it helps and how it sustains itself.')

A += f"""
<section>
<h2>1 · FaithFellows on one page</h2>
<p class="lead"><strong>FaithFellows gives every church its own digital home, and every member their church family in their pocket, at a price every church can afford.</strong></p>
<div class="grid2">
  <div class="card"><h4>The problem</h4><p>Most churches reach people only on Sunday. Radio and TV are expensive, giving is mostly cash with no records, members are scattered across WhatsApp groups and notebooks, and a custom website or app is out of reach for a small church.</p></div>
  <div class="card"><h4>The solution</h4><p>One platform with three parts: a <strong>mobile app</strong> for members (Bible, hymns, sermons, prayer, events, games, giving), a <strong>church portal</strong> for leaders (content, people, money, records) and a <strong>control room</strong> for the platform owner. Giving is by <strong>mobile money</strong>.</p></div>
  <div class="card"><h4>Who pays</h4><p>Churches pay a small monthly fee (from UGX 60,000). Members use the app free. Every church gets its own website, hosting and support included.</p></div>
  <div class="card"><h4>What changes</h4><p>People hear and read the Word daily, not only on Sunday. Leaders see who follows them and how they are doing. Treasurers get receipts, records and reports. Givers are thanked by email within minutes.</p></div>
</div>
<div class="stats">
  {stat('3-in-1', 'app, church portal and platform control')}
  {stat('UGX 60,000', 'a month for a church to start')}
  {stat('Mobile money', 'MTN and Airtel, no cash handling')}
  {stat('8+', 'Bible versions, plus hymns, radio and podcasts')}
</div>
<div class="quote">“God loves a cheerful giver.” — 2 Corinthians 9:7. FaithFellows makes giving as easy as a prompt on the phone, and makes sure every gift is thanked, recorded and accounted for.</div>
{phones([('app-home-explore', 'Home: highlights, shortcuts, the day’s inspiration'), ('app-bible-versions', 'Bible in the version you choose'), ('app-give-form', 'Giving with mobile money')])}
</section>

<section>
<h2>2 · The problem</h2>
<p>Churches in Uganda and across Africa do remarkable work with very little, and they face the same handful of problems. These are the situations FaithFellows was built for.</p>
{table(['The situation today', 'What it costs the church'], [
  ['<strong>Reach stops at the door.</strong> Radio and TV slots are expensive; members who travel, study or live abroad cannot take part.', 'Members drift away; sermons are heard once and lost; the diaspora is not served.'],
  ['<strong>Information is scattered.</strong> Members live in WhatsApp groups, attendance in notebooks, announcements on pieces of paper.', 'Leaders do not know who is active, who is new, who needs a visit. Messages are missed.'],
  ['<strong>Giving is mostly cash.</strong> Offerings are counted by hand; mobile money goes to personal numbers.', 'No receipts, no records, no way to show donors where their gift went. Accountability suffers and so does trust.'],
  ['<strong>No daily habit.</strong> Bible reading, prayer and encouragement depend on the person alone.', 'Faith is practised once a week. Young people look for what they need elsewhere online.'],
  ['<strong>Technology is out of reach.</strong> A custom website or app costs more than a small church can pay, and nobody on staff can run it.', 'The church has no online presence, or an abandoned page.'],
  ['<strong>Data is expensive and connections are poor.</strong>', 'Heavy apps are not used. The Bible and hymns must work with little data, and offline.'],
])}
</section>

<section>
<h2>3 · The solution</h2>
<p>FaithFellows replaces the patchwork with one connected system. A church signs up, is approved, and immediately has a website, an admin portal and a presence in the app that its members download.</p>
<div class="grid3">
  <div class="card"><div class="ico">📱</div><h4>The member app</h4><p>Home, Bible, Community and Give tabs plus Me. Sermons, live services, devotionals, prayer wall, events and tickets, radio, podcasts, a hymn book, games, people to walk with, and giving.</p></div>
  <div class="card"><div class="ico">🏛️</div><h4>The church portal</h4><p>A website dashboard for pastors and staff: publish content, schedule events, go live, manage people and groups, record giving, run fundraisers, see statistics and send notifications.</p></div>
  <div class="card"><div class="ico">🧭</div><h4>The control room</h4><p>For the platform owner: approve churches, set the plans and fee, watch payments and activity, decide which features each church sees, send inspiration, and keep the platform healthy.</p></div>
</div>
<h3>What makes it work in the real world</h3>
{ul([
  '<strong>Mobile money first.</strong> A gift is a prompt on the phone approved with the PIN, through MTN and Airtel.',
  '<strong>Light and offline.</strong> The Bible and the hymn book can be saved on the phone and read without data.',
  '<strong>Faith-based by design.</strong> Podcasts are approved before listing, hymns always carry their credits, and contact details are never shared between members.',
  '<strong>Affordable.</strong> The church fee covers the website, hosting, storage, support and the app presence.',
  '<strong>Measurable.</strong> Leaders and treasurers see growth, engagement and giving in numbers they can act on.',
])}
</section>

<section>
<h2>4 · Who it serves and the impact</h2>
{table(['Who', 'What they get', 'The impact'], [
  ['<strong>Church members</strong>', 'A free app: the Word in their own version, hymns, sermons, prayer, events, a daily inspiration, a way to give and a place to belong.', 'A daily habit instead of a Sunday one. Belonging, for people far from their church. Giving made simple, with a receipt and a thank-you.'],
  ['<strong>Pastors and leaders</strong>', 'One place for content, people and groups; statistics on followers and activity; notifications to the whole church or a group.', 'Time saved on admin and chasing messages. Knowing who is growing, who is quiet and who needs care.'],
  ['<strong>Treasurers and finance teams</strong>', 'Giving reasons set by the church, records clustered by reason, fundraisers, cash entry, refunds, payouts, CSV and PDF exports.', 'Accounts that add up. Transparent stewardship that builds donor trust and lets a church plan.'],
  ['<strong>Young people</strong>', 'Games, the Bible in plain versions, shareable verses, a circle of believers like them.', 'The church meets them where they already are, on the phone.'],
  ['<strong>The wider community</strong>', 'Churches that run well and communicate well; local-language content; digital skills for church administrators.', 'Stronger, more accountable community institutions, and a model other organisations can follow.'],
])}
<h3>Impact you can measure</h3>
<p>Because everything happens in one system, impact is not a guess. The platform records, anonymously and added up:</p>
<div class="grid4">
  <div class="card"><h4>Reach</h4><p>Followers per church, new followers each week, growth month on month.</p></div>
  <div class="card"><h4>Engagement</h4><p>Members who opened the app, minutes with the Bible, minutes listening, verses shared.</p></div>
  <div class="card"><h4>Generosity</h4><p>Share of followers who gave, giving by reason and by method, fundraiser progress.</p></div>
  <div class="card"><h4>Health</h4><p>Quiet churches warned and reopened, payments on time, support requests.</p></div>
</div>
{wide('web-church-subscribers', 'The church’s Subscribers page: growth, who follows, how active they are')}
</section>

<section>
<h2>5 · The need, and why now</h2>
{ul([
  '<strong>Mobile money is how people already pay.</strong> The missing piece was connecting it to church giving with receipts and records.',
  '<strong>Phones are how people already read and listen.</strong> Members expect their church to be there, as everything else is.',
  '<strong>Churches already rely on digital tools,</strong> but in disconnected ways (chat groups, photos of notebooks). A single system turns that effort into records and insight.',
  '<strong>Trust and accountability matter more than ever.</strong> Donors give more freely when they are thanked, can see receipts and know their church keeps clean accounts.',
  '<strong>Local relevance is rare.</strong> Most church software is built for other markets. FaithFellows is built in Lira, priced in shillings and designed for low data.',
])}
{box('The invitation', 'Churches, denominations and fellowships are invited to pilot FaithFellows: a church can be live in a day, with its members following it by QR code or a link.', 'green')}
</section>

<section>
<h2>6 · The business model</h2>
<p><strong>Churches pay a small monthly subscription; members use the app free.</strong> Free membership grows the network (more members, more reason for a church to be on the platform); the subscription pays for running it.</p>
<h3>Plans (the platform owner can change these at any time)</h3>
{table(['', 'Starter', 'Growth'], [
  ['<strong>Price</strong>', 'UGX 60,000 per month', 'UGX 150,000 per month'],
  ['<strong>Storage</strong>', '5 GB', '25 GB'],
  ['<strong>Live streaming</strong>', '40 hours per month', '150 hours per month'],
  ['<strong>Staff accounts</strong>', 'Up to 10', 'Up to 30'],
  ['<strong>Branches</strong>', 'Up to 5', 'Up to 20'],
  ['<strong>Includes</strong>', 'Custom church website, free hosting for the first year, custom church email accounts, server maintenance and optimisation, hosting for sermons and media, two social media posts a week, Google platform listing, payment integration, continuous tech support', 'Everything in Starter, with larger limits for storage, streaming, staff and branches'],
])}
<div class="grid3">
  <div class="card"><h4>Revenue streams</h4>{ul(['Monthly church subscriptions (core).', 'Custom domain add-on: UGX 60,000 a year.', 'Higher plans for larger networks.', 'Possible later: sponsored content, partner programmes and training.'])}</div>
  <div class="card"><h4>What the fee pays for</h4>{ul(['Servers, storage and streaming.', 'Push, email and (optional) SMS.', 'Support and daily running.', 'Security, backups and updates.'])}</div>
  <div class="card"><h4>How churches pay</h4>{ul(['Mobile money prompt to the phone.', 'Email a week before, a reminder the day before, a thank-you after.', '14 days of grace; then the church is paused until paid.', 'A free first year can be offered; the length is a setting.'])}</div>
</div>
<h3>An illustration of the scale (Starter plan only)</h3>
{table(['Churches', 'Monthly subscriptions', 'Per year'], [
  ['50', 'UGX 3,000,000', 'UGX 36,000,000'], ['100', 'UGX 6,000,000', 'UGX 72,000,000'], ['250', 'UGX 15,000,000', 'UGX 180,000,000'], ['500', 'UGX 30,000,000', 'UGX 360,000,000'],
])}
<p class="small" style="font-size:9pt;color:var(--muted)">These are simple multiplications of the list price for illustration, not a forecast. Real income depends on how many churches join, the free period offered and the mix of plans. Running costs (hosting, messaging, support) should be added once the first months of actual usage are known.</p>
<h3>Why it holds</h3>
{ul([
  '<strong>Records and history stay in one place.</strong> A church that has its members, sermons and accounts here has a good reason to stay.',
  '<strong>A network that grows.</strong> Members who follow a church can connect with other believers; each church makes the app more valuable to the next.',
  '<strong>Aligned incentives.</strong> Churches that use the platform actively keep it; quiet churches are warned and, if still quiet, paused, so the directory stays alive.',
  '<strong>Low cost to serve.</strong> One platform serves every church; new churches are set up by themselves, not by custom projects.',
])}
<h3>How it reaches churches</h3>
{ul(['Pilot with a group of churches and learn from them, starting in the north of Uganda.', 'Partner with denominations, pastors’ fellowships and conferences.', 'Let members bring their church: a QR code and a link to follow the church are always one tap away.', 'Show results: the statistics pages and monthly giving records are the best sales pitch.'])}
</section>

<section>
<h2>7 · Roadmap and what it takes to launch</h2>
{table(['Area', 'Status'], [
  ['Member app (Android): Home, Bible, Community, Give, Me; radio, podcasts, hymns, games, people, prayer, events, live, giving', '<span class="pill">Built</span>'],
  ['Church portal: content, people, finance, records, statistics, notifications, billing', '<span class="pill">Built</span>'],
  ['Super admin: churches, plans and fee, subscribers, features, inspiration pushes, email, Google sign-in, push test', '<span class="pill">Built</span>'],
  ['Live on faithfellows.com with automatic updates', '<span class="pill">Live</span>'],
  ['Mobile money through ioTec (MTN and Airtel)', '<span class="pill">Live</span>'],
  ['Phone updates over the air (no reinstall for most changes)', '<span class="pill">Built</span>'],
  ['iPhone app in the App Store', 'Next: needs an Apple developer account'],
  ['ESV Bible (Crossway licence key)', 'Next: needs a Crossway API key, on terms that fit a paid platform'],
  ['Luganda, Lango and other local-language hymn books', 'Next: with the publishers’ written permission'],
  ['SMS and WhatsApp notifications', 'Ready; switched on when a church wants to pay for them'],
  ['Automatic payouts to church accounts and bank reconciliation', 'Records and manual payouts are built; automation next'],
])}
{box('To go live for everyone, the platform owner still needs to', ul(['Keep the two scheduled jobs running on the server (the scheduler and the queue).', 'Add the Google sign-in redirect address and publish the consent screen.', 'Get an ESV key and set it on the server.', 'Upload the Firebase key to Expo so Android notifications are delivered (Super admin → Push test shows whether it works).', 'Choose the fee and the length of the free period.']), 'warn')}
</section>
"""

B = part('Part B', 'How it works', 'The parts of the platform and how a church, a member and a gift move through it.')
B += f"""
<section>
<h2>8 · The platform in plain words</h2>
<div class="arch">
  <div class="a"><b>Member app</b>Android (iPhone next). Home, Bible, Community, Give, Me. Works with little data; the Bible and hymns can be kept on the phone. Updates arrive over the air.</div>
  <div class="b"><b>Church portal</b>A website for pastors and staff, with roles and permissions: content, events, people, finance, statistics, billing.</div>
  <div class="c"><b>Super admin</b>The platform owner’s room: churches, plans, subscribers, features, messages to everyone, email, push test, database updates.</div>
  <div class="wideb"><b>One shared system underneath</b> · a secure API and database · each church’s data kept apart · every important action written to an audit log</div>
  <div class="a"><b>Money</b>ioTec mobile money (MTN, Airtel). Receipts, records and a thank-you email for each gift.</div>
  <div class="b"><b>Messages</b>Push to the phone, in-app list, email from the platform’s Gmail account; SMS and WhatsApp ready.</div>
  <div class="c"><b>Content</b>Sermons, devotionals, live streams, events, podcasts (links to the host), radio, hymns, Bible text.</div>
</div>
<p>Everything is connected: when a church publishes a sermon, its followers are told; when a member gives, the treasurer’s records, the campaign progress, the member’s receipt and the thank-you email all update by themselves.</p>
</section>

<section>
<h2>9 · A church’s journey</h2>
<div class="flow"><div><b>1</b>Apply</div><div><b>2</b>Approved</div><div><b>3</b>Set up</div><div><b>4</b>Members follow</div><div><b>5</b>Publish &amp; engage</div><div><b>6</b>Give &amp; record</div><div><b>7</b>Pay the fee</div></div>
{steps([
  '<strong>Apply.</strong> A church fills in the registration form on the website (name, denomination, place, contact).',
  '<strong>Approval.</strong> The platform owner reviews and approves it; the church gets its website address, its admin portal, a set of roles (Editor, Finance, Media, Events, Moderator) and a starting list of giving reasons.',
  '<strong>Set up.</strong> The setup checklist guides the church: profile, logo and cover, service times, staff, giving channels (mobile money), first announcement.',
  '<strong>Members follow.</strong> People find the church in the app’s directory, or scan its QR code or open its link, and follow it.',
  '<strong>Publish and engage.</strong> Sermons, devotionals, announcements, events, live services, prayer requests, groups and reading plans reach followers on their phones; notifications go only to the church’s followers.',
  '<strong>Give and record.</strong> Members give by mobile money; the church sees every gift grouped by reason, plus fundraisers and records.',
  '<strong>Pay the monthly fee.</strong> The portal always shows the next payment day; reminders go out a week and a day before.',
])}
{box('Staying active', 'A church that goes completely quiet (no staff sign-in, nothing published, no new followers) for 30 days gets a warning by email and in the portal. If it is still quiet 14 days later it is paused, and the platform owner can open it again. Nothing is deleted.')}
</section>

<section>
<h2>10 · A member’s journey</h2>
<div class="flow"><div><b>1</b>Install</div><div><b>2</b>Sign in</div><div><b>3</b>Follow a church</div><div><b>4</b>Daily habit</div><div><b>5</b>Belong</div><div><b>6</b>Give</div></div>
{steps([
  '<strong>Install and sign in</strong> with Google or with email. A forgotten password is reset with a code sent by email.',
  '<strong>Follow a church</strong> (up to three), from the directory, a QR code or a link.',
  '<strong>Build a daily habit:</strong> the Bible in the version they like, the inspiration of the day, a devotional, hymns, radio and podcasts, prayer.',
  '<strong>Belong:</strong> events, groups, reading circles, games with friends, and Grow your circle for people who share their faith and interests.',
  '<strong>Give:</strong> a few taps and a prompt on the phone, then a receipt and a thank-you.',
  '<strong>See their own progress:</strong> private rings and statistics for Bible time, listening and sharing.',
])}
</section>

<section>
<h2>11 · How giving works</h2>
{steps([
  '<strong>The church decides what people can give for.</strong> Each church writes its own list of giving reasons (Tithe, Offering, Building fund, a thanksgiving…). It also starts fundraisers with a goal and a closing date.',
  '<strong>The member chooses.</strong> They enter an amount; their phone number from their profile is already filled in below it; they pick the reason (or a fundraiser) and press Give.',
  '<strong>A prompt appears on the phone.</strong> They enter their mobile money PIN to approve; the amount is deducted from their mobile money.',
  '<strong>The system confirms</strong> the payment with the mobile money company, marks the gift as received, and writes the receipt.',
  '<strong>Everyone is updated at once:</strong> the member gets a receipt (PDF) and a thank-you email; the fundraiser’s progress moves; the treasurer’s records and the finance team’s alerts update.',
  '<strong>The church sees it clustered:</strong> by reason, fundraiser and payment method, with fees and the net received, and can download the ledger as CSV or PDF.',
])}
{wide('web-church-records', 'Financial records: what came in, what for, and how')}
{box('Honest records', 'Every gift keeps the name of the reason as it was given, so renaming or removing a reason later never changes past records. Pending, failed and refunded gifts are shown separately and never counted as received.', 'green')}
</section>

<section>
<h2>12 · How the monthly fee works</h2>
<p>A church’s fee keeps its portal and its place in the app running. The amount is its plan price, set by the platform owner.</p>
{table(['When', 'What happens'], [
  ['<strong>A week before the payment day</strong>', 'An invoice is prepared and the church’s owner and admins get an email: the amount, the payment date and a Pay button.'],
  ['<strong>The day before</strong>', 'A second reminder email.'],
  ['<strong>On the payment day</strong>', 'The church pays with mobile money: it enters a phone number, gets a prompt and approves it with the PIN.'],
  ['<strong>When paid</strong>', 'A thank-you email with the next payment date; the “paid until” date moves forward a month.'],
  ['<strong>If it is late</strong>', 'Reminders continue. After the grace days (14 by default) the church is paused until it pays; paying brings it back at once.'],
])}
{wide('web-church-billing', 'Billing: the next payment is always shown, with the amount and a mobile money button')}
</section>

<section>
<h2>13 · Privacy, safety and trust</h2>
<div class="grid2">
  <div class="card"><h4>Who can see what</h4>{ul(['Each church’s data is kept separate from every other church.', 'Staff have roles with exact permissions: only finance staff see giving, and only care staff see pastoral notes (which even platform staff cannot read).', 'Members’ own statistics are private; churches only see anonymous totals.'])}</div>
  <div class="card"><h4>Safety between members</h4>{ul(['Meeting other believers is for adults who have given their date of birth.', 'Members choose whether to be found; only an approximate distance is ever shown, never a place.', 'Phone numbers, emails and links cannot be sent between members; blocking works both ways; reports go to moderators.'])}</div>
  <div class="card"><h4>Security</h4>{ul(['Passwords and secrets are stored encrypted.', 'Sign-in with Google, or email with a reset code that expires in 15 minutes.', 'Every important action is written to an audit log.', 'Limits on repeated attempts protect sign-in and payments.'])}</div>
  <div class="card"><h4>Respect for content</h4>{ul(['Hymns carry their credits and only the platform owner adds them.', 'Licensed Bible text (the ESV) is never stored or downloaded.', 'Podcasts are approved before they are listed; the audio stays with its owner.'])}</div>
</div>
</section>
"""

C = part('Part C', 'User guide', 'Step by step, with the screens, for members, church leaders and the platform owner.')
C += f"""
<section>
<h2>14 · Members: getting started</h2>
<p>Install the FaithFellows app on your phone and open it. The first screen offers two ways in.</p>
{phones([('app-welcome', 'Welcome: continue with Google, or with email'), ('app-login', 'Sign in with your email and password')])}
{steps([
  'Press <strong>Continue with Google</strong> and choose your Google account, or press <strong>Continue with email</strong> and type your email and password.',
  'New here? Press <strong>Create an account</strong>, fill in your name, email and password and accept the terms.',
  'Forgot your password? Press <strong>Forgot your password?</strong>, enter your email, and type the 6-digit code we send you with a new password. Check the spam folder if it does not arrive. You can ask for a new code after 45 seconds.',
  'When the phone asks, press <strong>Allow</strong> for notifications so you get news from your church.',
  'Find your church: open <strong>Community → Find churches</strong>, or scan the church’s QR code, and press <strong>Follow</strong>. You can follow up to three churches.',
])}
{box('Add your phone number once', 'Your profile asks for your phone number. It is saved there and fills in mobile money for you every time you give.')}
</section>

<section>
<h2>15 · Members: the Home screen</h2>
<p>Home is your daily starting point.</p>
{phones([('app-home-explore', 'The highlights slider and the Explore shortcuts'), ('app-home-inspiration', 'The inspiration of the hour'), ('app-home-verse', 'Verse of the day and the Games card'), ('app-home-lists', 'Top podcasts and the latest sermons')], 4)}
{ul([
  'When the app opens, a short greeting (“Good morning, Grace”) shows for a few seconds above the slider.',
  '<strong>The slider</strong> at the top shows what is happening at your church: live services, events, sermons, fundraisers. Tap a slide to open it.',
  '<strong>Explore</strong> opens radio, podcasts, hymns, sermons, events, prayer, giving and games. The number on Prayer is how many requests are waiting for you to pray.',
  '<strong>Inspiration</strong> is a short, strong message for the morning, mid-morning or evening. Tap the share icon to send it to someone.',
  '<strong>Highlights</strong> slide by themselves: the live service, urgent notices, announcements, the devotional, fundraisers and the next event.',
  '<strong>Verse of the day, Games, Top podcasts and Sermons</strong> finish the page. Pull down to refresh.',
])}
</section>

<section>
<h2>16 · Members: Bible</h2>
{phones([('app-bible', 'The Bible tab'), ('app-bible-versions', 'Choose your version'), ('app-bible-books', 'Books numbered 1 to 66'), ('app-reader', 'Reading, with verse actions')], 4)}
{steps([
  'Open the <strong>Bible</strong> tab. The chip under the title shows your version; tap it to change to another (ESV when available, KJV, WEB, WEBBE, ASV, BBE, Darby, Douay-Rheims).',
  'Books are numbered in order, <strong>Genesis 1 to Revelation 66</strong>. Choose Old or New Testament, tap a book and then a chapter.',
  'Type a reference such as <strong>John 3:16</strong> or <strong>Psalm 23</strong> in the search box to jump straight to it.',
  'In a chapter, tap verses to select them, then <strong>highlight</strong> in one of four colours, <strong>copy</strong>, write a <strong>note</strong> or <strong>share</strong>. Sharing makes a neat scripture card.',
  'Use <strong>Reading plans</strong> and <strong>My notes</strong> from the Bible tab. The cloud button saves a version on your phone to read without data (not available for the ESV).',
])}
{box('Your reading counts', 'The minutes you spend in the Bible are added to your Bible ring on your profile, privately.', 'green')}
</section>

<section class="newpage">
<h2>17 · Members: Games</h2>
<p>Games are Bible quizzes you play with friends. There are <strong>25 ready-made games</strong> of 10 questions: Creation, Moses, David, the Prophets, Women of the Bible, the Christmas story, Parables, Miracles, Acts, Paul, Revelation, Books of the Bible, Bible lands, and Faith in Uganda among them.</p>
{phones([('app-games', 'Pick a game, or join with a PIN'), ('app-game-lobby', 'Your game is ready: share the PIN or invite'), ('app-game-invite', 'Invite people you have played with, by name')])}
{steps([
  'Open <strong>Games</strong> from Home. Filter by category and press <strong>Start</strong> on a game.',
  'You become the host and a player. Press <strong>Share PIN</strong> to send the 6-digit code by WhatsApp or SMS, or <strong>Invite people</strong> and tap <strong>Invite</strong> next to the names of people you have played with or are connected to.',
  'Your friends get a notification and a push; they tap it, or enter the PIN under <strong>Join a friend’s game</strong>.',
  'When everyone is in, press <strong>Start game</strong>. Everyone is told it has started. Each question is open for 10 seconds; fast, correct answers score more.',
  'At the end you see the podium and the scoreboard.',
])}
</section>

<section>
<h2>18 · Members: Grow your circle</h2>
{phones([('app-community', 'The Community tab'), ('app-people', 'A simple list of people, with why they were suggested')])}
{steps([
  'Open <strong>Community → Grow your circle</strong>. The list shows names only (no ages) and a short reason, such as “Both like Worship &amp; Prayer”.',
  '<strong>For you</strong> ranks people by what you share: interests, podcasts, hymns, scriptures, church, town and age. <strong>My church</strong>, <strong>Near me</strong> and <strong>Everyone</strong> are other views.',
  'In <strong>Near me</strong>, press <strong>Allow location</strong> once, then pick a distance (5 to 200 km). Churches around you are listed first. Others only see roughly how far away you are, never where.',
  'Press <strong>Say Hey 👋</strong> to ask to connect. Once they accept, you can message each other. To be found yourself, add your birthday and choose to be listed in your profile.',
])}
{box('For adults only', 'Connecting is for members aged 18 and over who have added their date of birth. Phone numbers and links cannot be sent in messages.', 'warn')}
</section>

<section>
<h2>19 · Members: giving and fundraisers</h2>
{phones([('app-give', 'The Give tab recognises your church'), ('app-give-form', 'Amount, your number, then what it is for'), ('app-donate', 'Donate to a fundraiser in one sheet')])}
{steps([
  'Open the <strong>Give</strong> tab. If you follow one church it appears at the top as “Your church”; press <strong>Give</strong>.',
  'Type the amount, or tap a quick amount. Your <strong>mobile money number</strong> (from your profile) is right below it. A note reminds you that a prompt will be sent to that phone.',
  'Choose <strong>what it is for</strong>: the reasons your church has listed. Under More options you can give anonymously, repeat the gift weekly, monthly or quarterly, or add a note.',
  'Press <strong>Give</strong>. On your phone, enter your mobile money PIN when the prompt appears. The amount is deducted from your mobile money.',
  'You get a receipt in the app (Give → My gifts) and a thank-you email with a PDF receipt.',
  '<strong>Fundraisers:</strong> open one from Highlights or the Give tab, press <strong>Donate</strong>, enter the amount and confirm your number. The page shows how much is raised and the Givers, most recent first.',
])}
</section>

<section>
<h2>20 · Members: your profile and statistics</h2>
{phones([('app-profile-top', 'Your profile: photo, badges, and a prompt to finish it'), ('app-profile-progress', 'My progress: three rings'), ('app-profile-numbers', 'Your numbers and your week')])}
{ul([
  '<strong>Complete your profile.</strong> The page asks for your phone number first, then your photo, birthday, town, a line about you and your interests. The percentage shows how far along you are; every detail helps with suggestions and with giving.',
  '<strong>My churches</strong> lists the churches you follow; tap one to open it.',
  '<strong>My progress</strong> has three rings for this week or this month: <strong>Bible</strong> (minutes reading), <strong>Listening</strong> (radio and podcasts) and <strong>Sharing</strong> (verses and inspiring messages you shared). They fill against gentle goals: about 10 minutes of Bible a day, an hour of listening and a few shares a week.',
  '<strong>Your numbers</strong> shows your streak of days, chapters read, listening time, shares, highlights and prayers. <strong>See all</strong> opens every figure, for the week, the month or all time.',
])}
{phones([('app-stats', 'All your statistics in one place'), ('app-notification-settings', 'Choose which notifications you want')], 2)}
<p>These figures are only for you. Nobody else sees them, and churches only ever see anonymous totals.</p>
</section>

<section>
<h2>21 · Members: more to explore</h2>
{phones([('app-podcasts', 'Podcasts'), ('app-hymns', 'The hymn book'), ('app-events', 'Events and tickets'), ('app-prayer', 'The prayer wall')], 4)}
<table><thead><tr><th>Feature</th><th>How to use it</th></tr></thead><tbody>
<tr><td><strong>Radio</strong></td><td>Press the play button on Home. Gospel songs play day and night; it keeps playing with the screen locked.</td></tr>
<tr><td><strong>Podcasts</strong></td><td>Browse, follow a podcast, play episodes, change speed, and write notes while you listen.</td></tr>
<tr><td><strong>Hymns</strong></td><td>Open the hymn book, search by title or number, make the text bigger, use worship mode, and save a hymnal on your phone for offline use. Every hymn shows its credits.</td></tr>
<tr><td><strong>Sermons, devotionals, announcements</strong></td><td>From Home or your church page. Tap Share to send them on.</td></tr>
<tr><td><strong>Events</strong></td><td>See details, say you are going, buy a ticket with mobile money and show its QR code at the door.</td></tr>
<tr><td><strong>Live</strong></td><td>When your church goes live, a banner appears on Home and you get a notification.</td></tr>
<tr><td><strong>Prayer wall</strong></td><td>Share a request (public requests are approved by the church’s moderators first) and tap Pray for others.</td></tr>
<tr><td><strong>Groups and reading circles</strong></td><td>Join a study group, or read a plan together with up to six friends using a code.</td></tr>
<tr><td><strong>Notifications</strong></td><td>Me → Notification settings: choose news by church and by kind, set quiet hours, and press “send me a test”.</td></tr>
</tbody></table>
</section>

<section class="newpage">
<h2>22 · Church leaders: the portal</h2>
<p>Church staff sign in on the website and open their church’s admin portal. What each person sees depends on their role.</p>
{wide('web-church-dashboard', 'The church dashboard: what is happening at a glance')}
<h3>What the menu holds</h3>
<div class="grid3">
  <div class="card"><h4>Content</h4><p>Sermons and series, devotionals, announcements, reading plans, media library, feed posts.</p></div>
  <div class="card"><h4>Live &amp; events</h4><p>Live streams, events with RSVPs, tickets and QR check-in.</p></div>
  <div class="card"><h4>Giving</h4><p>Finance, campaigns (fundraisers), giving channels and reasons, payouts, reconciliation.</p></div>
  <div class="card"><h4>People</h4><p>Members, small groups, volunteers, attendance, pastoral care and counselling (confidential).</p></div>
  <div class="card"><h4>Community</h4><p>Prayer wall, testimonies, comments, quizzes and games, reports, send a notification.</p></div>
  <div class="card"><h4>Settings</h4><p>Church profile, staff and roles, website and branding, integrations, billing and plan, support, audit log.</p></div>
</div>
{steps([
  '<strong>First days:</strong> follow the setup checklist: add your logo and cover, service times and staff; turn on mobile money under Giving channels.',
  '<strong>Publish:</strong> create an announcement, a sermon or a devotional. Followers are notified (you choose whether to send a push).',
  '<strong>Events:</strong> create an event, set seats and ticket price; check people in with the QR scanner in the app.',
  '<strong>Send a notification</strong> to the whole church, a branch, a group or a stage (for example new members), by push, email or SMS.',
  '<strong>Staff and roles:</strong> invite people and give them a preset role (Editor, Finance, Media, Events, Moderator) or your own mix of permissions.',
])}
</section>

<section>
<h2>23 · Church leaders: money and records</h2>
{wide('web-church-reasons', 'Giving reasons: only the church adds, renames, hides or removes them')}
{steps([
  '<strong>Giving reasons</strong> (Finance → Giving reasons): add what members can give for. Choose which platform category it counts as; switch one off to hide it from members.',
  '<strong>Campaigns:</strong> create a fundraiser with a goal, a story, a picture and a closing date. Members donate from the app; progress and the list of givers update live.',
  '<strong>Record cash:</strong> use “Record cash / bank gift” so offline gifts appear in the same records and receipts.',
  '<strong>Financial records</strong> (Finance → Financial records): choose a period; see received, fees and net; the clusters by reason and by payment method; the chart over time; what is pending, refunded or waiting to be paid out; and the ledger of every gift. Filter by reason, fundraiser or method.',
  '<strong>Export:</strong> download the ledger (CSV), the summary (CSV) or a printable PDF for the board or the accountant.',
  '<strong>Refunds and bank transfers:</strong> finance staff can refund a gift or confirm a bank transfer; every change is logged.',
])}
</section>

<section>
<h2>24 · Church leaders: people and subscribers</h2>
<p>Open <strong>Subscribers</strong> in the portal for the full picture of who follows your church.</p>
{ul([
  '<strong>Followers</strong>, how many are waiting for approval and how many left.',
  '<strong>New followers</strong> in the last 7 and 30 days, with growth against the 30 days before, and a chart of the last 12 weeks.',
  '<strong>Opened the app</strong> in the last 30 days (as a percentage and a count), and how many gave in the last 90 days.',
  '<strong>What followers did in the app</strong> (all together): minutes reading the Bible, minutes listening, scriptures and messages shared. Nobody is named.',
  '<strong>Who they are:</strong> by stage (visitor, new member, member, worker, leader), age, gender, town, branch and where they joined from.',
  '<strong>Newest followers</strong> with their join date. Use <strong>Members</strong> to follow up, message and export.',
])}
{wide('web-church-analytics', 'Analytics: content, attendance and giving trends')}
</section>

<section>
<h2>25 · Church staff in the app</h2>
<p>Staff do the daily work from their phone: open <strong>Me → Manage your church</strong>.</p>
{ul([
  '<strong>Announcements and posts</strong> with photos, pinned or scheduled.',
  '<strong>Events</strong> with QR check-in at the door.',
  '<strong>Games:</strong> host a live quiz for the church.',
  '<strong>Prayer:</strong> approve requests and reply privately.',
  '<strong>Members and reports</strong> at a glance; <strong>broadcasts</strong> to followers; <strong>go live</strong>.',
  '<strong>Giving:</strong> this month’s giving clustered by reason, the fees and net, the latest gifts, and the list of giving reasons to add, hide or remove.',
])}
</section>

<section>
<h2>26 · The platform owner (super admin)</h2>
<p>The super admin area is at <strong>faithfellows.com/super-admin</strong>.</p>
{wide('web-super-dashboard', 'The super admin dashboard')}
{table(['Where', 'What you do there'], [
  ['<strong>Churches</strong>', 'Approve applications, suspend or reopen a church, open any church’s portal with Manage (recorded in the audit log).'],
  ['<strong>Plans, Invoices, Subscriptions</strong>', 'Set the monthly fee and limits of each plan, change a church’s plan or price, create or mark invoices paid. Invoices go out a week before the payment day.'],
  ['<strong>Subscribers</strong>', 'Every church side by side: followers, growth, payment status and whether it is warned or blocked for being quiet. Open one for the full statistics.'],
  ['<strong>App features</strong>', 'Switch podcasts, hymns and radio on or off for one church or all.'],
  ['<strong>Inspiration</strong>', 'Write the morning, mid-morning and evening messages; set the times they are pushed to every phone; press send to push one now.'],
  ['<strong>Email</strong>', 'Connect the Gmail account the platform sends from (receipts, thank-yous, reset codes), send a test, and choose who is emailed about every new account.'],
  ['<strong>Google sign-in</strong>', 'Save the Google client ID and secret; the page shows the redirect address to add in Google Cloud.'],
  ['<strong>Push test</strong>', 'Send a test notification to a member’s phone and see exactly what Expo and Google answered, with the fix for common Android problems.'],
  ['<strong>Settings</strong>', 'Free period, support contacts, announcements, and how many quiet days before a church is warned and then blocked.'],
  ['<strong>Hymn book, Podcasts, Gospel radio, Moderation</strong>', 'Add hymnals with credits, approve podcasts, upload radio songs, review reports.'],
  ['<strong>Database updates, Audit logs</strong>', 'Apply the changes a new version brings with one button; see who did what.'],
])}
{wide('web-super-inspiration', 'Inspiration: the library, the schedule and “send now”')}
{wide('web-super-subscribers', 'Subscribers across every church, with payment and standing')}
{wide('web-super-push', 'Push test: proof that notifications reach a phone')}
</section>
"""

D = part('Part D', 'Reference', 'Questions people ask, the numbers that matter and who to contact.')
D += f"""
<section>
<h2>27 · Questions and answers</h2>
{table(['Question', 'Answer'], [
  ['I did not get the reset code.', 'Check the spam folder, wait a minute and ask for a new code (available after 45 seconds). Make sure you used the email you signed up with.'],
  ['Google sign-in does not open.', 'The platform owner needs to add the redirect address in Google Cloud and publish the consent screen. Meanwhile use Continue with email.'],
  ['I gave but nothing was deducted.', 'The prompt must be approved with your PIN. Open Give → My gifts: a pending gift can be checked from there. Make sure the number is your mobile money number.'],
  ['Why is my Bible not in the ESV?', 'The ESV needs a licence key on the server. Until then the app uses the King James Version. You can pick another version at any time.'],
  ['I do not get notifications.', 'Allow notifications for FaithFellows in the phone’s settings; check Me → Notification settings and quiet hours; press “send me a test”.'],
  ['Who can see my statistics?', 'Only you. Churches see anonymous totals.'],
  ['Can I follow more than one church?', 'Yes, up to three.'],
  ['Our church page disappeared.', 'A church that stays quiet for 30 days is warned, then paused 14 days later; an unpaid fee can also pause it. Contact the platform owner; nothing is deleted.'],
  ['How do we get our members to join?', 'Share the church’s link and QR code from the portal, announce it on Sunday and put the QR code on the screen or the bulletin.'],
])}
</section>

<section>
<h2>28 · Key figures and settings</h2>
{table(['Setting', 'Default', 'Where it is changed'], [
  ['Starter plan', 'UGX 60,000 per month', 'Super admin → Plans'],
  ['Growth plan', 'UGX 150,000 per month', 'Super admin → Plans'],
  ['Custom domain add-on', 'UGX 60,000 per year', 'Server setting'],
  ['Free first period', '12 months', 'Super admin → Settings (free trial length)'],
  ['Grace after a due date', '14 days', 'Server setting'],
  ['Invoice and first reminder', 'A week before the payment day', 'Built in'],
  ['Quiet church warning / block', '30 days / 14 days later', 'Super admin → Settings'],
  ['Followed churches per member', '3', 'Server setting'],
  ['Minimum gift', 'UGX 500 (UGX 1,000 for a pledge)', 'Built in'],
  ['Inspiration push times', '06:30, 10:30, 18:30', 'Super admin → Inspiration'],
  ['Game question time', '10 seconds', 'Server setting'],
])}
<h3>Technology</h3>
<p>Website and API: Laravel 12 (PHP 8.2) with a MariaDB database, hosted at faithfellows.com. Mobile app: React Native with Expo (SDK 57), Android now and iPhone next. Notifications through Expo push (Firebase for Android) and email through Gmail. Mobile money through ioTec Pay. Updates reach installed apps over the air; website updates deploy from the code repository automatically. The API reference is in <code>web/docs/API.md</code>, the decision log in <code>web/DECISIONS.md</code> and the deployment guide in <code>web/docs/DEPLOYMENT_SHARED_HOSTING.md</code>.</p>
</section>

<section>
<h2>29 · Contact</h2>
<div class="grid2">
  <div class="card"><h4>Kakebe Technologies Limited</h4><p>Rm B1 Plot 43, Renzo City House, Obote Ave., Lira City<br>P.O Box 660031, Lira City<br>Phone: 0393 249 845<br>Email: service@kakebe.tech<br>Websites: www.kakebe.tech · www.kakebetechcamp.tech</p></div>
  <div class="card"><h4>The team</h4><p><strong>Joseph Ekure</strong> · FaithFellow Lead<br><strong>Oscar Jerom Okello</strong> · Technical Website Lead<br><strong>Sedrick Otolo</strong> · Team Lead</p></div>
</div>
<footer class="end"><span><img src="data:image/png;base64,{MARK_B64}" alt="" style="height:7mm;vertical-align:middle;margin-right:2mm">FaithFellows · faithfellows.com</span><span>“Empowering your ministry in the digital age”</span></footer>
</section>
"""

DOC = f"""<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>FaithFellows: documentation</title>
<style>{CSS}</style></head><body>
{cover}{toc}{A}{B}{C}{D}
</body></html>"""

(DOCS / 'FaithFellows-Documentation.html').write_text(DOC, encoding='utf-8')
print('written', len(DOC) // 1024, 'KB; missing screens:', sorted(set(missing)))
