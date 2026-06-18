# Transliacijos overlay'ai — kaip viskas veikia

Pilnas OBS overlay'ų sistemos aprašymas: architektūra, duomenų srautas, langų
tipai, push scenarijus, administravimas, diegimas ir problemų sprendimas.

> Trumpai: Filament admin'e kuri „langus" (scenas), juos įjungi/išjungi valdymo
> puslapyje, o OBS Browser Source rodo permatomą puslapį, kuris kas 3 s
> klausia serverio, ką rodyti. Gyvus turnyro duomenis į serverį **pučia** Node
> scenarijus, sukamas tavo kompiuteryje.

---

## 1. Architektūra ir kodėl push

Produkcinis serveris (freehosting.lt) **negali** pasiekti Tournated API
(`api.tournated.com`) — išeinantis ryšys blokuojamas. Todėl duomenys ne
traukiami serveryje, o **pučiami** iš tavo PC:

```
Tournated API  ──(GraphQL)──>  push.js (tavo PC)  ──(POST /overlay/ingest)──>  serveris (snapshot DB)
                                                                                      │
OBS Browser Source  ──(GET /overlay/{token}/data kas 3 s)──────────────────────────┘
```

- **push.js** (tavo PC) kas ~20 s nuskaito turnyro duomenis ir nusiunčia į serverį.
- Serveris išsaugo „snapshot" (vienas įrašas vienam turnyrui).
- OBS puslapis kas 3 s klausia serverio, ką rodyti aktyviame lange, ir piešia.

Visa logika (filtravimas, rikiavimas, grupavimas) yra **serveryje** — push tik
atneša žalius duomenis, renderer'is tik piešia.

---

## 2. Komponentai (failai)

| Failas | Atsakomybė |
|---|---|
| `tools/overlay-push/push.js` | Tavo PC scenarijus: skaito Tournated API, normalizuoja, pučia į serverį |
| `app/Http/Controllers/OverlayController.php` | Maršrutai: `show`, `data`, `ingest`, `wanted` |
| `app/Services/OverlayData.php` | Snapshot skaitymas + visa rezoliucijos logika (grupės, bracketai, tvarkaraštis) |
| `app/Filament/Resources/OverlayResource.php` | Admin forma: langai ir jų nustatymai |
| `app/Filament/Resources/OverlayResource/Pages/ListOverlays.php` | Sąrašo puslapis + „Kaip paleisti push" modalas |
| `app/Models/Overlay.php` | Overlay modelis (token, config, windows, state, temos) |
| `app/Models/OverlaySnapshot.php` | Snapshot modelis (vienas per turnyrą) |
| `resources/views/overlays/base.blade.php` | Bazinis OBS puslapis: poll'inimas, pozicija, intro animacija, temos |
| `resources/views/overlays/window.blade.php` | Visų langų tipų CSS + piešimo (render) logika |
| `tests/Feature/OverlayEndpointTest.php` | Feature testai (data endpoint, visi langų tipai) |

---

## 3. Snapshot forma

Vienas snapshot vienam turnyrui (`overlay_snapshots.tournament_external_id`).
`payload` (JSON):

```json
{
  "title": "Lietuvos Senjorų ... 2026",
  "categories": [ { "id": 53642, "category": { "id": 1, "name": "Vyrai 40+" }, "mde": 16 } ],
  "groups_by_category": {
    "53642": [ { "id": 5, "name": "A", "segment": "MD", "entries": [...], "matches": [...] } ]
  },
  "category_stages": {
    "53642": { "has_groups": true, "has_bracket": true, "draw_type": "play-each-place", "draw_size": 16 }
  },
  "brackets_by_category": {
    "53642": { "segments": [ { "key": "..-main", "label": "Pagrindinis", "is_main": true,
                              "rounds": [...], "third": {...}, "placements": [] }, ... ] }
  },
  "matches": [
    { "id": 1148450, "date": "2026-04-18", "time": "12:00", "duration": 60,
      "court": "Court 7", "court_id": 49934, "category_id": 53642, "category": "Vyrai 40+",
      "status": "completed", "in_progress": false, "finished_at": "2026-04-18T11:50:00Z",
      "round": "R1", "segment": "main", "score": "6:3 2:6 [10:8]",
      "team1": ["Paulius Lavrukaitis", "Neividas Biriukovas"],
      "team2": ["Marius Linartas", "Tomas Vitkus"], "winner": 1 }
  ]
}
```

