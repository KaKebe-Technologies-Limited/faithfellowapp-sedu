/**
 * FaithFellows — interactive components (vanilla JS + Alpine.js).
 *
 * Heavy libraries (Chart.js, Quill, hls.js, Leaflet) are lazy-loaded only on
 * pages that need them to keep the first load small on slow connections.
 */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function http(url, { method = 'GET', body = null } = {}) {
    const res = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
            ...(body && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? (body instanceof FormData ? body : JSON.stringify(body)) : null,
        credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw Object.assign(new Error(data.message || 'Request failed'), { status: res.status, data });
    return data;
}

/* ── Toasts ──────────────────────────────────────────────────────────── */

const TOAST_ICONS = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };

export function toast(message, type = 'info', timeout = 5000) {
    let container = document.querySelector('.ff-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'ff-toast-container';
        container.setAttribute('aria-live', 'polite');
        document.body.appendChild(container);
    }
    const el = document.createElement('div');
    el.className = `ff-toast is-${type}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = `<i class="bi ${TOAST_ICONS[type] ?? TOAST_ICONS.info} ff-toast-icon" aria-hidden="true"></i>
        <div class="ff-toast-body"></div>
        <button type="button" class="btn-close" aria-label="Close"></button>`;
    el.querySelector('.ff-toast-body').textContent = message;
    const remove = () => { el.classList.add('is-leaving'); setTimeout(() => el.remove(), 220); };
    el.querySelector('.btn-close').addEventListener('click', remove);
    container.appendChild(el);
    if (timeout) setTimeout(remove, timeout);
}

/* ── Confetti (goal reached / milestones) ────────────────────────────── */

export function celebrate(pieces = 120) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.body.classList.contains('ff-lite')) return;
    const colors = ['#BC2649', '#D34268', '#F27EA8', '#F7B2D1', '#E8A317', '#1E9E5A'];
    const wrap = document.createElement('div');
    wrap.className = 'ff-celebrate';
    for (let i = 0; i < pieces; i++) {
        const p = document.createElement('i');
        p.style.left = `${Math.random() * 100}%`;
        p.style.background = colors[i % colors.length];
        p.style.animationDelay = `${Math.random() * 0.8}s`;
        p.style.animationDuration = `${2 + Math.random() * 1.5}s`;
        wrap.appendChild(p);
    }
    document.body.appendChild(wrap);
    setTimeout(() => wrap.remove(), 4200);
}

/* ── Formatting helpers ─────────────────────────────────────────────── */

export const money = (n, currency = 'UGX') =>
    `${currency} ${Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 0 })}`;

const fmtTime = (s) => {
    if (!isFinite(s)) return '0:00';
    const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = Math.floor(s % 60);
    return (h ? `${h}:${String(m).padStart(2, '0')}` : `${m}`) + `:${String(sec).padStart(2, '0')}`;
};

/* ── Alpine components ──────────────────────────────────────────────── */

export function registerComponents(Alpine) {
    // Giving form: quick amounts + custom amount + payment method switching.
    Alpine.data('givingForm', (opts = {}) => ({
        amount: opts.amount ?? '',
        custom: false,
        method: opts.method ?? '',
        currency: opts.currency ?? 'UGX',
        phoneMethods: ['iotec', 'mtn_momo', 'airtel_money'],
        pick(value) { this.amount = value; this.custom = false; },
        useCustom() { this.custom = true; this.amount = ''; this.$nextTick(() => this.$refs.custom?.focus()); },
        get needsPhone() { return this.phoneMethods.includes(this.method); },
        get formatted() { return money(this.amount, this.currency); },
        get valid() { return Number(this.amount) >= (opts.min ?? 500) && this.method; },
    }));

    // Sermon audio player with speed control and offline saving.
    Alpine.data('audioPlayer', (opts = {}) => ({
        playing: false, current: 0, duration: 0, rate: 1, saved: false, saving: false,
        init() {
            const a = this.$refs.audio;
            if (!a) return;
            a.addEventListener('loadedmetadata', () => (this.duration = a.duration));
            a.addEventListener('timeupdate', () => (this.current = a.currentTime));
            a.addEventListener('ended', () => (this.playing = false));
            a.addEventListener('play', () => { this.playing = true; this.track('play'); });
            a.addEventListener('pause', () => { this.playing = false; this.track('pause'); });
            if ('caches' in window && opts.src) {
                caches.open('ff-sermons').then((c) => c.match(opts.src)).then((hit) => (this.saved = !!hit));
            }
        },
        toggle() { const a = this.$refs.audio; a.paused ? a.play() : a.pause(); },
        seek(e) { this.$refs.audio.currentTime = e.target.value; },
        skip(sec) { this.$refs.audio.currentTime = Math.max(0, this.$refs.audio.currentTime + sec); },
        cycleRate() {
            const rates = [1, 1.25, 1.5, 2, 0.75];
            this.rate = rates[(rates.indexOf(this.rate) + 1) % rates.length];
            this.$refs.audio.playbackRate = this.rate;
        },
        get label() { return `${fmtTime(this.current)} / ${fmtTime(this.duration)}`; },
        async saveOffline() {
            if (!('caches' in window)) return toast('Offline saving is not supported on this browser.', 'warning');
            this.saving = true;
            try {
                const cache = await caches.open('ff-sermons');
                await cache.add(new Request(opts.src, { credentials: 'same-origin' }));
                const list = JSON.parse(localStorage.getItem('ff-offline') || '[]').filter((s) => s.src !== opts.src);
                list.unshift({ src: opts.src, title: opts.title, church: opts.church, url: location.href, savedAt: Date.now() });
                localStorage.setItem('ff-offline', JSON.stringify(list.slice(0, 50)));
                this.saved = true;
                toast('Saved for offline listening.', 'success');
            } catch (e) {
                toast('Could not save this sermon offline.', 'error');
            } finally { this.saving = false; }
        },
        async removeOffline() {
            const cache = await caches.open('ff-sermons');
            await cache.delete(opts.src);
            localStorage.setItem('ff-offline', JSON.stringify(JSON.parse(localStorage.getItem('ff-offline') || '[]').filter((s) => s.src !== opts.src)));
            this.saved = false;
        },
        track(event) {
            if (!opts.trackUrl || event !== 'pause' || this.current < 5) return;
            http(opts.trackUrl, { method: 'POST', body: { seconds: Math.round(this.current) } }).catch(() => {});
        },
    }));

    // Live stream player: HLS video / audio-only / embed + viewer heartbeat.
    Alpine.data('livePlayer', (opts = {}) => ({
        audioOnly: opts.audioOnly ?? document.body.classList.contains('ff-lite'),
        viewers: opts.viewers ?? 0,
        status: opts.status ?? 'live',
        hls: null,
        async init() {
            this.$watch('audioOnly', () => this.attach());
            await this.attach();
            if (opts.heartbeatUrl) {
                const beat = () => http(opts.heartbeatUrl, { method: 'POST' })
                    .then((d) => { this.viewers = d.viewers ?? this.viewers; this.status = d.status ?? this.status; })
                    .catch(() => {});
                beat();
                setInterval(beat, 30000);
            }
        },
        async attach() {
            const video = this.$refs.video;
            const audio = this.$refs.audio;
            if (this.hls) { this.hls.destroy(); this.hls = null; }
            const el = this.audioOnly ? audio : video;
            const src = this.audioOnly ? (opts.audioSrc || opts.src) : opts.src;
            if (!el || !src) return;
            if (src.endsWith('.m3u8') && !el.canPlayType('application/vnd.apple.mpegurl')) {
                const { default: Hls } = await import('hls.js');
                if (Hls.isSupported()) {
                    this.hls = new Hls({ capLevelToPlayerSize: true, startLevel: this.audioOnly ? 0 : -1 });
                    this.hls.loadSource(src);
                    this.hls.attachMedia(el);
                    if (this.audioOnly) this.hls.on(Hls.Events.MANIFEST_PARSED, () => { this.hls.currentLevel = 0; });
                }
            } else {
                el.src = src;
            }
        },
    }));

    // Moderated live chat with lightweight polling (works without WebSockets).
    Alpine.data('liveChat', (opts = {}) => ({
        messages: [], body: '', lastId: 0, sending: false,
        init() {
            this.fetch();
            setInterval(() => this.fetch(), opts.interval ?? 5000);
        },
        async fetch() {
            try {
                const data = await http(`${opts.fetchUrl}?after=${this.lastId}`);
                if (data.messages?.length) {
                    this.messages.push(...data.messages);
                    this.lastId = data.messages[data.messages.length - 1].id;
                    this.$nextTick(() => { const box = this.$refs.box; if (box) box.scrollTop = box.scrollHeight; });
                }
                if (data.hidden?.length) this.messages = this.messages.filter((m) => !data.hidden.includes(m.id));
            } catch (e) { /* offline — retry next tick */ }
        },
        async send() {
            if (!this.body.trim() || this.sending) return;
            this.sending = true;
            try {
                await http(opts.postUrl, { method: 'POST', body: { body: this.body } });
                this.body = '';
                await this.fetch();
            } catch (e) {
                toast(e.data?.message || 'Message not sent.', 'error');
            } finally { this.sending = false; }
        },
        async hide(id) {
            if (!opts.hideUrl) return;
            await http(opts.hideUrl.replace('__ID__', id), { method: 'POST' }).catch(() => {});
            this.messages = this.messages.filter((m) => m.id !== id);
        },
    }));

    // Waits for a mobile-money payment to be approved on the giver's phone.
    Alpine.data('paymentStatus', (opts = {}) => ({
        status: opts.status ?? 'pending', message: '', tries: 0,
        init() { if (this.status === 'pending' || this.status === 'processing') this.poll(); },
        async poll() {
            this.tries++;
            try {
                const d = await http(opts.url);
                this.status = d.status; this.message = d.message ?? '';
                if (d.status === 'succeeded') { celebrate(80); if (opts.successUrl) setTimeout(() => (location.href = opts.successUrl), 1800); return; }
                if (d.status === 'failed' || d.status === 'cancelled') return;
            } catch (e) { /* keep polling */ }
            if (this.tries < 60) setTimeout(() => this.poll(), this.tries < 10 ? 3000 : 6000);
            else this.status = 'timeout';
        },
    }));

    // "I prayed for you" toggle.
    Alpine.data('prayButton', (opts = {}) => ({
        prayed: !!opts.prayed, count: opts.count ?? 0, busy: false,
        async toggle() {
            if (opts.guest) return (location.href = opts.loginUrl);
            if (this.busy) return;
            this.busy = true;
            try {
                const d = await http(opts.url, { method: 'POST' });
                this.prayed = d.prayed; this.count = d.count;
                if (d.prayed) toast('Thank you for praying 🙏', 'success', 2500);
            } catch (e) { toast('Please try again.', 'error'); } finally { this.busy = false; }
        },
    }));

    // Reactions (amen / love / pray / celebrate).
    Alpine.data('reactions', (opts = {}) => ({
        counts: opts.counts ?? {}, mine: opts.mine ?? null,
        async react(type) {
            if (opts.guest) return (location.href = opts.loginUrl);
            const d = await http(opts.url, { method: 'POST', body: { type } }).catch(() => null);
            if (d) { this.counts = d.counts; this.mine = d.mine; }
        },
    }));

    // Notification bell — polls unread count.
    Alpine.data('notificationBell', (opts = {}) => ({
        count: opts.count ?? 0,
        init() {
            if (!opts.url) return;
            setInterval(() => http(opts.url).then((d) => (this.count = d.unread)).catch(() => {}), 60000);
        },
    }));

    // Website builder — drag & drop block ordering with enable toggles.
    Alpine.data('blockEditor', (opts = {}) => ({
        blocks: opts.blocks ?? [],
        labels: opts.labels ?? {},
        dragIndex: null, overIndex: null,
        start(i) { this.dragIndex = i; },
        over(i) { this.overIndex = i; },
        drop(i) {
            if (this.dragIndex === null || this.dragIndex === i) return this.reset();
            const [moved] = this.blocks.splice(this.dragIndex, 1);
            this.blocks.splice(i, 0, moved);
            this.reset();
        },
        move(i, dir) {
            const j = i + dir;
            if (j < 0 || j >= this.blocks.length) return;
            [this.blocks[i], this.blocks[j]] = [this.blocks[j], this.blocks[i]];
        },
        reset() { this.dragIndex = null; this.overIndex = null; },
        get json() { return JSON.stringify(this.blocks); },
    }));

    // Permission matrix: toggle a whole group / column.
    Alpine.data('permissionMatrix', () => ({
        toggleAll(selector, checked) {
            this.$root.querySelectorAll(selector).forEach((cb) => { if (!cb.disabled) cb.checked = checked; });
        },
    }));

    // Geofenced attendance check-in.
    Alpine.data('geoCheckin', (opts = {}) => ({
        state: 'idle', message: '',
        locate() {
            if (!navigator.geolocation) { this.state = 'error'; this.message = 'Location is not available on this device.'; return; }
            this.state = 'locating';
            navigator.geolocation.getCurrentPosition(async (pos) => {
                try {
                    const d = await http(opts.url, { method: 'POST', body: { lat: pos.coords.latitude, lng: pos.coords.longitude } });
                    this.state = 'done'; this.message = d.message; toast(d.message, 'success');
                } catch (e) { this.state = 'error'; this.message = e.data?.message || 'Check-in failed.'; }
            }, () => { this.state = 'error'; this.message = 'We could not read your location.'; }, { enableHighAccuracy: true, timeout: 15000 });
        },
    }));

    // Countdown to a scheduled service / event.
    Alpine.data('countdown', (target) => ({
        left: '',
        init() { this.tick(); setInterval(() => this.tick(), 1000); },
        tick() {
            const diff = Math.max(0, new Date(target).getTime() - Date.now()) / 1000;
            const d = Math.floor(diff / 86400), h = Math.floor((diff % 86400) / 3600), m = Math.floor((diff % 3600) / 60), s = Math.floor(diff % 60);
            this.left = diff <= 0 ? 'Starting now' : `${d ? d + 'd ' : ''}${h}h ${m}m ${s}s`;
        },
    }));
}

/* ── Progressive enhancement (data attributes) ──────────────────────── */

function initFlashToasts() {
    document.querySelectorAll('[data-ff-toast]').forEach((el) => {
        toast(el.dataset.ffToast, el.dataset.ffToastType || 'info');
        el.remove();
    });
}

function initCopyAndShare() {
    document.addEventListener('click', async (e) => {
        const copyBtn = e.target.closest('[data-ff-copy]');
        if (copyBtn) {
            e.preventDefault();
            try { await navigator.clipboard.writeText(copyBtn.dataset.ffCopy); toast('Link copied to clipboard.', 'success', 2500); }
            catch { toast('Copy failed — please copy manually.', 'warning'); }
        }
        const shareBtn = e.target.closest('[data-ff-share]');
        if (shareBtn && navigator.share) {
            e.preventDefault();
            navigator.share({ title: shareBtn.dataset.ffShareTitle || document.title, url: shareBtn.dataset.ffShare }).catch(() => {});
        }
    });
}

function initConfirm() {
    document.addEventListener('submit', (e) => {
        const msg = e.target.dataset?.ffConfirm;
        if (msg && !window.confirm(msg)) e.preventDefault();
    });
}

function initRings() {
    document.querySelectorAll('.ff-ring[data-value]').forEach((ring) => {
        const circle = ring.querySelector('.ff-ring-value');
        if (!circle) return;
        const r = circle.r.baseVal.value, c = 2 * Math.PI * r;
        const pct = Math.min(100, Math.max(0, Number(ring.dataset.value)));
        circle.style.strokeDasharray = `${c}`;
        circle.style.strokeDashoffset = `${c}`;
        requestAnimationFrame(() => setTimeout(() => (circle.style.strokeDashoffset = `${c - (pct / 100) * c}`), 60));
    });
}

async function initCharts() {
    const nodes = document.querySelectorAll('[data-ff-chart]');
    if (!nodes.length) return;
    const { Chart, registerables } = await import('chart.js');
    Chart.register(...registerables);
    Chart.defaults.font.family = '"Space Grotesk", -apple-system, sans-serif';
    Chart.defaults.color = '#727272';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    const palette = ['#BC2649', '#F27EA8', '#2D7FF9', '#1E9E5A', '#E8A317', '#8E1C37', '#A5A5A5'];
    nodes.forEach((el) => {
        const cfg = JSON.parse(el.dataset.ffChart);
        const type = cfg.type || 'line';
        cfg.data.datasets.forEach((ds, i) => {
            const color = ds.color || palette[i % palette.length];
            if (type === 'line') {
                Object.assign(ds, { borderColor: color, backgroundColor: color + '22', fill: ds.fill ?? true, tension: 0.35, pointRadius: 2, borderWidth: 2 });
            } else if (type === 'bar') {
                Object.assign(ds, { backgroundColor: ds.backgroundColor || color, borderRadius: 6, maxBarThickness: 36 });
            } else {
                ds.backgroundColor = ds.backgroundColor || palette;
                ds.borderWidth = 0;
            }
        });
        new Chart(el, {
            type,
            data: cfg.data,
            options: Object.assign({
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: cfg.legend ?? (type !== 'line' && type !== 'bar') } },
                scales: (type === 'line' || type === 'bar') ? {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { precision: 0 } },
                } : undefined,
            }, cfg.options || {}),
        });
    });
}

async function initEditors() {
    const nodes = document.querySelectorAll('textarea[data-ff-editor]');
    if (!nodes.length) return;
    const [{ default: Quill }] = await Promise.all([import('quill'), import('quill/dist/quill.snow.css')]);
    nodes.forEach((textarea) => {
        const wrap = document.createElement('div');
        wrap.className = 'ff-ql-editor';
        const holder = document.createElement('div');
        wrap.appendChild(holder);
        textarea.insertAdjacentElement('afterend', wrap);
        textarea.style.display = 'none';
        const quill = new Quill(holder, {
            theme: 'snow',
            placeholder: textarea.placeholder || 'Write something inspiring…',
            modules: {
                toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'], ['blockquote', 'link'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']],
            },
        });
        quill.root.innerHTML = textarea.value;
        quill.on('text-change', () => { textarea.value = quill.getText().trim() ? quill.root.innerHTML : ''; });
    });
}

async function initMaps() {
    const nodes = document.querySelectorAll('[data-ff-map]');
    if (!nodes.length) return;
    const [{ default: L }] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);
    nodes.forEach((el) => {
        const points = JSON.parse(el.dataset.ffMap || '[]').filter((p) => p.lat && p.lng);
        const map = L.map(el, { scrollWheelZoom: false }).setView(points[0] ? [points[0].lat, points[0].lng] : [1.3733, 32.2903], points.length === 1 ? 14 : 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
        const icon = L.divIcon({ className: '', html: '<div style="width:18px;height:18px;border-radius:50%;background:#BC2649;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3)"></div>', iconSize: [18, 18] });
        const markers = points.map((p) => {
            const m = L.marker([p.lat, p.lng], { icon }).addTo(map);
            if (p.label) {
                const popup = document.createElement('div');
                const strong = document.createElement(p.url ? 'a' : 'strong');
                strong.textContent = p.label;
                if (p.url) strong.href = p.url;
                popup.appendChild(strong);
                if (p.sub) { const s = document.createElement('div'); s.className = 'small text-muted'; s.textContent = p.sub; popup.appendChild(s); }
                m.bindPopup(popup);
            }
            return m;
        });
        if (markers.length > 1) map.fitBounds(L.featureGroup(markers).getBounds().pad(0.2));
    });
}

function initLiteMode() {
    document.querySelectorAll('[data-ff-lite-toggle]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const on = !document.body.classList.contains('ff-lite');
            document.cookie = `ff_lite=${on ? 1 : 0};path=/;max-age=31536000;SameSite=Lax`;
            location.reload();
        });
    });
}

// Branded verse card → PNG (canvas, no server round-trip).
function initVerseCards() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-ff-verse-download]');
        if (!btn) return;
        e.preventDefault();
        const text = btn.dataset.verse, ref = btn.dataset.reference, church = btn.dataset.church || 'FaithFellows';
        const c = document.createElement('canvas');
        c.width = 1080; c.height = 1080;
        const ctx = c.getContext('2d');
        const g = ctx.createLinearGradient(0, 0, 1080, 1080);
        g.addColorStop(0, '#BC2649'); g.addColorStop(1, '#F27EA8');
        ctx.fillStyle = g; ctx.fillRect(0, 0, 1080, 1080);
        ctx.strokeStyle = 'rgba(255,255,255,.12)'; ctx.lineWidth = 60;
        ctx.beginPath(); ctx.roundRect(760, 760, 420, 420, 120); ctx.stroke();
        ctx.fillStyle = '#fff'; ctx.textAlign = 'left';
        ctx.font = '600 54px "Space Grotesk", sans-serif';
        const words = `“${text}”`.split(' '); let line = '', y = 260; const lines = [];
        words.forEach((w) => { const t = line ? `${line} ${w}` : w; if (ctx.measureText(t).width > 880) { lines.push(line); line = w; } else line = t; });
        lines.push(line);
        lines.slice(0, 10).forEach((l) => { ctx.fillText(l, 100, y); y += 74; });
        ctx.font = '700 40px "Space Grotesk", sans-serif'; ctx.fillText(ref, 100, y + 40);
        ctx.font = '500 30px "Space Grotesk", sans-serif'; ctx.globalAlpha = .85; ctx.fillText(`${church} · FaithFellows`, 100, 980);
        const a = document.createElement('a');
        a.download = `${ref.replace(/[^a-z0-9]+/gi, '-')}.png`; a.href = c.toDataURL('image/png'); a.click();
    });
}

function initOfflineList() {
    const list = document.querySelector('[data-ff-offline-list]');
    if (!list) return;
    const items = JSON.parse(localStorage.getItem('ff-offline') || '[]');
    if (!items.length) return;
    list.innerHTML = '';
    items.forEach((s) => {
        const row = document.createElement('div');
        row.className = 'ff-card ff-card-sm mb-3';
        const t = document.createElement('div'); t.className = 'fw-semibold text-ff-black'; t.textContent = s.title;
        const sub = document.createElement('div'); sub.className = 'small text-ff-muted mb-2'; sub.textContent = s.church || '';
        const audio = document.createElement('audio'); audio.controls = true; audio.preload = 'none'; audio.src = s.src; audio.className = 'w-100';
        row.append(t, sub, audio);
        list.appendChild(row);
    });
}

function initServiceWorker() {
    if ('serviceWorker' in navigator && location.protocol !== 'file:') {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
}

// Web push subscription (VAPID).
async function initPush() {
    const btn = document.querySelector('[data-ff-push-subscribe]');
    if (!btn) return;
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !btn.dataset.vapid) { btn.disabled = true; return; }
    btn.addEventListener('click', async () => {
        try {
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') return toast('Push notifications were blocked.', 'warning');
            const reg = await navigator.serviceWorker.ready;
            const key = Uint8Array.from(atob(btn.dataset.vapid.replace(/-/g, '+').replace(/_/g, '/') + '=='.slice(0, (4 - btn.dataset.vapid.length % 4) % 4)), (c) => c.charCodeAt(0));
            const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
            await http(btn.dataset.url, { method: 'POST', body: sub.toJSON() });
            toast('Push notifications enabled on this device.', 'success');
        } catch (e) { toast('Could not enable push notifications.', 'error'); }
    });
}

export function boot() {
    initFlashToasts();
    initCopyAndShare();
    initConfirm();
    initRings();
    initLiteMode();
    initVerseCards();
    initOfflineList();
    initServiceWorker();
    initPush();
    initCharts();
    initEditors();
    initMaps();
    if (document.querySelector('[data-ff-celebrate]')) celebrate();
    window.ff = { toast, celebrate, money, http };
}
