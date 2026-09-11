# Head to Head (Akistata) overlay — dizainas

**Data:** 2026-06-20
**Statusas:** Patvirtinta kryptis (laukia spec peržiūros)

## Tikslas

Naujas overlay šablonas **„Akistata" (`type:'h2h'`)**: dvi komandos viena prieš kitą su
žaidėjų nuotraukomis (apkarpyto žmogaus GIF), centre — VS + rungtynių laikas / live
rezultatas / kortas·etapas / laisvas tekstas. Lėta animacija link žiūrovo.

Padel = dvejetai, tad **beveik visada po 2 žmones** komandoje.

## Komponentai

### 1. Žaidėjų nuotraukų biblioteka (`Žaidėjų nuotraukos` puslapis)

- Naujas Filament puslapis grupėje „Transliacijos".
- Iš snapshot (`participants_by_category`) išvardija visus dalyvius (žmones) pagal turnyrą.
- Prie kiekvieno žmogaus:
  - **Nuotrauka** — įkeliamas **GIF/PNG** (apkarpytas žmogus, skaidrus fonas).
  - **Lytis** (V/M) — automatiškai užpildoma iš kategorijos pavadinimo („Vyrai"→V,
    „Moterys"→M), redaguojama (svarbu mišriems dvejetams). Naudojama stock parinkimui.
- Saugoma `player_photos` lentelėje: `tournament_external_id`, `person_key`, `name`,
  `gender`, `photo`. Unikalu `(tournament_external_id, person_key)`.
- **`person_key`** — Tournated user **ID** (stabilus), fallback normalizuotas vardas.
  Todėl `push.js` papildomas: dalyvių IR matches užklausose įtraukiamas user `id`.
- **Stock nuotraukos** — įmontuoti vyriškas/moteriškas silueto/cut-out paveiksliukai
  (naudojami, kai žmogus neturi nuotraukos). Galimybė įkelti savo 2 stock (vyr/mot).

### 2. H2H langas (`type:'h2h'`) + „Akistata" valdymas

- **Lango nustatymai (admine):** kuriuos centro elementus rodyti (laikas / live
  rezultatas / kortas·etapas / VS-tekstas), laisvas tekstas, animacijos jungiklis,
  komandų plokštelių rodymas (vardai/spalvos).
- **Valdymas:** „Akistata" puslapis (arba simplified control) — pasirenki rungtynes iš
  turnyro fixtures (kas prieš ką) → nustato `state['h2h_match_id']`; Play/Stop (kaip kiti langai).
- **Centras automatiškai:** prieš rungtynes — laikas; vykstant (`in_progress`) — live
  rezultatas; visada galima kortas/etapas ir/ar laisvas tekstas/VS.

### 3. Duomenys + renderis

- **`/data` h2h šaka:** pagal `state['h2h_match_id']` randa rungtynes snapshot'e →
  `team1` (2 žaidėjai + nuotraukos), `team2`, `center` {time, date, score, court, round,
  in_progress}, config.
- **Nuotraukos rišimas:** kiekvienas rungtynių žaidėjas (pagal user id, fallback vardą) →
  `player_photos`; jei nėra — stock pagal lytį.
- **Renderis (`window.blade`):** dvi pusės viena prieš kitą; po 2 žaidėjus pusėje
  (priekinis didesnis, partneris truputį už ir mažesnis); vardai po nuotraukomis;
  komandų plokštelės viršuje; centrinis stulpelis VS + turinys. **Lėtas zoom/dreifas
  link žiūrovo (~20 s, subtilus)**; GIF animuojasi savaime. Renderiama į `<body>` host'ą
  (kaip kiti fixed langai).

## Architektūra (santrauka)

```
push.js (+user id) → snapshot.participants_by_category / matches
   admin „Žaidėjų nuotraukos" → player_photos (photo + gender per person)
   admin „Akistata" valdymas → state['h2h_match_id'] + Play
OBS → /overlay/{token}/data (h2h) → resolveH2h → team1/team2 (+photos/stock) + center
   → window.blade h2h render (facing teams, center, slow zoom)
```

## Klaidų valdymas / niuansai

- Žaidėjas be nuotraukos → stock pagal lytį.
- Nėra pasirinktų rungtynių → langas tuščias / „Pasirink rungtynes".
- Vardų sutapimai → rišimas pagal user id sumažina riziką; kraštutiniu atveju rankinis patikslinimas.
- 1 žmogaus komanda → rodoma viena nuotrauka toje pusėje.

## Apimties ribos (v1)

- Be realaus laiko push (polling pakanka).
- Be pilnos statistikos (kaip pavyzdyje su pasais/įvarčiais) — tik nuotraukos + centras.
- Stock = du paveiksliukai (vyr/mot); be automatinio veido apkarpymo.

## Failai

- `tools/overlay-push/push.js` — user `id` dalyviuose ir matches.
- Migracija + `app/Models/PlayerPhoto.php`.
- `app/Filament/Pages/PlayerPhotosPage.php` + view.
- `app/Filament/Resources/OverlayResource.php` — h2h lango laukai.
- „Akistata" valdymas (Filament page) — fixtures pasirinkimas.
- `OverlayController` h2h šaka + `OverlayData::resolveH2h` + participants/person helperiai.
- `resources/views/overlays/window.blade.php` — h2h renderis + CSS.
- Stock paveiksliukai (vyr/mot) — `resources`/`public` asset'ai.
- Testai: `tests/Unit` (resolveH2h matching), `tests/Feature` (control, photos).

## Testavimas

- `resolveH2h`: pagal `match_id` grąžina abi komandas su nuotraukomis (arba stock pagal
  lytį) ir centro duomenis.
- Photos: įkėlimas saugomas `player_photos`; nėra nuotraukos → stock fallback.
- Control: pasirinkus rungtynes nustatomas `state['h2h_match_id']`; play/stop.

## Susiję

- [Bendras overlay vadovas](../../overlays.md)
- Įgyvendinimo planas: `docs/superpowers/plans/2026-06-20-head-to-head-overlay.md` (toliau).