Forma laisva (JSON) — schemos migracijų nereikia. Seni snapshot'ai be `matches`
ar su senu bracket formatu vis tiek veikia (serveris juos sutvarko).

---

## 4. Langų tipai

Overlay turi vieną ar kelis **langus** (scenas). Kiekvienas turi `type`, o per
valdymo puslapį įjungiamas tik vienas aktyvus (`state.active_window_id`).

### 4.1 Grupės (`groups`)
Grupių lentelės (round-robin standings). Lange — vienas ar keli **pogrupiai**,
kiekvienam pasirenki:
- **Kategorija** — tik tos, kurios turi grupes.
- **Segmentai** (multi) — Main / 5-8 / 9-16 ir pan. Tuščia = visi. Rodoma
  segmento etiketė ant lentelės.
- **Pogrupis** — konkretus (arba „Visi"). Sąrašas susiaurėja pagal segmentus.

Standings skaičiuojami iš `entries` + `matches` (laimėjimai, sužaista,
pralaimėta — tik kai visos grupės rungtynės baigtos).

### 4.2 Brackets (`bracket`)
Pilno ekrano turnyro tinklelis, automatiškai sumažinamas, kad tilptų
(`transform: scale`). Lange:
- **Kategorija (bracketas)** — tik tos, kurios turi tinklelį.
- **Segmentai** (multi) — atskiri draw'ai / tinklelio dalys. Tuščia = visi.

**Segmentų logika.** Kategorija gali turėti:
- vieną „play-each-place" draw'ą — jis **išskaidomas** į „Pagrindinis" +
  placement segmentus (5-8, 9-16, 13-16);
- kelis atskirus draw'us (pvz. „dėl 1/3/5 vietos") — kiekvienas = segmentas.

Pozicijų intervalas (5-8 ir pan.) apskaičiuojamas iš struktūros: `start =`
paskutinio „Nth place" raundo skaičius − 2, `end = start + 2×(pirmo raundo
rungtynių) − 1`. Placement segmente laimėtojų finalas ir „Nth place" finalas
sujungiami į vieną stulpelį, kiekvienos finalinės rungtynės pažymimos „Dėl N
vietos". „Dėl 3 vietos" rungtynė pakišama po pagrindiniu finalu.

### 4.3 Rėmėjai (`sponsors`)
Rotuojantys rėmėjų logo. Variantai: `corner` (kampe), `bar` (apačios juosta),
`fullscreen`. Logo imami iš rėmėjų sąrašo arba įkeliami masiškai. `rotate_seconds`
valdo keitimo intervalą.

### 4.4 Tvarkaraštis / Order of Play (`schedule`)
Vienas tipas, penki **variantai** (`schedule_variant`). Bendri filtrai:
- **Data** — kurios dienos rungtynės (tuščia = visos dienos).
- **Kategorijos** (multi) — tuščia = visos.
- **Kortai** (multi) — tuščia = visi.
- **Kiek rodyti** (`limit`, def. 6) — taikoma „Dabar / Toliau / Rezultatai".

Variantai:
| Variantas | Ką rodo | Logika |
|---|---|---|
| **Pagal kortą** (`by_court`) | Stulpeliai = kortai, juose rungtynės pagal laiką | grupuoja pagal kortą, rikiuoja pagal laiką |
| **Pagal laiką** (`by_time`) | Sekcijos = laiko langai, juose kortas + poros | grupuoja pagal laiką |
| **Dabar žaidžiama** (`now`) | Vykstančios rungtynės | `in_progress = true` |
| **Toliau aikštelėje** (`next`) | Vykstanti (viršuje) + būsimos | `in_progress`, tada `status = pending`, pagal laiką |
| **Rezultatų juosta** (`results`) | Ką tik pasibaigusios su rezultatu | turi `score` ir nevyksta; naujausios viršuje |

**Rezultatų rikiavimas (su atsarga):** jei rungtynė turi pabaigos žymą
(`finished_at` = Tournated `firstScoreSubmittedAt`) — pagal ją; jei ne — pagal
suplanuotą datą+laiką. Visada naujausios viršuje, tada `limit`.

**Rezultatų juosta — ypatumas:** ji visada piešiama per **visą ekrano apačią**
(be turnyro pavadinimo/logo, su dideliu užrašu „REZULTATAI", paryškinant
nugalėtoją ir rezultatą). Bendri pozicijos nustatymai jai negalioja. Techniškai
juosta įdedama tiesiai į `<body>` (už pozicionuojamų konteinerių), kad
`position: fixed; bottom: 0` priliptų prie tikro ekrano apačios.

### 4.5 Traukimas / Burtai (`draw`)

Gyva burtų ceremonija: operatorius dėlioja komandas (rankiniu būdu arba atsitiktiniu
„TRAUKTI") į grupių lenteles arba sėkluotą bracketą. **Esminis skirtumas nuo kitų
langų** — lentos turinį (kas kur padėta) **kuria operatorius gyvai serveryje**;
snapshot'as naudojamas tik dalyvių sąrašui užkrauti. Žemiau — pilnas „kaip veikia",
kad būtų lengva koreguoti.

#### Failai (kur kas yra)

| Failas | Atsakomybė |
|---|---|
| `app/Services/DrawEngine.php` | Gryna logika: vietų išdėstymas, sėklų tvarka, krepšeliai, `drawNext` / `place` / `undo` / `reset`. Be DB/HTTP, pilnai unit-testuota. |
| `app/Filament/Pages/DrawControlPage.php` + `resources/views/filament/pages/draw-control.blade.php` (+ `partials/draw-slot.blade.php`) | Operatoriaus konsolė (Livewire). |
| `app/Filament/Resources/OverlayResource.php` | `type:'draw'` lango nustatymų laukai. |
| `app/Http/Controllers/OverlayController.php` (`data()` draw šaka) | Sudeda payload'ą + kategorijos pavadinimą. |
| `app/Services/OverlayData.php` (`resolveDraw`, `participants`) | Payload'o surinkimas; dalyvių skaitymas iš snapshot. |
| `resources/views/overlays/window.blade.php` (draw šaka + CSS) | Overlay atvaizdavimas + animacijos. |
| `resources/views/overlays/base.blade.php` | Polling intervalas, change-signature, valymas. |
| `tools/overlay-push/push.js` (`fetchParticipants`) | Dalyvių traukimas iš Tournated. |
| `tests/Unit/DrawEngineTest.php`, `tests/Feature/DrawControlTest.php` | Testai. |

#### Būsenos forma — `overlay.state['draws'][<windowId>]`

```
{ teams:[{id,name,seed,pot}],         // užšaldytas pool (kopija, ne snapshot)
  slots:{ '<key>': teamId | 'BYE' | null },
  current:{team_id,slot} | null,      // paskutinis padėjimas (varo animaciją)
  history:[{team_id,slot}],           // Undo
  active_pot:int, status:'idle'|'done' }
