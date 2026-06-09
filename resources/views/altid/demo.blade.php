<!doctype html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Alderstjek med AltID</title>
    @stack('styles')
</head>
<body class="min-h-screen bg-[#eef6f7] px-4 py-6 font-sans text-[#0e2c4c] sm:px-6">
<main class="mx-auto grid w-[min(100%,34rem)] gap-5 rounded-lg border border-[#d7e7ea] bg-white p-5 shadow-xl shadow-[#0e2c4c]/10 sm:p-6">
    <header class="flex flex-col items-center gap-4 border-b border-[#d7e7ea] pb-5 text-center">
        <img src="{{ asset('vendor/laravel-altid/altid-logo-primary-dark-blue.svg') }}" alt="AltID" class="h-auto w-40">
        <a href="/altid" class="text-sm font-semibold text-[#1472c8] underline decoration-[#1472c8]/40 underline-offset-4 transition hover:text-[#0e2c4c]">
            Læs om AltID og alderskontrol
        </a>
    </header>

    <label class="grid max-w-xs gap-2">
        <span class="text-sm font-semibold text-[#0e2c4c]">Vælg alderskrav</span>
        <select id="claim" class="min-h-11 rounded-md border border-[#bfd4d8] bg-white px-3 text-sm font-medium text-[#0e2c4c] shadow-sm outline-none ring-[#1f82ff]/20 transition focus:border-[#1f82ff] focus:ring-4">
            @foreach (config('altid.age_claims') as $claim)
                <option value="{{ $claim }}" @selected($claim === config('altid.age_claim'))>{{ $claim }}</option>
            @endforeach
        </select>
    </label>

    <div id="actions" class="flex flex-wrap gap-2">
        <button id="start" type="button" class="inline-flex min-h-11 items-center justify-center rounded-md bg-[#0e2c4c] px-4 text-sm font-bold text-white shadow-sm transition hover:bg-[#163e68] disabled:cursor-wait disabled:bg-[#91a8b9]">
            Start alderstjek
        </button>
        <a id="open" class="hidden min-h-11 items-center justify-center rounded-md border border-[#0e2c4c] bg-white px-4 text-sm font-bold text-[#0e2c4c] shadow-sm transition hover:bg-[#f6fbfb]" href="#">
            Åbn i AltID
        </a>
    </div>

    <section id="result" class="hidden min-w-0 grid-cols-1 gap-4 rounded-lg border border-[#d7e7ea] bg-[#fbfdfd] p-4" aria-live="polite">
        <div id="status" class="rounded-md bg-[#eaf4ff] px-3 py-2 text-sm font-bold text-[#0e2c4c]">Klar til at starte</div>

        <div id="qrPanel" class="grid min-h-72 place-items-center gap-3 rounded-md border border-[#d7e7ea] bg-white p-4">
            <img id="qr" class="h-auto w-full max-w-56" alt="QR-kode til AltID aldersverifikation">
            <p class="m-0 max-w-sm text-center text-sm font-medium text-[#456177]">Scan QR-koden eller åbn AltID direkte på denne enhed.</p>
        </div>

        <div id="verifiedPanel" class="hidden gap-4 rounded-lg border border-[#b9efce] bg-[#ecfff4] p-4">
            <div class="flex items-center gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#009c3e] text-xl font-black text-white">✓</div>
                <div class="min-w-0">
                    <div class="text-xl font-bold leading-tight text-[#0e2c4c]">Alderen er verificeret</div>
                    <div id="verifiedSubtitle" class="break-all text-sm text-[#17693b]">AltID proof er godkendt</div>
                </div>
            </div>

            <div class="grid gap-2">
                <div class="grid gap-1 rounded-md border border-[#ccefd9] bg-white p-3 sm:grid-cols-[140px_minmax(0,1fr)] sm:items-start">
                    <span class="text-xs font-bold uppercase text-[#17693b]">Claim</span>
                    <strong id="verifiedClaim" class="break-words text-sm text-[#0e2c4c]">-</strong>
                </div>
                <div class="grid gap-1 rounded-md border border-[#ccefd9] bg-white p-3 sm:grid-cols-[140px_minmax(0,1fr)] sm:items-start">
                    <span class="text-xs font-bold uppercase text-[#17693b]">Resultat</span>
                    <strong id="verifiedValue" class="break-words text-sm text-[#0e2c4c]">-</strong>
                </div>
                <div class="grid gap-1 rounded-md border border-[#ccefd9] bg-white p-3 sm:grid-cols-[140px_minmax(0,1fr)] sm:items-start">
                    <span class="text-xs font-bold uppercase text-[#17693b]">Validering</span>
                    <strong id="verifiedValidation" class="break-words text-sm text-[#0e2c4c]">-</strong>
                </div>
                <div class="grid gap-1 rounded-md border border-[#ccefd9] bg-white p-3 sm:grid-cols-[140px_minmax(0,1fr)] sm:items-start">
                    <span class="text-xs font-bold uppercase text-[#17693b]">Issuer</span>
                    <strong id="verifiedIssuer" class="break-words text-sm text-[#0e2c4c]">-</strong>
                </div>
            </div>

            <div id="verifiedChecks" class="grid gap-2"></div>
        </div>

        @if (config('altid.debug'))
            <details id="debugPanel" class="min-w-0 rounded-md border border-slate-200 bg-slate-50 p-3" open>
                <summary class="cursor-pointer text-sm font-bold text-slate-700">Debug</summary>
                <div class="mt-3 grid min-w-0 gap-3">
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-600">Transaction</p>
                        <pre id="transaction" class="max-h-56 max-w-full overflow-auto rounded-md bg-slate-950 p-3 text-xs whitespace-pre-wrap break-all text-slate-100">-</pre>
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-600">Callback</p>
                        <pre id="callback" class="max-h-56 max-w-full overflow-auto rounded-md bg-slate-950 p-3 text-xs whitespace-pre-wrap break-all text-slate-100">-</pre>
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-600">Authorization URL</p>
                        <pre id="authorization" class="max-h-40 max-w-full overflow-auto rounded-md bg-slate-950 p-3 text-xs whitespace-pre-wrap break-all text-slate-100">-</pre>
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-600">Vores test-app URL</p>
                        <pre id="testAppUrl" class="max-h-40 max-w-full overflow-auto rounded-md bg-slate-950 p-3 text-xs whitespace-pre-wrap break-all text-slate-100">-</pre>
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-600">Authorization Request</p>
                        <pre id="authorizationRequest" class="max-h-56 max-w-full overflow-auto rounded-md bg-slate-950 p-3 text-xs whitespace-pre-wrap break-all text-slate-100">-</pre>
                    </div>
                </div>
            </details>
        @endif
    </section>
