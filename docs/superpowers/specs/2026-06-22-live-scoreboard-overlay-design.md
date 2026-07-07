# Gyvo rezultato (REZULTATAS) overlay — dizainas

**Data:** 2026-06-22
**Statusas:** Patvirtinta kryptis (laukia spec peržiūros)

## Tikslas

Naujas `type:'score'` overlay: **kompaktiška gyvo rezultato kortelė** (kaip padelio
transliacijose). Operatorius veda rezultatą **rankiniu būdu (+ / −)**, o sistema pati
skaičiuoja taškus, geimus, setus, tiebreak'ą ir sukioja servą. Spalvos — pagal overlay
temą. Padel = dvejetai (po 2 žaidėjus komandoje).

## Padelio skaičiavimas (santrauka)

- Taškai geime: 0 → 15 → 30 → 40 → geimas.
- **Lygiosios (40–40)** — pasirenkama taisyklė:
  - **Pranašumas** (advantage): laimi 2 taškų skirtumu (neribotai).
  - **Auksinis taškas** (golden / punto de oro): prie 40–40 vienas lemiamas taškas.
  - **STAR**: prie 40–40 → 1-as pranašumas; jį pralaimėjus → 40–40 → 2-as pranašumas;
    jį pralaimėjus → **Star taškas** (vienas lemiamas). T.y. daugiausiai 2 pranašumai,
    tada lemiamas taškas.
- Geimai sete: setas laimimas prie `games_per_set` (numatyta 6) 2 geimų skirtumu; kai abi
  komandos pasiekia `tiebreak_at` geimų (numatyta = `games_per_set`) → **tiebreak**.
  („iki 6" → tiebreak 6–6; „iki 9" → `tiebreak_at`=8 → tiebreak 8–8, laimėtojas 9–8.)
- Tiebreak (mažasis): iki `tiebreak_to` (numatyta 7), 2 skirtumu.
- Setai: iki `sets_to_win` laimėtų (numatyta 2 → best of 3).
- Lemiamas setas: jei `super_tb` įjungtas — vietoj pilno seto **super tiebreak** iki
  `super_tb_to` (numatyta 10), 2 skirtumu.
- Servas: kiekvieną geimą pereina kitai komandai; komandos viduje 2 žaidėjai serva
  pakaitomis. Operatorius nustato pradinį; toliau sukasi automatiškai (galima perjungti).

## Komponentai

### 1. `App\Services\ScoreEngine` (gryna logika, unit-testuojama)

Būsena (`overlay.state['score']`):
```
{ teams:[nameA,nameB],
  sets:[[gA,gB],…],        // baigtų setų geimų rezultatai (rodymui)
  sets_won:[a,b],
  games:[a,b],             // einamo seto geimai
  points:[a,b],            // taškų indeksai 0..3 (0/15/30/40)
  deuce:{ mode-derived },  // advantage/golden/star būsena: adv_team, star_stage
  tiebreak:bool, super_tiebreak:bool, tb:[a,b],
  server_team:0|1, server_player:0|1,
  status:'playing'|'finished', winner:null|0|1,
  history:[…] }            // undo (būsenų kaminas)
```

Metodai (gryni, `(config,state)→state`):
- `init(config, teams)` — pradinė būsena; nustato lemiamo seto super-tiebreak'ą kai reikia.
- `point(config, state, team)` — prideda tašką ir perskaičiuoja viską pagal taisykles
  (geimas / setas / tiebreak / super-tiebreak / STAR būsenų automatas); sukioja servą;
  įrašo į `history`.
- `undo(config, state)` — atstato prieš tai buvusią būseną.
- `setServer(state, team, player)` — rankinis servo nustatymas.
- `reset(config, state)` — iš naujo (išlaiko komandas + taisykles).

**STAR būsenų automatas** prie 40–40: `star_stage` ∈ {0(1-a deuce), adv1, 1(2-a deuce),
adv2, star}. Pralaimėjus pranašumą — grįžta į deuce; po 2-o pranašumo — Star taškas.

### 2. Nustatymai (lango konfigūracija, admine)