```
Vietų raktai: grupės — `A1..A{n}`, `B1..`; bracket — `"1".."N"` (fizinės pozicijos
viršus→apačia). **PHP skaitinius raktus paverčia int** — todėl `place()` ir lyginimai
naudoja `(string)` (žr. `DrawEngine::place`, kodėl).

#### DrawEngine taisyklės (ką koreguoti čia)

- `layout($config)` → grupėms `{groups:[{label,slots[]}]}`, bracket'ui `{pairs:[[k,k]]}`.
- `bracketSeedOrder($n)` — kanoninė sėklų→pozicijų tvarka (rekursinis dvigubinimas):
  `n=4 → [1,4,2,3]`, `n=8 → [1,8,4,5,2,7,3,6]`. `bracketPotOfSeed()` — juostos:
  `{1,2}=1, {3,4}=2, {5–8}=3, …` (`ceil(log2(seed))`).
- **Krepšeliai (pots).** *Grupės:* aktyvaus krepšelio komandos dalijamos po vieną į
  kiekvieną grupę, tada kitas krepšelis (Čempionų lygos stilius). *Bracket:* sėklos
  dedamos į savo juostos kanonines vietas, be sėklų — į likusias. **Jei duomenyse
  nėra pot/seed** (dabar Tournated sėklų neatiduoda), variklis nelūžta — visi laikomi
  vienu krepšeliu (`pickGroups`/`pickBracket` „lastPot/unseededPot" logika).
- `place($config,$state,$teamId,$slot)` — rankinis/lock dėjimas. `'BYE'` (= `DrawEngine::BYE`)
  galima dėti į kelias vietas; į pool nebaigtumą neįskaitomas (`poolEmpty`).
- `undo` / `reset` — atšaukia paskutinį / išvalo iki pool.

#### Dalyviai (Tournated → pool)

`push.js::fetchParticipants` naudoja `tournamentRegistrationParticipants(tournament, categoryId)`
(grąžina po eilutę kiekvienam žaidėjui). **Poros grupuojamos pagal `registrationId`**
(`team` yra asmeninis žaidėjo objektas, ne pora!). **Nepilnos registracijos** (vieno
žmogaus, laukiančios partnerio) **atmetamos**, jei kategorijoje yra dvejetų — kad
skaičius sutaptų su viešu dalyvių sąrašu. Seed=null, pot=null (Tournated „Skirstymas"
sėklų per šią užklausą neatiduoda; jei reikės — atskira užklausa). Pridėta į snapshot
kaip `participants_by_category`; konsolė nukopijuoja **vieną kartą** į užšaldytą pool.

#### Konsolė (`DrawControlPage`)

- **Užkrauti dalyvius iš Tournated** (`loadParticipants`) → `DrawEngine::init`.
- **Galimi žaidėjai** — redaguojami: `addTeam` / `renameTeam` / `removeTeam`
  (rankiniai id = `'m'+random`).
- **Lenta** — kiekviena vieta yra mygtukas: laisva → `selectSlot` atidaro **popup**
  (`partials/draw-slot.blade.php` + modalas blade gale); užimta → `removeFromSlot`.
- **Popup'e**: paieška (`remainingTeams`, **be diakritikų** per `fold()` — „seskauskas"
  randa „Šeškauskas"), komandų mygtukai (`placeTeam`) ir **BYE** (`placeBye`).
- **TRAUKTI** (`drawNext`, atsitiktinis), **Atšaukti** (`undo`), **Iš naujo**
  (`resetBoard` — pervadintas, nes `reset` koliduoja su Livewire), **Rodyti/Sustabdyti**
  (`play`/`stop` → `state.active_window_id`).
- Visi veiksmai per `run()` rašo į `state.draws[windowId]`; klaidos → Filament notification.

#### Payload (`resolveDraw` + controller)

`data()` draw šaka grąžina `draw: { format, board, slots{key→{id,name}}, pool[], current{team_id,name,slot},
status, active_pot, camera_corner, show_tournament, sponsors[], rotate_seconds, category }`.
`category` (grupės/kategorijos pavadinimas) pridedamas kontroleryje pagal `category_id`.

#### Overlay atvaizdavimas (`window.blade.php` draw šaka)

- **Piešiama į `<body>` host'ą `#ov-draw`** (ne į `#stage`), nes `.draw-stage` yra
  `position:fixed`, o `#stage` `will-change:transform` taptų jos containing-block ir
  suspaustų. (Ta pati priežastis kaip rezultatų juostos.)
