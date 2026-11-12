@extends('layouts.app')

@section('title', 'Padelio Turnyrai - Privatumo politika')

@section('content')
<div class="pt-24 pb-16 bg-dark min-h-screen">
    <div class="max-w-3xl mx-auto px-4">
        <div class="py-12 text-center">
            <div class="text-gold text-sm tracking-[0.3em] uppercase font-semibold mb-4">Privatumas · Privacy</div>
            <h1 class="text-4xl md:text-5xl font-black text-white">Privatumo politika</h1>
            <p class="text-gray-500 mt-4">Galioja nuo 2026-11-12 · Svetainė padelioturnyrai.lt ir programėlė „Padelio turnyrai“</p>
        </div>

        <article class="bg-dark-card border border-dark-border p-8 md:p-12 space-y-8 text-gray-300 leading-relaxed">
            <section class="space-y-3">
                <h2 class="text-xl font-bold text-white">Trumpai</h2>
                <p>Programėlė ir svetainės grafiko puslapis veikia be paskyrų: neprašome vardo, el. pašto, telefono, vietos ar nuotraukų. Nenaudojame reklamos, analitikos ar sekimo įrankių ir duomenų neparduodame.</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-bold text-white">Ką saugome ir kodėl</h2>
                <p>Tik tai, ko reikia pranešimams apie mačus išsiųsti, kai juos įsijungiate:</p>
                <ul class="list-disc pl-6 space-y-1">
                    <li><strong class="text-white">Pranešimų žetonas</strong>: atsitiktinis įrenginio adresas, kurį suteikia Apple, Google ar naršyklė.</li>
                    <li><strong class="text-white">Pasirinktas turnyras ir vaidmuo</strong>: žaidėjas arba žiūrovas.</li>
                    <li><strong class="text-white">Pasirinkti žaidėjų vardai</strong>: kas esate jūs (žaidėjo režimu) arba ką sekate (žiūrovo režimu). Šie vardai jau yra viešame turnyro dalyvių sąraše.</li>
                    <li><strong class="text-white">Platforma</strong>: iOS arba Android, kad žinotume, kaip pristatyti pranešimą.</li>
                    <li><strong class="text-white">Išsiųstų pranešimų tekstai</strong>, kad tas pats pranešimas nebūtų siunčiamas du kartus.</li>
                </ul>
                <p>Teisinis pagrindas: jūsų sutikimas, kurį duodate įsijungdami pranešimus (BDAR 6 str. 1 d. a p.). Sutikimą galite atšaukti bet kada.</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-bold text-white">Kas mato duomenis</h2>
                <p>Duomenys saugomi mūsų svetainės serveryje Europos Sąjungoje (talpinimas freehosting.lt). Pranešimai pristatomi per <strong class="text-white">Expo</strong> (programėlės pranešimų paslauga), <strong class="text-white">Apple Push Notification service</strong> ir <strong class="text-white">Google Firebase Cloud Messaging</strong>, o svetainėje – per jūsų naršyklės pranešimų paslaugą. Jos gauna tik žetoną ir pranešimo tekstą. Daugiau niekam duomenų neperduodame.</p>
                <p>Turnyrų tvarkaraščius, rezultatus ir dalyvių vardus gauname iš turnyrų sistemos Tournated (play.padel.lt). Tai vieši turnyro duomenys.</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-bold text-white">Kiek laiko saugome</h2>
                <ul class="list-disc pl-6 space-y-1">
                    <li>Kai programėlėje išjungiate pranešimus arba ją ištrinate, įrenginio įrašas ištrinamas (ištrynus programėlę – kai pranešimų paslauga praneša, kad žetonas nebegalioja).</li>
                    <li>Nenaudojami įrenginių įrašai automatiškai ištrinami po 60 dienų.</li>
                    <li>Išsiųstų pranešimų tekstai ištrinami po 30 dienų.</li>
                    <li>Programėlės nustatymai (turnyras, vaidmuo, vardai) saugomi tik jūsų telefone.</li>
                </ul>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-bold text-white">Jūsų teisės</h2>
                <p>Galite prašyti susipažinti su duomenimis, juos ištaisyti ar ištrinti. Parašykite per <a href="{{ lroute('contact') }}" class="text-gold underline">kontaktų formą</a>. Jei manote, kad duomenys tvarkomi neteisėtai, galite kreiptis į Valstybinę duomenų apsaugos inspekciją (vdai.lrv.lt).</p>
                <p>Programėlė neskirta vaikams iki 13 metų, ir mes sąmoningai nerenkame jų duomenų.</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-bold text-white">Kontaktai</h2>
                <p>Duomenų valdytojas – padelio turnyrų organizatorius, padelioturnyrai.lt. Klausimams: <a href="{{ lroute('contact') }}" class="text-gold underline">padelioturnyrai.lt/kontaktai</a>.</p>
            </section>

            <hr class="border-dark-border">

            <section class="space-y-3" lang="en">
                <h2 class="text-xl font-bold text-white">English summary</h2>
                <p>The “Padelio turnyrai” app and the schedule pages on padelioturnyrai.lt work without accounts. We do not ask for your name, email, phone number, location or photos, and we use no advertising, analytics or tracking.</p>
                <p>When you turn on notifications, we store only what is needed to send match alerts: the push token issued by Apple, Google or your browser; the selected tournament and role (player or spectator); the player names you picked (yourself, or the players you follow), which are already public in the tournament entry list; the platform; and the texts of sent notifications to avoid duplicates. The legal basis is your consent, which you can withdraw at any time by turning notifications off.</p>
                <p>Data is stored on our website server in the European Union (hosted by freehosting.lt) and delivered only through Expo, Apple Push Notification service, Google Firebase Cloud Messaging or your browser’s push service. They receive only the token and the message text. Tournament schedules, results and entry lists come from the Tournated tournament system (play.padel.lt) and are public.</p>
                <p>Device records are deleted when you turn notifications off or uninstall the app, and automatically after 60 days of inactivity. Notification texts are deleted after 30 days. App settings are stored only on your phone. The app is not directed to children under 13.</p>
                <p>To access or delete your data, use the <a href="{{ lroute('contact') }}" class="text-gold underline">contact form</a>. You may also lodge a complaint with the Lithuanian State Data Protection Inspectorate (vdai.lrv.lt).</p>
            </section>
        </article>
    </div>
</div>
@endsection
