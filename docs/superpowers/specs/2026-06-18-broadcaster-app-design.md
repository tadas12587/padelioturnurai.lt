# Transliacijos įrankis — atsisiunčiama programa (PC/Mac) — dizainas

**Data:** 2026-06-18
**Statusas:** Patvirtinta (laukia plano)

## Problema

Overlay duomenys į serverį patenka per `tools/overlay-push/push.js` (Node), nes
produkcijos serveris nepasiekia `api.tournated.com`. `push.js` paleidžiamas
**operatoriaus kompiuteryje**, bet transliuoti gali tekti iš skirtingų mašinų
(Windows ir Mac), o reikalauti įsidiegti Node.js kiekvienam nepatogu.

## Sprendimas

Supakuoti `push.js` į **savarankiškus dvejetainius failus** (su įmontuotu runtime),
kuriuos transliuotojas parsisiunčia iš admin puslapio ir paleidžia dukart spustelėjęs —
**be jokio Node.js diegimo**. Logika nesikeičia.

## Kodėl Bun `--compile`

`push.js` yra ES modulis (`"type":"module"`) — būtent čia `pkg` ir Node SEA strigdo.
**Bun `--compile`** natūraliai palaiko ESM ir **kryžmiškai kompiliuoja visus taikinius
iš vienos mašinos** (Windows PC), įdėdamas savo runtime. Transliuotojui nieko diegti
nereikia. (Alternatyva: pervesti `push.js` į CommonJS + `pkg`, bet tai regresas.)

Taikiniai:
- `overlay-push-win.exe` — `bun-windows-x64`
- `overlay-push-mac-arm` — `bun-darwin-arm64` (Apple Silicon)
- `overlay-push-mac-intel` — `bun-darwin-x64` (Intel Mac)

## Komponentai

1. **`tools/overlay-push/build.mjs`** (arba `package.json` skriptas) — paleidžia tris
   `bun build --compile` komandas, sukuria tris failus į `tools/overlay-push/dist/`.
   Naudoja tą patį `push.js` (jokių kodo šakų). `INGEST_TOKEN` ir `SITE_URL` lieka
   įmontuoti kaip numatytieji — failas savarankiškas (dukart spusteli → veikia).

2. **Filament puslapis „Transliacijos įrankis"** (`app/Filament/Pages/BroadcasterToolPage.php`
   + view) grupėje „Transliacijos":
   - Trys atsisiuntimo mygtukai (Windows / Mac Apple Silicon / Mac Intel), nukreipia į
     failus iš `storage/app/public/broadcaster/`.
   - Trumpa instrukcija pagal OS (Windows: dukart spustelėk; Mac: pirmą kartą
     dešinys-pelės → „Open", nes nepasirašyta).
   - Informacija: dabartinis `INGEST_TOKEN` ir aktyvūs turnyrai (sanity).
   - Jei failo nėra `storage`'e — rodo įspėjimą „dar neįkelta" su build/upload nuoroda.

3. **Failų talpinimas.** Dvejetainiai ~50–90 MB, todėl **necommit'inami į git**
   (`.gitignore`: `tools/overlay-push/dist/`). Build'inami lokaliai, įkeliami į serverio
   `storage/app/public/broadcaster/` per FTP/scp. Perbuild'inti reikia tik pasikeitus
   `push.js` logikai ar token'ui.

## Duomenų srautas (nesikeičia)

```
overlay-push-*  ──► fetch Tournated GraphQL (Origin: play.padel.lt)
   (transliuotojo PC/Mac)        │
                                 ▼
                    POST /overlay/ingest (X-Overlay-Token)
   GET /overlay/wanted ──► kuriuos turnyrus siųsti
```

## Klaidų valdymas / niuansai

- **macOS Gatekeeper:** nepasirašytas → „cannot be opened…". Instrukcija: dešinys →
  „Open" (vieną kartą), arba `xattr -d com.apple.quarantine <failas>`. Apple pasirašymas
  (notarization) reikalauja $99/m. paskyros — kol kas praleidžiam, dokumentuojam.
- **Token rotacija** → perbuild'inti ir įkelti iš naujo.
- Binaras išlaiko esamą `push.js` konsolės logą ir auto-reconnect ciklą.
- **Apple Silicon vs Intel** — du atskiri Mac failai (universalaus nekuriam).

## Apimties ribos (v1)

- Be kodo pasirašymo / notarizacijos.
- Binarai build'inami rankiniu būdu ir įkeliami rankiniu būdu (be CI).
- Be auto-atnaujinimo (transliuotojas parsisiunčia naują, jei keičiasi).

## Testavimas

- Build skriptas sukuria tris failus (rankinis patikrinimas, nes priklauso nuo Bun).
- Filament puslapis: rodo mygtukus kai failai yra; įspėjimą kai nėra (gali būti
  paprastas feature testas su fake storage failais).
- End-to-end: paleisti `overlay-push-win.exe`, patikrinti, kad snapshot atsiunčiamas
  (kaip dabar su `node push.js`).

## Susiję

- [Bendras overlay vadovas](../../overlays.md)
- Įgyvendinimo planas: `docs/superpowers/plans/2026-06-18-broadcaster-app.md` (toliau).