- **Antraštė**: turnyro pavadinimas (mažas) + kategorija/grupė (didelis) + „BURTAI".
  „Krepšelis N" **nerodomas**. Tekstas su šešėliu (virš gyvo vaizdo).
- **Išdėstymas — column-major**: 2 stulpeliai, `rows=ceil(n/2)`, `grid-auto-flow:column`
  → numeracija 1–4 žemyn 1 stulpelyje, 5–8 antrame.
- **Telpa į aukštį**: `.draw-fit` apvalkalas; jei `scrollHeight > clientHeight`,
  `transform:scale(avail/natural)` (sinchroniškai, prieš animaciją). Be to bracket'o
  šriftas ~6% mažesnis nei grupių, kad 16 komandų tilptų be vyniojimo.
- **Skridimo animacija (FLIP/clone-and-fly)**: padėjus komandą, jos „Liko traukti"
  chip'o klonas lanku nuskrieja iki vietos (~720ms), tada vieta atskleidžiama
  (`.just-in`). Veikia ir rankiniam, ir TRAUKTI. Mechanika: chip'ai turi `data-team`,
  vietos `data-slot`; pozicijos imamos `getBoundingClientRect`; praeito render'io pool
  pozicijos saugomos `window.__drawPoolRects`; padėjimas „naujas", jei
  `slot|team_id !== window.__drawHandledKey`. BYE neturi chip'o → tiesiog įslysta.
