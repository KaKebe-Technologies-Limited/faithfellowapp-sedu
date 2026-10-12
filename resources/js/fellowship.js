/**
 * FaithFellows — fellowship features (Sprint 10):
 * note taker, Bible reader, passage loader, notification pop-ups,
 * live quiz (player, host, scoreboard).
 */
import { http, toast } from './faithfellows';

const debounce = (fn, ms) => {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
};

/* ── In-app notification pop-up card ────────────────────────────────── */

window.ffNotificationPop = (n) => {
    let host = document.querySelector('.ff-notif-pops');
    if (!host) {
        host = document.createElement('div');
        host.className = 'ff-notif-pops';
        host.setAttribute('aria-live', 'polite');
        document.body.appendChild(host);
    }
    const card = document.createElement('a');
    card.className = 'ff-notif-pop';
    card.href = n.url;
    card.innerHTML = '<span class="ff-notif-pop-icon"><i class="bi"></i></span><span class="ff-notif-pop-text"><span class="ff-notif-pop-church"></span><strong></strong><span class="ff-notif-pop-body"></span></span><button type="button" class="btn-close" aria-label="Dismiss"></button>';
    card.querySelector('.bi').classList.add(n.icon || 'bi-bell');
    card.querySelector('.ff-notif-pop-church').textContent = n.church || 'FaithFellows';
    card.querySelector('strong').textContent = n.title || '';
    card.querySelector('.ff-notif-pop-body').textContent = n.body || '';
    const remove = () => { card.classList.add('is-leaving'); setTimeout(() => card.remove(), 250); };
    card.querySelector('.btn-close').addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); remove(); });
    host.appendChild(card);
    setTimeout(remove, 9000);
};

