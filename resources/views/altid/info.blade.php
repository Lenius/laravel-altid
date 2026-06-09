<!doctype html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AltID i Danmark</title>
    <meta name="description" content="Læs kort om AltID, Danmarks officielle app til ID og digitale beviser, og hvordan aldersbevis kan bruges til alderskontrol ved takeaway og onlinebestilling.">
    @stack('styles')
</head>
<body class="min-h-screen bg-[#eef6f7] font-sans text-[#0e2c4c]">
<main class="mx-auto flex min-h-screen w-full max-w-5xl flex-col px-4 py-6 sm:px-6 lg:px-8">
    <section class="grid gap-8 rounded-xl border border-[#d7e7ea] bg-white p-5 shadow-xl shadow-[#0e2c4c]/10 sm:p-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
        <div class="grid gap-6">
            <img src="{{ asset('vendor/laravel-altid/altid-logo-primary-dark-blue.svg') }}" alt="AltID" class="h-auto w-40">

            <div class="grid gap-4">
                <p class="text-sm font-bold uppercase tracking-wide text-[#1472c8]">Alderskontrol til takeaway og onlinebestilling</p>
                <h1 class="max-w-3xl text-4xl font-bold leading-tight text-[#0e2c4c] sm:text-5xl">AltID gør det lettere at købe varer med alderskrav</h1>
                <p class="max-w-2xl text-lg leading-8 text-[#3f5d76]">
                    AltID er Digitaliseringsstyrelsens app til digitale beviser. I vores takeaway-løsning kan aldersbeviset bruges, når en ordre indeholder varer som kræver alderskontrol, uden at kunden behøver dele CPR-nummer, præcis alder eller andre unødvendige oplysninger.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="/alderstjek" class="inline-flex min-h-12 items-center justify-center rounded-md bg-[#0e2c4c] px-5 text-sm font-bold text-white shadow-sm transition hover:bg-[#163e68]">
                    Prøv alderstjek
                </a>
                <a href="https://digst.dk/it-loesninger/altid/" target="_blank" rel="noopener" class="inline-flex min-h-12 items-center justify-center rounded-md border border-[#bfd4d8] bg-white px-5 text-sm font-bold text-[#0e2c4c] shadow-sm transition hover:bg-[#f6fbfb]">
                    Læs hos Digitaliseringsstyrelsen
                </a>
            </div>
        </div>

        <div class="grid gap-3 rounded-lg border border-[#d7e7ea] bg-[#f6fbfb] p-4">
            <div class="rounded-md bg-white p-4 shadow-sm">
                <div class="text-sm font-bold uppercase text-[#1472c8]">Aldersbevis</div>
                <div class="mt-2 text-3xl font-bold text-[#0e2c4c]">Over 16 / 18 / 21</div>
                <p class="mt-3 text-sm leading-6 text-[#3f5d76]">
                    Kunden deler kun svaret på det alderskrav, der passer til varen. Det kan for eksempel være relevant ved alkohol, tobak eller andre aldersbegrænsede produkter.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-md bg-white p-4 shadow-sm">
                    <div class="text-2xl font-bold text-[#0e2c4c]">03.06.2026</div>
                    <p class="mt-2 text-sm text-[#3f5d76]">AltID blev tilgængelig i Danmark.</p>
                </div>
                <div class="rounded-md bg-white p-4 shadow-sm">
                    <div class="text-2xl font-bold text-[#0e2c4c]">13+</div>
                    <p class="mt-2 text-sm text-[#3f5d76]">Appen er til personer med MitID og CPR-nr.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-5 py-8 lg:grid-cols-3">
        <article class="rounded-lg border border-[#d7e7ea] bg-white p-5 shadow-sm">
            <h2 class="text-xl font-bold text-[#0e2c4c]">Hvad er AltID?</h2>
            <p class="mt-3 leading-7 text-[#3f5d76]">
                AltID er en digital identitetstegnebog, hvor borgeren kan have officielle digitale beviser på telefonen. Ved lanceringen er fokus på aldersbevis og legitimationskort.
            </p>
        </article>

        <article class="rounded-lg border border-[#d7e7ea] bg-white p-5 shadow-sm">
            <h2 class="text-xl font-bold text-[#0e2c4c]">Hvorfor i takeaway?</h2>
            <p class="mt-3 leading-7 text-[#3f5d76]">
                En takeaway kan have produkter, hvor kunden skal være over en bestemt alder. Med AltID kan kontrollen ske i bestillingsflowet, før ordren betales, pakkes eller udleveres.
            </p>
        </article>

        <article class="rounded-lg border border-[#d7e7ea] bg-white p-5 shadow-sm">
            <h2 class="text-xl font-bold text-[#0e2c4c]">Hvad deler brugeren?</h2>
            <p class="mt-3 leading-7 text-[#3f5d76]">
                Ved alderskontrol kan brugeren godkende, at tjenesten får bekræftet et konkret aldersclaim, for eksempel at personen er over 18 år.
            </p>
        </article>
    </section>

    <section class="grid gap-6 rounded-xl border border-[#d7e7ea] bg-white p-5 shadow-sm sm:p-8 lg:grid-cols-[0.85fr_1.15fr]">
        <div>
            <h2 class="text-3xl font-bold leading-tight text-[#0e2c4c]">Sådan passer det ind i vores takeaway</h2>
            <p class="mt-4 leading-7 text-[#3f5d76]">
                Når kunden lægger en aldersbegrænset vare i kurven, kan shoppen bede om et AltID-tjek. Kunden godkender i appen, og ordren kan fortsætte, når alderskravet er bekræftet.
            </p>
            <p class="mt-4 leading-7 text-[#3f5d76]">
                Det gør oplevelsen mere naturlig for både afhentning og levering: alderskontrollen kan dokumenteres digitalt, og personalet slipper for at håndtere flere personlige oplysninger end nødvendigt.
            </p>
        </div>

        <div class="grid gap-3">
            <div class="grid gap-2 rounded-md border border-[#d7e7ea] bg-[#f6fbfb] p-4 sm:grid-cols-[2.5rem_1fr]">
                <div class="flex size-10 items-center justify-center rounded-full bg-[#0e2c4c] text-sm font-bold text-white">1</div>
                <div>
                    <h3 class="font-bold text-[#0e2c4c]">Produktet bestemmer alderskravet</h3>
                    <p class="mt-1 text-sm leading-6 text-[#3f5d76]">Et produkt i shoppen kan markeres med en alder, for eksempel 16 eller 18 år.</p>
                </div>
            </div>
            <div class="grid gap-2 rounded-md border border-[#d7e7ea] bg-[#f6fbfb] p-4 sm:grid-cols-[2.5rem_1fr]">
                <div class="flex size-10 items-center justify-center rounded-full bg-[#0e2c4c] text-sm font-bold text-white">2</div>
                <div>
                    <h3 class="font-bold text-[#0e2c4c]">Kunden bekræfter med AltID</h3>
                    <p class="mt-1 text-sm leading-6 text-[#3f5d76]">Kunden scanner en QR-kode eller åbner AltID direkte og godkender kun det nødvendige aldersclaim.</p>
                </div>
            </div>
            <div class="grid gap-2 rounded-md border border-[#d7e7ea] bg-[#f6fbfb] p-4 sm:grid-cols-[2.5rem_1fr]">
                <div class="flex size-10 items-center justify-center rounded-full bg-[#0e2c4c] text-sm font-bold text-white">3</div>
                <div>
                    <h3 class="font-bold text-[#0e2c4c]">Ordren kan fortsætte</h3>
                    <p class="mt-1 text-sm leading-6 text-[#3f5d76]">Når svaret er godkendt, kan shoppen gemme dokumentation og lade kunden betale eller færdiggøre bestillingen.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-8 rounded-xl border border-[#d7e7ea] bg-[#0e2c4c] p-5 text-white shadow-sm sm:p-8">
        <div class="grid gap-6 lg:grid-cols-[1fr_1fr] lg:items-center">
            <div>
                <h2 class="text-3xl font-bold leading-tight">Mindre friktion ved afhentning og levering</h2>
                <p class="mt-4 leading-7 text-white/80">
                    AltID kan hjælpe med at flytte alderskontrollen tidligere i bestillingen. Det betyder, at kunden kan klare verificeringen hjemmefra, og at butikken får et tydeligt svar på, om alderskravet er opfyldt.
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg bg-white/10 p-4">
                    <div class="text-sm font-bold uppercase text-white/70">For kunden</div>
                    <p class="mt-2 text-sm leading-6 text-white/85">Ingen unødvendig deling af CPR, adresse eller præcis alder i checkout.</p>
                </div>
                <div class="rounded-lg bg-white/10 p-4">
                    <div class="text-sm font-bold uppercase text-white/70">For takeawayen</div>
                    <p class="mt-2 text-sm leading-6 text-white/85">Et digitalt spor af aldersclaim, resultat, tidspunkt og validering ved ordren.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 py-8">
        <h2 class="text-2xl font-bold text-[#0e2c4c]">Kilder</h2>
        <div class="flex flex-col gap-2 text-sm font-medium text-[#3f5d76]">
            <a class="underline decoration-[#1472c8]/40 underline-offset-4 hover:text-[#0e2c4c]" href="https://digst.dk/it-loesninger/altid/" target="_blank" rel="noopener">Digitaliseringsstyrelsen: AltID</a>
            <a class="underline decoration-[#1472c8]/40 underline-offset-4 hover:text-[#0e2c4c]" href="https://digst.dk/nyheder/nyhedsarkiv/2026/juni/nu-kan-du-downloade-altid" target="_blank" rel="noopener">Digitaliseringsstyrelsen: Nu kan du downloade AltID</a>
            <a class="underline decoration-[#1472c8]/40 underline-offset-4 hover:text-[#0e2c4c]" href="https://digst.dk/it-loesninger/den-digitale-identitetstegnebog/" target="_blank" rel="noopener">Digitaliseringsstyrelsen: Den digitale identitetstegnebog</a>
        </div>
    </section>
</main>
</body>
</html>