- **Rėmėjai** — nuolatinė juosta (marquee): vienodos plytelės slenka po vieną į šoną
  (rinkinys dubliuotas, `translateX -50%`), greitis = `rotate_seconds`.
- **Kameros juosta**: pagal `camera_corner` paliekama **30% pločio** laisva juosta
  (`padding-*` ant `.draw-head/.draw-body/.draw-spons`).

#### Dydžiai / konstantos (dažniausiai koreguojama)

| Ką | Kur | Dabar |
|---|---|---|
| Polling (draw) | `base.blade.php` `schedule()` | 500 ms |
| Skridimo trukmė / lankas | `window.blade.php` `flyTeam` | 720 ms, vidurys −48px |
| Grupių/bracket šriftai | `window.blade.php` CSS `.dg-slot` / `.dteam` | 28px / 26px |
| Grupės pavadinimas, „BURTAI" | `.draw-head .tt` / `.badge` | 46px / 42px |
| Kameros juostos plotis | `.draw-corner-* padding` | 30% |
| Rėmėjų plytelė | `.sp-tile` | 180×72 |
| „Title-safe" paraštės | `.draw-stage padding` | 48×64px |

Dydžiai parinkti 1920×1080 transliacijai (TV min. 24–28px, antraštės ≥50% didesnės).

#### Apribojimai (v1)

Vienas operatorius, last-write-wins. Be websocket'ų (polling). Sėklos/krepšeliai
realiai neveikia, kol Tournated „Skirstymas" neperduodamas (komandos be pot/seed —
traukimas tampa atsitiktinis vienoje juostoje; rankinis dėjimas pilnas).

---

## 5. push.js (tavo PC)

Nustatymai failo viršuje (arba per aplinkos kintamuosius):
- `SITE_URL` — svetainė (def. `https://padelioturnyrai.lt`).
- `INGEST_TOKEN` — slaptas raktas; turi sutapti su serverio `.env`
  `OVERLAY_INGEST_TOKEN`.
- `TOURNAMENT_ID` — **neprivalomas** atsarginis; paprastai tuščias.