export function registerFellowship(Alpine) {
    /* ── Note taker (slide-out notebook beside what you read) ──────── */
    Alpine.data('noteTaker', (opts = {}) => ({
        open: false, loaded: false, status: 'idle', savedAt: null,
        title: '', body: '', color: 'paper', noteUrl: null,
        init() {
            this.persist = debounce(() => this.save(), 900);
            window.addEventListener('ff-note-insert', (e) => { this.open = true; this.insert(e.detail); });
            if (opts.inline) { this.open = true; this.load(); return; }
            this.open = window.matchMedia('(min-width: 1400px)').matches && localStorage.getItem('ff-notes-open') === '1';
            if (this.open) this.load();
            const dock = (v) => document.body.classList.toggle('ff-notes-docked', v);
            dock(this.open);
            this.$watch('open', (v) => { localStorage.setItem('ff-notes-open', v ? '1' : '0'); dock(v); if (v) this.load(); });
        },
        async load() {
            if (this.loaded) return;
            try {
                const n = await http(`${opts.loadUrl}?context=${encodeURIComponent(opts.context)}`);
                Object.assign(this, { title: n.title || '', body: n.body || '', color: n.color || 'paper', savedAt: n.saved_at, noteUrl: n.id ? n.url : null });
            } catch (e) { /* offline: start empty */ }
            this.loaded = true;
            this.$nextTick(() => this.$refs.body?.focus());
        },
        changed() { this.status = 'typing'; this.persist(); },
        async save() {
            if (!this.loaded) return;
            this.status = 'saving';
            try {
                const n = await http(opts.saveUrl, { method: 'PUT', body: {
                    context: opts.context, reference: opts.reference, url: opts.url, church_id: opts.churchId,
                    title: this.title, body: this.body, color: this.color,
                } });
                this.savedAt = n.saved_at; this.noteUrl = n.url; this.status = 'saved';
            } catch (e) { this.status = 'error'; }
        },
        insert(text) {
            const el = this.$refs.body;
            const add = (this.body && !this.body.endsWith('\n') ? '\n' : '') + text + '\n';
            if (el && document.activeElement === el) {
                const pos = el.selectionStart;
                this.body = this.body.slice(0, pos) + add + this.body.slice(pos);
            } else {
                this.body += add;
            }
            this.changed();
        },
        format(kind) {
            const prefix = { bullet: '- ', heading: '# ', quote: '> ' }[kind];
            if (prefix) return this.insert(prefix);
            if (kind === 'time') return this.insert(`— ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`);
        },
        get statusLabel() {
            return { typing: 'Typing…', saving: 'Saving…', saved: 'Saved', error: 'Not saved — offline?' }[this.status] || (this.savedAt ? 'Saved' : 'Autosaves as you type');
        },
    }));

    /* ── Bible reader: font size, verse selection, copy / note / share ─ */
    Alpine.data('bibleReader', (opts = {}) => ({
        size: Number(localStorage.getItem('ff-bible-size') || 19), selected: [],
        resize(d) { this.size = Math.min(28, Math.max(15, this.size + d)); localStorage.setItem('ff-bible-size', this.size); },
        toggle(v) { this.selected = this.selected.includes(v) ? this.selected.filter((x) => x !== v) : [...this.selected, v].sort((a, b) => a - b); },
        clear() { this.selected = []; },
        get label() {
            if (!this.selected.length) return '';
            const s = this.selected; const ranges = [];
            for (let i = 0; i < s.length; i++) {
                let j = i; while (j + 1 < s.length && s[j + 1] === s[j] + 1) j++;
                ranges.push(i === j ? `${s[i]}` : `${s[i]}–${s[j]}`); i = j;
            }
            return `${opts.reference}:${ranges.join(',')}`;
        },
        text() {
            return this.selected.map((v) => this.$root.querySelector(`[data-verse="${v}"] .ff-verse-text`)?.textContent.trim()).join(' ');
        },
        async copy() {
            try { await navigator.clipboard.writeText(`“${this.text()}” — ${this.label} (${opts.translation})`); toast('Verse copied.', 'success', 2500); } catch (e) { toast('Copy failed.', 'warning'); }
            this.clear();
        },
        note() {
            window.dispatchEvent(new CustomEvent('ff-note-insert', { detail: `> ${this.text()} (${this.label})` }));
            this.clear();
        },
        share() {
            const text = `“${this.text()}” — ${this.label}`;
            if (navigator.share) navigator.share({ text, url: location.href }).catch(() => {});
            else window.open(`https://wa.me/?text=${encodeURIComponent(text + ' ' + location.href)}`, '_blank', 'noopener');
            this.clear();
        },
    }));

    /* ── Passage loader for reading-plan days ──────────────────────── */
    Alpine.data('biblePassage', (url) => ({
        html: '', state: 'loading',
        async init() {
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (!res.ok) throw new Error();
                this.html = await res.text(); this.state = 'ready';
            } catch (e) { this.state = 'error'; }
        },
    }));

    /* ── Live quiz ─────────────────────────────────────────────────── */
    const quizBase = (opts) => ({
        s: opts.initial || {}, now: Date.now(), clockOffset: 0, busy: false, timer: null, poller: null,
        init() {
            this.tick = setInterval(() => (this.now = Date.now()), 100);
            this.refresh();
            this.poller = setInterval(() => this.refresh(), opts.every || 1000);
        },
        destroy() { clearInterval(this.tick); clearInterval(this.poller); },
        async refresh() {
            try {
                const s = await http(opts.stateUrl);
                if (s.question && s.question.remaining_ms !== undefined) s.question.deadline = Date.now() + s.question.remaining_ms;
                this.s = s;
                if (this.after) this.after();
                if (s.status === 'finished' || s.status === 'cancelled') clearInterval(this.poller);
            } catch (e) { /* keep last state */ }
        },
        get remaining() {
            if (!this.s.question || this.s.status !== 'question') return 0;
            return Math.max(0, Math.ceil((this.s.question.deadline - this.now) / 1000));
        },
        get progress() {
            if (!this.s.question) return 0;
            return Math.max(0, Math.min(100, ((this.s.question.deadline - this.now) / (this.s.question.time_limit * 1000)) * 100));
        },
        shape(i) { return ['bi-triangle-fill', 'bi-diamond-fill', 'bi-circle-fill', 'bi-square-fill'][i]; },
    });

    Alpine.data('quizPlayer', (opts = {}) => ({
        ...quizBase(opts),
        async answer(i) {
            if (this.busy || this.s.me?.answer !== null || this.s.status !== 'question' || this.remaining <= 0) return;
            this.busy = true;
            try {
                const r = await http(opts.answerUrl, { method: 'POST', body: { option: i, question: this.s.index } });
                this.s.me = { ...this.s.me, answer: i, ...r };
                if (navigator.vibrate) navigator.vibrate(30);
            } catch (e) { toast(e.data?.message || 'Too late for this question.', 'warning', 2500); } finally { this.busy = false; }
        },
    }));

    Alpine.data('quizHost', (opts = {}) => ({
        ...quizBase(opts),
        autoRevealed: -1,
        after() {
            // Reveal automatically when time runs out or everyone has answered.
            const q = this.s.question;
            if (this.s.status === 'question' && q && this.autoRevealed !== this.s.index
                && (q.remaining_ms <= 0 || (this.s.players_count > 0 && q.answered_count >= this.s.players_count))) {
                this.autoRevealed = this.s.index;
                this.act('reveal');
            }
        },
        async act(action) {
            if (this.busy) return;
            this.busy = true;
            try { this.s = await http(opts.actions[action], { method: 'POST' }); } catch (e) { toast(e.data?.message || 'Please try again.', 'error'); } finally { this.busy = false; this.refresh(); }
        },
        async copyLink() {
            try { await navigator.clipboard.writeText(opts.joinUrl); toast('Join link copied.', 'success', 2000); } catch (e) {}
        },
    }));

    Alpine.data('quizBoard', (opts = {}) => ({ ...quizBase({ ...opts, every: opts.every || 1500 }) }));
}
