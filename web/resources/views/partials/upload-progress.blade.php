{{--
    A form that sends files shows how far it has got: a card over the page with the FaithFellows ring, the percentage, the size sent, the speed and the time
    left, then "Saving…" while the server finishes. Used by a form that has the attribute data-ff-upload and answers JSON ({ redirect }) when asked for it.
    A form with no file chosen is sent the normal way. Errors come back into the same card, with the fields marked, and the person goes back to the form.
--}}
<div id="ff-upload-overlay" class="ff-upload-overlay" hidden role="dialog" aria-modal="true" aria-labelledby="ff-upload-title">
    <div class="ff-upload-sheet">
        <div class="ff-upload is-working" id="ff-upload-card">
            <div class="ff-upload-ring" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <svg viewBox="0 0 80 80" aria-hidden="true"><circle class="track" cx="40" cy="40" r="35"/><circle class="arc" cx="40" cy="40" r="35"/></svg>
                <span class="ff-upload-logo"><x-logo-mark :size="36" class="text-ff-primary" /></span>
                <span class="ff-upload-state" aria-hidden="true"></span>
            </div>
            <div class="ff-upload-info">
                <div class="ff-upload-name" id="ff-upload-title">{{ __('Sending your files…') }}</div>
                <div class="ff-upload-status" id="ff-upload-status" aria-live="polite"></div>
            </div>
            <div class="ff-upload-pct" id="ff-upload-pct">0%</div>
        </div>
        <ul class="ff-upload-files" id="ff-upload-files"></ul>
        <ul class="ff-upload-problems" id="ff-upload-problems" hidden></ul>
        <p class="ff-upload-note" id="ff-upload-note">{{ __('Keep this page open until it says it is done.') }}</p>
        <div class="ff-upload-actions">
            <button type="button" class="btn btn-ff-ghost" id="ff-upload-cancel">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-ff-primary" id="ff-upload-back" hidden>{{ __('Back to the form') }}</button>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (() => {
            const overlay = document.getElementById('ff-upload-overlay');
            const card = document.getElementById('ff-upload-card');
            const arc = card.querySelector('.arc');
            const ring = card.querySelector('.ff-upload-ring');
            const title = document.getElementById('ff-upload-title');
            const status = document.getElementById('ff-upload-status');
            const pct = document.getElementById('ff-upload-pct');
            const filesList = document.getElementById('ff-upload-files');
            const problems = document.getElementById('ff-upload-problems');
            const note = document.getElementById('ff-upload-note');
            const cancel = document.getElementById('ff-upload-cancel');
            const back = document.getElementById('ff-upload-back');
            const CIRCLE = 2 * Math.PI * 35;
            const words = {
                sending: @json(__('Sending your files…')),
                saving: @json(__('Saving and telling your members…')),
                done: @json(__('Done. Taking you back…')),
                keepOpen: @json(__('Keep this page open until it says it is done.')),
                lost: @json(__('The connection was lost. Check your internet connection, then try again.')),
                expired: @json(__('The page expired, or the files are bigger than the server allows. Reload the page and try again.')),
                tooBig: @json(__('The files are bigger than the server allows.')),
                failed: @json(__('Something went wrong on the server. It may already be saved: check the list before you try again, so it is not added twice.')),
                slow: @json(__('The server took too long to answer. It may already be saved: check the list before you try again, so it is not added twice.')),
                code: @json(__('(error :n)')),
                ref: @json(__('Reference: :r')),
                fix: @json(__('Some details need fixing:')),
                left: @json(__('about :t left')),
            };
            arc.style.strokeDasharray = CIRCLE;

            const size = (bytes) => bytes >= 1048576 ? (bytes / 1048576).toFixed(bytes >= 10485760 ? 0 : 1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
            const clock = (s) => s >= 90 ? Math.round(s / 60) + ' min' : Math.max(1, Math.round(s)) + ' s';
            let xhr = null;
            let working = false;

            function show(percent, text, state) {
                const value = Math.max(0, Math.min(100, Math.round(percent)));
                arc.style.strokeDashoffset = CIRCLE * (1 - value / 100);
                ring.setAttribute('aria-valuenow', value);
                pct.textContent = value + '%';
                if (text !== undefined) status.textContent = text;
                if (state) { card.classList.remove('is-working', 'is-adding', 'is-done', 'is-error'); card.classList.add(state); }
            }

            function finish(failed) {
                working = false;
                cancel.hidden = true;
                back.hidden = !failed;
                note.hidden = failed;
            }

            function fail(message, errors) {
                show(Number(ring.getAttribute('aria-valuenow')) || 0, message, 'is-error');
                title.textContent = @json(__('It could not be saved'));
                problems.innerHTML = '';
                Object.values(errors || {}).flat().slice(0, 6).forEach((m) => { const li = document.createElement('li'); li.textContent = m; problems.appendChild(li); });
                problems.hidden = !problems.children.length;
                finish(true);
            }

            function send(form, files) {
                working = true;
                title.textContent = words.sending;
                filesList.innerHTML = '';
                files.forEach(({ file }) => {
                    const li = document.createElement('li');
                    const name = document.createElement('span'); name.textContent = file.name;
                    const length = document.createElement('span'); length.textContent = size(file.size);
                    li.append(name, length); filesList.appendChild(li);
                });
                problems.hidden = true; problems.innerHTML = '';
                note.hidden = false; note.textContent = words.keepOpen;
                cancel.hidden = false; back.hidden = true;
                show(0, '…', 'is-working');
                overlay.hidden = false;
                document.body.style.overflow = 'hidden';
                form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
                form.querySelectorAll('.ff-upload-feedback').forEach((el) => el.remove());

                const body = new FormData(form);
                const total = files.reduce((sum, { file }) => sum + file.size, 0);
                const started = Date.now();
                xhr = new XMLHttpRequest();
                xhr.open('POST', form.action);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.upload.onprogress = (e) => {
                    if (!e.lengthComputable) return;
                    const seconds = Math.max(0.5, (Date.now() - started) / 1000);
                    const speed = e.loaded / seconds;
                    const left = speed > 0 ? (e.total - e.loaded) / speed : 0;
                    show(Math.min(99, (e.loaded / e.total) * 100), size(e.loaded) + ' / ' + size(e.total) + ' · ' + size(speed) + '/s' + (left > 3 && e.loaded > 262144 ? ' · ' + words.left.replace(':t', clock(left)) : ''), 'is-working');
                };
                xhr.upload.onload = () => { title.textContent = words.saving; show(100, words.saving, 'is-adding'); };
                xhr.onload = () => {
                    let data = {};
                    try { data = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }
                    if (xhr.status >= 200 && xhr.status < 300 && data.redirect) {
                        working = false;
                        title.textContent = words.done;
                        show(100, data.message || '', 'is-done');
                        cancel.hidden = true;
                        setTimeout(() => { window.location.href = data.redirect; }, 900);
                        return;
                    }
                    if (xhr.status === 422) {
                        Object.entries(data.errors || {}).forEach(([field, messages]) => {
                            const input = form.querySelector('[name="' + field + '"]');
                            if (!input) return;
                            input.classList.add('is-invalid');
                            const hint = document.createElement('div');
                            hint.className = 'invalid-feedback d-block ff-upload-feedback';
                            hint.textContent = [].concat(messages)[0];
                            input.insertAdjacentElement('afterend', hint);
                        });
                        return fail(words.fix, data.errors);
                    }
                    if (xhr.status === 419) return fail(words.expired, null);
                    if (xhr.status === 413) return fail(words.tooBig, null);
                    // The status and, when the server noted the error, its reference, so the problem can be found and fixed.
                    const detail = [words.code.replace(':n', xhr.status), data.reference ? words.ref.replace(':r', data.reference) : ''].filter(Boolean).join(' ');
                    fail([[408, 502, 503, 504, 522, 524].includes(xhr.status) ? words.slow : words.failed, detail].join(' '), null);
                };
                xhr.onerror = xhr.ontimeout = () => fail(words.lost, null);
                xhr.onabort = () => { working = false; overlay.hidden = true; document.body.style.overflow = ''; };
                xhr.send(body);
            }

            cancel.addEventListener('click', () => { if (xhr && working) xhr.abort(); });
            back.addEventListener('click', () => { overlay.hidden = true; document.body.style.overflow = ''; });
            window.addEventListener('beforeunload', (e) => { if (working) { e.preventDefault(); e.returnValue = ''; } });

            document.querySelectorAll('form[data-ff-upload]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    const files = [...form.querySelectorAll('input[type=file]')].flatMap((input) => [...input.files].map((file) => ({ field: input.name, file })));
                    if (!files.length || !window.XMLHttpRequest || !window.FormData || working) { if (working) event.preventDefault(); return; }
                    event.preventDefault();
                    // Let every other submit handler (the text editor saving its words, for one) finish before the form is read.
                    setTimeout(() => send(form, files), 0);
                });
            });
        })();
    </script>
@endpush