**Kuriuos turnyrus siunčia.** Kiekvieną ciklą paklausia serverio
`GET /overlay/wanted` (apsaugota tuo pačiu token'u) — gauna visus turnyrų ID,
kuriuos naudoja sukurti overlay'ai, ir siunčia būtent juos. **Pakeitus turnyrą
admin'e, push persijungia automatiškai — nereikia nieko redaguoti ar
perpaleisti.** (Jei serveris negrąžina nieko, naudoja `TOURNAMENT_ID` jei
nustatytas.)

**Paleidimas:**
```powershell
cd C:\Users\Tadas\Desktop\WEB-zinovai\tools\overlay-push
node push.js
```
Log eilutė kas ~20 s, pvz.:
```
✅ [12:01:33] Nusiųsta: "..." — 12 kat., 84 grupių, 101 susitikimų
```
Skaičiai (kat. / grupių / susitikimų) padeda patikrinti, ar duomenys tikrai
teka. „0 susitikimų" arba eilutė be „susitikimų" = sukasi sena versija →
perpaleisk.

**Perpaleidimas:** sename lange `Ctrl+C`, tada komandą iš naujo. Jei reikia —
`Get-Process node | Stop-Process -Force`, tada `node push.js`.

> `push.js` yra ES modulis (`package.json` turi `"type": "module"`).

---

## 6. Serverio maršrutai

| Metodas | Kelias | Paskirtis |
|---|---|---|
| GET | `/overlay/{overlay}` | OBS puslapis (pagal token) |
| GET | `/overlay/{overlay}/data` | JSON aktyviam langui (OBS poll'ina) |
| GET | `/overlay/wanted` | Turnyrų ID sąrašas push'ui (token) |
| POST | `/overlay/ingest` | Priima snapshot iš push (token) |

`wanted` ir `ingest` apsaugoti antrašte `X-Overlay-Token` (= `OVERLAY_INGEST_TOKEN`).
`wanted` registruotas **prieš** `/overlay/{overlay}`, kad nebūtų palaikytas token'u.

---

## 7. Administravimas

1. **Overlay** turi pavadinimą, **Tournated turnyro ID**, išvaizdą (tema,
   spalvos, logo, pozicija) ir **langus**.
2. **Langai** kuriami „Langai" sekcijoje; kiekvienam pasirenki tipą ir
   nustatymus (žr. 4 skyrių).
3. **OBS URL** kopijuojamas sąraše („OBS URL" mygtukas) — tai
   `https://.../overlay/{token}`.
4. **Valdymo puslapyje** (Transliacijos) įjungi/išjungi langus (Play/Stop) —
   tai nustato `state.active_window_id`.
5. Sąrašo viršuje „Kaip paleisti duomenų siuntimą" modalas primena push komandą.

---

## 8. OBS

- Pridėk **Browser Source**, URL = nukopijuotas overlay token URL.
- Plotis/aukštis = transliacijos raiška (pvz. 1920×1080), fonas permatomas.
- Lango turinys keičiasi automatiškai pagal tai, ką įjungi valdymo puslapyje, ir
  pagal push atnaujinimus (kas ~3 s).

---

## 9. Diegimas (deploy)

Serveris seka `feature/overlay-windows-v2` šaką (vietinė šaka serveryje gali būti
pavadinta `master`, bet rodo į šią).

```bash
cd ~/private/laravel
git pull origin feature/overlay-windows-v2
rm -f bootstrap/cache/*.php
php artisan optimize:clear
```

Jei `git pull` skundžiasi **„divergent branches"** — sulygink priverstinai
(deploy kopija, saugu; `.env` ir įkelti failai nepaliečiami):
```bash
git fetch origin
git reset --hard origin/feature/overlay-windows-v2
rm -f bootstrap/cache/*.php
php artisan optimize:clear
```

> **NIEKADA** nedaryk `php artisan config:cache` / `route:cache` / `view:cache`
> šiame chroot'intame hostinge — naudok tik `optimize:clear`.
>
> **`push.js` perpaleisti reikia tik tada**, kai keitėsi pats `push.js` arba
> snapshot forma (nauji laukai). Vien blade/serverio pakeitimams — nereikia.

---

## 10. Temos ir pozicija

Spalvos per CSS kintamuosius: `--ov-bg`, `--ov-text`, `--ov-accent`, `--ov-muted`
(iš lango temos / spalvų). Pozicija — `config.position` (`top-left` …
`bottom-right`, `center`), taikoma visiems langams **išskyrus** rėmėjų
`fullscreen` ir tvarkaraščio `results` juostą (jos visada per visą ekraną).

---

## 11. Problemų sprendimas

| Simptomas | Priežastis / sprendimas |
|---|---|
| Overlay tuščias | Nėra aktyvaus lango (Play valdymo puslapyje) arba snapshot tuščias |
| Grupės/bracketai/tvarkaraštis tušti | Push nesiunčia duomenų — patikrink push log eilutę (kat./grupių/susitikimų); perpaleisk push |
| Pakeitei turnyrą, bet rodo seną | Sena `push.js` versija arba senas procesas — perpaleisk (push pats seka admin per `/overlay/wanted`) |
| Rezultatų juosta tuščia | Turnyre nėra rungtynių su įvestu rezultatu, arba „Data" nustatyta dienai be baigtų rungtynių (palik tuščią) |
| Rezultatų juosta ne apačioje | Reikia naujausio kodo (juosta dedama į `<body>`); patikrink, ar serveryje yra naujausias commit |
| „divergent branches" deploy metu | `git fetch origin && git reset --hard origin/feature/overlay-windows-v2` |
| 403 iš ingest/wanted | `INGEST_TOKEN` ≠ serverio `OVERLAY_INGEST_TOKEN` |

---

## 12. Testai

```
php artisan test --filter=OverlayEndpointTest
```
Padengia data endpoint'ą, grupių/segmentų filtravimą, bracket segmentus
(+ seną formatą), `wanted` autorizaciją ir visus tvarkaraščio variantus.
(Standartinis Laravel `Tests\Feature\ExampleTest` ant `/` nesusijęs su
overlay'ais.)

---

## 13. Dizaino / plano dokumentai

- `docs/superpowers/specs/` — dizainai (auto-brackets, placements+court/time,
  schedule overlays).
- `docs/superpowers/plans/` — implementacijos planai.