</main>

<script>
    const debugEnabled = @json(config('altid.debug'));
    const startButton = document.getElementById('start');
    const claimSelect = document.getElementById('claim');
    const openLink = document.getElementById('open');
    const resultPanel = document.getElementById('result');
    const qrPanel = document.getElementById('qrPanel');
    const qrImage = document.getElementById('qr');
    const verifiedPanel = document.getElementById('verifiedPanel');
    const verifiedSubtitle = document.getElementById('verifiedSubtitle');
    const verifiedClaim = document.getElementById('verifiedClaim');
    const verifiedValue = document.getElementById('verifiedValue');
    const verifiedValidation = document.getElementById('verifiedValidation');
    const verifiedIssuer = document.getElementById('verifiedIssuer');
    const verifiedChecks = document.getElementById('verifiedChecks');
    const statusBox = document.getElementById('status');
    const transactionBox = document.getElementById('transaction');
    const callbackBox = document.getElementById('callback');
    const authorizationBox = document.getElementById('authorization');
    const testAppUrlBox = document.getElementById('testAppUrl');
    const authorizationRequestBox = document.getElementById('authorizationRequest');
    let pollTimer = null;

    const statusClasses = {
        pending: 'rounded-md bg-[#eaf4ff] px-3 py-2 text-sm font-bold text-[#0e2c4c]',
        approved: 'rounded-md bg-[#ecfff4] px-3 py-2 text-sm font-bold text-[#17693b]',
        failed: 'rounded-md bg-[#fff1f1] px-3 py-2 text-sm font-bold text-[#9b1c1c]',
        expired: 'rounded-md bg-[#fff1f1] px-3 py-2 text-sm font-bold text-[#9b1c1c]',
    };

    function setDebugText(element, value) {
        if (!debugEnabled || !element) {
            return;
        }

        element.textContent = value;
    }

    function setStatus(text, state = 'pending') {
        statusBox.textContent = text;
        statusBox.className = statusClasses[state] || statusClasses.pending;
    }

    function resetVerificationView() {
        startButton.textContent = 'Start alderstjek';
        qrPanel.classList.remove('hidden');
        qrPanel.classList.add('grid');
        verifiedPanel.classList.add('hidden');
        verifiedPanel.classList.remove('grid');
        verifiedChecks.innerHTML = '';
        openLink.classList.add('hidden');
        openLink.classList.remove('inline-flex');
    }

    function formatBoolean(value) {
        if (value === true) {
            return 'Ja';
        }

        if (value === false) {
            return 'Nej';
        }

        return '-';
    }

    function addCheck(label, passed) {
        const item = document.createElement('div');
        item.className = passed
            ? 'flex min-h-10 items-center gap-2 rounded-md border border-[#ccefd9] bg-white px-3 py-2 text-sm font-bold text-[#0e2c4c] before:flex before:size-5 before:shrink-0 before:items-center before:justify-center before:rounded-full before:bg-[#009c3e] before:text-xs before:text-white before:content-["✓"]'
            : 'flex min-h-10 items-center gap-2 rounded-md border border-[#d7e7ea] bg-white px-3 py-2 text-sm font-bold text-[#61788a] before:flex before:size-5 before:shrink-0 before:items-center before:justify-center before:rounded-full before:bg-[#91a8b9] before:text-xs before:text-white before:content-["-"]';
        item.textContent = label;
        verifiedChecks.appendChild(item);
    }

    function showVerified(payload) {
        const result = payload.result || {};
        const validation = payload.validation || payload.callback?.validation || {};
        const details = validation.details || {};

        startButton.textContent = 'Start nyt alderstjek';
        openLink.classList.add('hidden');
        openLink.classList.remove('inline-flex');
        qrPanel.classList.add('hidden');
        qrPanel.classList.remove('grid');
        verifiedPanel.classList.remove('hidden');
        verifiedPanel.classList.add('grid');

        verifiedSubtitle.textContent = payload.transaction_id;
        verifiedClaim.textContent = result.claim || payload.claim || '-';
        verifiedValue.textContent = formatBoolean(result.value);
        verifiedValidation.textContent = result.validation || '-';
        verifiedIssuer.textContent = details.issuer_certificate_subject || '-';
        verifiedChecks.innerHTML = '';

        addCheck('Issuer signatur', details.issuer_signature_verified === true);
        addCheck('Claim digest', details.claim_digest_verified === true);
        addCheck('Certifikat trust', details.issuer_certificate_chain_trusted === true);
        addCheck('MSO gyldighed', details.mso_validity_verified === true);
        addCheck('Doctype', details.mso_doctype_verified === true);
        addCheck(
            details.device_binding_required === false ? 'Device binding ikke krævet' : 'Device binding',
            details.device_binding_required === false || details.device_binding_verified === true
        );
    }

    function renderPayload(payload) {
        setStatus(`Status: ${payload.status}`, payload.status);
        setDebugText(transactionBox, JSON.stringify(payload, null, 2));
        setDebugText(callbackBox, JSON.stringify(payload.callback || null, null, 2));

        if (payload.status === 'approved' && payload.verified) {
            showVerified(payload);
        }
    }

    async function poll(statusUrl) {
        const response = await fetch(statusUrl, {headers: {'Accept': 'application/json'}});
        const payload = await response.json();
        renderPayload(payload);

        if (!['pending'].includes(payload.status)) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    startButton.addEventListener('click', async () => {
        clearInterval(pollTimer);
        pollTimer = null;
        startButton.disabled = true;
        resetVerificationView();
        setStatus('Starter alderstjek...');

        try {
            const claim = claimSelect.value;
            const response = await fetch('/api/altid/age/start', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({claim}),
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'AltID start fejlede');
            }

            resultPanel.classList.remove('hidden');
            resultPanel.classList.add('grid');
            qrImage.src = payload.qr_code;
            openLink.href = payload.authorization_url;
            openLink.classList.remove('hidden');
            openLink.classList.add('inline-flex');
            setDebugText(authorizationBox, payload.authorization_url);
            setDebugText(testAppUrlBox, payload.test_app_url);
            setDebugText(authorizationRequestBox, JSON.stringify(payload.authorization_request, null, 2));
            renderPayload(payload);

            pollTimer = setInterval(() => poll(payload.status_url), 2000);
            await poll(payload.status_url);
        } catch (error) {
            resultPanel.classList.remove('hidden');
            resultPanel.classList.add('grid');
            setStatus(error.message, 'failed');
        } finally {
            startButton.disabled = false;
        }
    });
</script>
</body>
</html>
