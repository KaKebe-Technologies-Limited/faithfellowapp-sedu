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

// localStorage can throw (private mode, blocked storage) — never let that break a page.
const stored = (key) => { try { return localStorage.getItem(key); } catch (e) { return null; } };
const store = (key, value) => { try { localStorage.setItem(key, value); } catch (e) { /* ignore */ } };

/** Copy text, falling back to a hidden textarea where the Clipboard API is unavailable (plain http). */
async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch (e) {
        const area = Object.assign(document.createElement('textarea'), { value: text, readOnly: true });
        area.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
        document.body.appendChild(area);
        area.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
        area.remove();
        return ok;
    }
}

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
            this.open = window.matchMedia('(min-width: 1400px)').matches && stored('ff-notes-open') === '1';
            if (this.open) this.load();
            const dock = (v) => document.body.classList.toggle('ff-notes-docked', v);
            dock(this.open);
            this.$watch('open', (v) => { store('ff-notes-open', v ? '1' : '0'); dock(v); if (v) this.load(); });
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

    /* ── Bible reader: layout, font size, tap verses to copy / highlight / note / share ─ */
    // Verses carry data-key="BOOK.chapter.verse"; their chapter's container has data-label.
    Alpine.data('bibleReader', (opts = {}) => ({
        size: Number(stored('ff-bible-size') || 19),
        lines: (stored('ff-bible-layout') || 'lines') === 'lines',
        selected: [],
        resize(d) { this.size = Math.min(28, Math.max(15, this.size + d)); store('ff-bible-size', this.size); },
        toggleLines() { this.lines = !this.lines; store('ff-bible-layout', this.lines ? 'lines' : 'paragraph'); },
        pick(e) {
            const el = e.target.closest('.ff-verse[data-key]');
            // Let people drag-select text normally; a tap toggles the verse.
            if (!el || (e.type === 'click' && String(window.getSelection?.() || '').length > 1)) return;
            el.classList.toggle('is-selected');
            this.selected = [...this.$root.querySelectorAll('.ff-verse.is-selected')].map((v) => v.dataset.key);
            if (navigator.vibrate) navigator.vibrate(12);
        },
        verses() { return this.selected.map((k) => this.$root.querySelector(`.ff-verse[data-key="${k}"]`)).filter(Boolean); },
        clear() { this.verses().forEach((el) => el.classList.remove('is-selected')); this.selected = []; },
        /** "John 3:16–18, 21; John 4:1" */
        get label() {
            const groups = [];
            this.verses().forEach((el) => {
                const name = el.closest('[data-label]')?.dataset.label || '';
                const v = Number(el.dataset.key.split('.')[2]);
                const last = groups[groups.length - 1];
                if (last && last.name === name) last.verses.push(v); else groups.push({ name, verses: [v] });
            });
            return groups.map(({ name, verses }) => {
                const ranges = [];
                for (let i = 0; i < verses.length; i++) {
                    let j = i; while (j + 1 < verses.length && verses[j + 1] === verses[j] + 1) j++;
                    ranges.push(i === j ? `${verses[i]}` : `${verses[i]}–${verses[j]}`); i = j;
                }
                return `${name}:${ranges.join(', ')}`;
            }).join('; ');
        },
        text() { return this.verses().map((el) => el.querySelector('.ff-verse-text')?.textContent.trim()).join(' '); },
        allColored(c) { const v = this.verses(); return v.length > 0 && v.every((el) => el.dataset.hl === c); },
        anyColored() { return this.verses().some((el) => el.dataset.hl); },
        async copy() {
            const ok = await copyText(`“${this.text()}”\n— ${this.label} (${opts.translation})`);
            toast(ok ? 'Copied — paste it anywhere.' : 'Copy failed. Long-press the text to copy it.', ok ? 'success' : 'warning', 2500);
            this.clear();
        },
        async highlight(color) {
            const els = this.verses();
            const before = els.map((el) => el.dataset.hl);
            els.forEach((el) => { if (color) el.dataset.hl = color; else delete el.dataset.hl; });
            const keys = [...this.selected];
            this.clear();
            try {
                await http(opts.highlightUrl, { method: 'POST', body: { verses: keys, color } });
                toast(color ? 'Highlighted. Find it again under Bible → My highlights.' : 'Highlight removed.', 'success', 2200);
            } catch (e) {
                els.forEach((el, i) => { if (before[i]) el.dataset.hl = before[i]; else delete el.dataset.hl; });
                toast('Could not save the highlight. Please try again.', 'warning');
            }
        },
        note() {
            window.dispatchEvent(new CustomEvent('ff-note-insert', { detail: `> ${this.text()} (${this.label})` }));
            this.clear();
        },
        share() {
            const text = `“${this.text()}” — ${this.label} (${opts.translation})`;
            if (navigator.share) navigator.share({ text }).catch(() => {});
            else window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank', 'noopener');
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
    // The server runs the clock (question → answer → next). Each screen keeps at
    // most one request in flight and wakes exactly when the current phase ends,
    // so slow connections or a busy server never pile requests up.
    const quizBase = (opts) => ({
        s: opts.initial || {}, now: Date.now(), busy: false, loading: false, poller: null, baseRanks: {},
        init() {
            this.stamp(this.s);
            this.tick = setInterval(() => (this.now = Date.now()), 100);
            this.refresh();
            document.addEventListener('visibilitychange', () => { if (!document.hidden) this.refresh(); });
        },
        destroy() { clearInterval(this.tick); clearTimeout(this.poller); },
        stamp(s) {
            if (s.question && s.question.remaining_ms !== undefined) s.question.deadline = Date.now() + s.question.remaining_ms;
            if (s.next_in_ms !== undefined) s.next_deadline = Date.now() + s.next_in_ms;
        },
        apply(s) {
            this.stamp(s);
            // Rank arrows compare with the standings when the question started.
            if (s.index !== this.s.index) this.baseRanks = Object.fromEntries((this.s.leaderboard || []).map((r) => [r.id, r.rank]));
            if (s.status === 'question' && this.s.status !== 'question' && navigator.vibrate) navigator.vibrate(40);
            flip(this.$root, () => { this.s = s; });
            if (this.after) this.after();
        },
        /** When to ask again: the regular beat, or sooner if a phase ends first. */
        schedule() {
            clearTimeout(this.poller);
            const s = this.s;
            if (s.status === 'finished' || s.status === 'cancelled') return;
            const every = s.status === 'lobby' ? 1000 : (opts.every || 1000);
            // The server shows the answer 1 s after the timer (network grace) and starts the next question on time.
            const due = s.status === 'question' ? s.question?.deadline + 1050 : (s.status === 'reveal' ? s.next_deadline + 100 : null);
            const until = due ? due - Date.now() : every;
            this.poller = setTimeout(() => this.refresh(), until > 0 ? Math.min(every, until) : Math.min(every, 400));
        },
        async refresh() {
            if (this.loading) return;
            this.loading = true;
            try { this.apply(await http(opts.stateUrl)); } catch (e) { /* keep the last state and try again */ } finally { this.loading = false; this.schedule(); }
        },
        get remaining() {
            if (!this.s.question || this.s.status !== 'question') return 0;
            return Math.max(0, Math.ceil((this.s.question.deadline - this.now) / 1000));
        },
        get progress() {
            if (!this.s.question) return 0;
            return Math.max(0, Math.min(100, ((this.s.question.deadline - this.now) / (this.s.question.time_limit * 1000)) * 100));
        },
        get nextIn() {
            return this.s.status === 'reveal' && this.s.next_deadline ? Math.max(0, Math.ceil((this.s.next_deadline - this.now) / 1000)) : 0;
        },
        get nextProgress() {
            const total = (this.s.timing?.reveal || 5) * 1000;
            return this.s.status === 'reveal' && this.s.next_deadline ? Math.max(0, Math.min(100, ((this.s.next_deadline - this.now) / total) * 100)) : 0;
        },
        move(row) {
            const before = this.baseRanks[row.id];
            if (before === undefined) return 0;
            return before - row.rank;
        },
        /** Top rows plus the viewer's own row when they are further down. */
        board(n = 5) {
            const rows = (this.s.leaderboard || []).slice(0, n);
            const me = this.s.me;
            if (me?.id && !rows.some((r) => r.id === me.id)) {
                rows.push({ id: me.id, rank: me.rank, name: me.name, score: me.score, gained: 0, answered: me.answer !== null && me.answer !== undefined, me: true, gap: true });
            }
            return rows;
        },
        medal(rank) { return ['🥇', '🥈', '🥉'][rank - 1] || rank; },
        shape(i) { return ['bi-triangle-fill', 'bi-diamond-fill', 'bi-circle-fill', 'bi-square-fill'][i]; },
    });

    // Combine component parts keeping getters live (object spread would freeze them into values).
    const mix = (...parts) => parts.reduce((o, p) => Object.defineProperties(o, Object.getOwnPropertyDescriptors(p)), {});

    // Smoothly slide leaderboard rows to their new places ([data-flip] keys).
    const flip = (root, mutate) => {
        const rows = root ? [...root.querySelectorAll('[data-flip]')] : [];
        const before = new Map(rows.map((el) => [el.dataset.flip, el.getBoundingClientRect().top]));
        mutate();
        if (!before.size || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        window.Alpine.nextTick(() => root.querySelectorAll('[data-flip]').forEach((el) => {
            const top = before.get(el.dataset.flip);
            const dy = top === undefined ? 0 : top - el.getBoundingClientRect().top;
            if (Math.abs(dy) > 1) el.animate([{ transform: `translateY(${dy}px)` }, { transform: 'none' }], { duration: 550, easing: 'cubic-bezier(.2,.8,.2,1)' });
        }));
    };

    Alpine.data('quizPlayer', (opts = {}) => mix(quizBase({ ...opts, every: opts.every || 1500 }), {
        async answer(i) {
            if (this.busy || (this.s.me?.answer !== null && this.s.me?.answer !== undefined) || this.s.status !== 'question' || this.remaining <= 0) return;
            this.busy = true;
            try {
                const r = await http(opts.answerUrl, { method: 'POST', body: { option: i, question: this.s.index } });
                this.s.me = { ...this.s.me, answer: i, ...r };
                if (navigator.vibrate) navigator.vibrate(30);
                this.refresh();
            } catch (e) { toast(e.data?.message || 'Too late for this question.', 'warning', 2500); } finally { this.busy = false; }
        },
    }));

    Alpine.data('quizHost', (opts = {}) => mix(quizBase(opts), {
        async act(action) {
            if (this.busy) return;
            this.busy = true;
            try { this.apply(await http(opts.actions[action], { method: 'POST' })); this.schedule(); } catch (e) { toast(e.data?.message || 'Please try again.', 'error'); } finally { this.busy = false; }
        },
        // "Skip": show the answer now, or go straight to the next question.
        skip() { this.act(this.s.status === 'question' ? 'reveal' : 'next'); },
        async copyLink() {
            if (await copyText(opts.joinUrl)) toast('Join link copied.', 'success', 2000);
        },
    }));

    Alpine.data('quizBoard', (opts = {}) => quizBase({ ...opts, every: opts.every || 1500 }));
}