Formatas: `score_games_per_set` (6/9…), `score_tiebreak_at` (kada TB; numatyta =
games_per_set), `score_sets_to_win` (1/2/3), `score_tiebreak` (bool) +
`score_tiebreak_to` (7), `score_super_tb` (bool) + `score_super_tb_to` (10),
`score_deuce_mode` (advantage/golden/star).
Išvaizda: `score_position` (viršus-kairė / viršus-centras / viršus-dešinė; galima ir
apačia), `score_width` (px, viskas proporcingai — responsive), `show_level` (rodyti lygį).

Formatai „1 setas iki 6 / iki 9 / 2 setai iki 6" išreiškiami per `games_per_set` +
`sets_to_win`.

### 3. „Rezultatas" valdymo puslapis (Filament)

- Pasirenki overlay → `score` langą → rungtynes iš tvarkaraščio (komandų vardai, kortas,
  kategorija/lygis).
- Mygtukai: **+ / −** kiekvienai komandai (− = **undo**), **servas** (kam; auto-sukasi,
  galima perjungti), **Iš naujo**, **Rodyti / Sustabdyti (OBS)**.
- Mini rezultato peržiūra (kaip matys žiūrovas).
- Rašo į `overlay.state['score']`; overlay pollina.

### 4. Overlay langas (`type:'score'`)

Kompaktiška kortelė (ne per visą apačią), piešiama į `<body>` host'ą `#ov-score`.
- **Antraštė**: lygis/kategorija + kortas·etapas.
- **Dvi komandų eilutės**: komandos vardas (**kiekvienas žaidėjas — „V. Pavardė", pvz.
  „T. Šeškauskas / J. Petraitis"**), baigti setai (skaičiai), einami geimai, einami taškai
  (0/15/30/40/AD/★), servo taškelis prie servuojančios komandos. Tiebreak'e taškai —
  skaičiais.
- **Pozicija** pagal `score_position`; **plotis** `score_width` — visi dydžiai
  proporcingai (responsive) per CSS kintamąjį / mastelį.
- **Spalvos — iš temos** (`var(--ov-bg/text/accent/muted)`).

## Duomenų srautas

```
Kontrolė (+/−, servas) → state['score']  (ScoreEngine perskaičiuoja)
OBS → /overlay/{token}/data (score) → resolveScore → kortelės payload
   → window.blade score šaka (kompaktiška kortelė, tema, pozicija, plotis)
```

Rungtynių pasirinkimas duoda komandų vardus + kortą + kategoriją (lygį) iš snapshot
matches; rezultatas — rankinis (ne iš Tournated).

## Klaidų valdymas / niuansai

- `−` prie tuščios istorijos — nieko nedaro.
- Nepasirinktos rungtynės — kortelė tuščia / „Pasirink rungtynes".
- Vienas operatorius (last-write-wins).
- „iki 9" interpretacija: setas laimimas prie `games_per_set` 2 skirtumu, tiebreak prie
  `gps–gps` (jei įjungtas). (Jei reikės „first-to-N" be 2 skirtumo — atskiras nustatymas.)

## Apimties ribos (v1)

- Rezultatas rankinis (ne auto iš Tournated).
- Be websocket'ų (polling ~1s).
- Servo rodymas — komandos lygiu (žaidėjo lygis sekamas viduje, bet nerodomas, jei nesplit).

## Testavimas (TDD)

Unit (`ScoreEngine`): taškų progresija 0/15/30/40; paprastas geimas; **advantage**,
**golden**, **STAR** deuce automatai; geimo/seto laimėjimas; **tiebreak** iki 7 (2 skirt.);
**super-tiebreak** lemiamame sete; mačo pabaiga (best of 1/3); servo sukimas; **undo**.
Feature: `/data` score payload; valdymo veiksmai (point/undo/server/reset/play/stop).

## Susiję

- [Bendras overlay vadovas](../../overlays.md)
- Įgyvendinimo planas: `docs/superpowers/plans/2026-06-22-live-scoreboard-overlay.md` (toliau).
