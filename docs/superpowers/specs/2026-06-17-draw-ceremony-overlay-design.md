# Traukimo (burtų) ceremonijos overlay — dizainas

**Data:** 2026-06-17
**Statusas:** Patvirtinta (laukia spec peržiūros prieš planą)

## Tikslas

Naujas overlay tipas transliacijai — **gyva burtų / traukimo ceremonija**. Admine
sukonfigūruojama traukiama grupė (formatas, pogrupių/bracket dydis, krepšeliai),
užkraunami dalyviai (iš Tournated arba ranka), o ceremonijos metu operatorius
gyvai traukia komandas: sistema atsitiktinai parenka komandą ir su animacija įdeda
ją į lentelę arba bracketą. Žiūrovas OBS'e mato beveik per visą ekraną išskleistą
lentą su animuotu komandos atsiradimu; vienas kampas paliekamas skaidrus gyvam
kameros vaizdui.

## Esminis sprendimas

Tai **naujas lango tipas `type: 'draw'`** esamame `Overlay` modelyje (ne atskira
sistema). Tas pats OBS URL, tas pats token, ta pati spalvų tema ir Play/Stop.
Skirtumas nuo kitų langų: kitų langų turinys yra **tik skaitomas** pushinto Tournated
snapshot'o atvaizdas, o traukimo lentos turinys (kas kur padėta) yra **kuriamas
gyvai serveryje** ceremonijos metu. Snapshot'as naudojamas tik dalyvių sąrašui
užkrauti.

Pasirinkta dėl: mažiausiai naujų dalių, panaudoja esamą token/tema/OBS/polling
infrastruktūrą, atitinka kodų bazės JSON-blob stilių. Reliacinės lentelės (atskiras
modelis) atmestos kaip YAGNI vieno operatoriaus ceremonijai.

## Architektūra

```
push.js (vartotojo PC) ──► POST /overlay/ingest ──► OverlaySnapshot.payload
   fetch Tournated                                     + participants_by_category
                                                              │
Admin „Traukimo valdymas" puslapis                            │ (užkrovimas vieną kartą)
   ── play/manual/lock/undo/reset ──► overlay.state['draws'][windowId]  ◄┘
                                              │
OBS Browser Source ──► GET /overlay/{token}/data (poll ~1s) ──► render draw board
```

## 1. Duomenų modelis

### Lango konfigūracija (`overlay.windows[]`, naujas įrašas)

```
{
  id, type:'draw', name,
  category_id,                  // Tournated kategorija dalyvių užkrovimui (nebūtina)
  format: 'groups' | 'bracket',
  group_count, group_size,      // groups formatui (pvz. 4 grupės × 4)
  bracket_size,                 // bracket formatui (8 / 16 / 32)
  use_pots: true,
  camera_corner: 'bottom-right',// kampas, paliekamas skaidrus gyvam vaizdui
  scrim_enabled, scrim_opacity  // panaudojama esama logika
}
```

### Gyva būsena (`overlay.state['draws'][windowId]`)

Namespace'inta pagal lango id, kad keli traukimo langai nesusidurtų.

```
{
  teams:  [ {id, name, pot|seed, locked_slot} ],  // užšaldytas dalyvių „pool"
  slots:  { 'A1':teamId, 'A2':null, ... },         // arba bracket pozicijos 1..N
  current:{ team_id, slot } | null,                // ką dabar atskleidžiame (animacijai)
  history:[ {team_id, slot} ],                      // Undo
  active_pot: 1,
  status: 'idle' | 'drawing' | 'done'
}
```

`slots` raktai: groups — `A1..A{group_size}, B1..` (grupės raidė + pozicija);
bracket — pozicijos indeksas `1..bracket_size` standartine sėklų tvarka.

## 2. Dalyvių srautas (Tournated + rankinis)

1. `push.js` papildomas `participants_by_category` — užklausia Tournated dalyvių
   (entries) kiekvienai kategorijai — ir įdeda į snapshot per `ingest`.
2. Valdymo puslapyje mygtukas **„Užkrauti dalyvius iš Tournated"** nukopijuoja tos
   kategorijos komandas į `state.draws[windowId].teams` **vieną kartą** (užšaldytas
   pool), automatiškai užpildydamas pot/seed iš Tournated sėklavimo, jei yra.
3. Po to galima **pridėti / pervadinti / pašalinti / perskirstyti krepšelius** ranka —
   pakeitimai išlieka, nes pool yra kopija, ne gyvas atvaizdas. CSV/rankinis kelias
   veikia taip pat (praleidžiamas 2 žingsnis).

## 3. Traukimo variklis (`DrawEngine` servisas)

Grynos funkcijos, be DB/HTTP: `(config, state) → naujas state`. Pilnai unit-testuojama.

### Vietų išdėstymas (apskaičiuojamas iš config)

- **Groups:** raktai `A1..A{group_size}`, `B1..`, … — `group_count × group_size` vietų.
- **Bracket:** pozicijos `1..bracket_size` standartine sėklų tvarka su kanonine
  sėklų-pozicijų lentele (sėkla 1 → viršus, 2 → apačia, 3–4 → pusės kotvos, 5–8 →
  ketvirčių kotvos, …) dydžiams 8/16/32.

### Krepšeliai → paskirstymas

- **Groups (Čempionų lygos stilius):** Krepšelis 1 = top `group_count` komandų,
  dalijamos **po vieną į grupę**; tada Krepšelis 2 po vieną į grupę; ir t.t.
  Kiekvienas „Traukti" ima atsitiktinę komandą iš **aktyvaus krepšelio** ir deda į
  atsitiktinę grupę, kuri dar neturi komandos iš to krepšelio, į kitą laisvą tos
  grupės poziciją. Krepšelis ištuštėja → `active_pot` didėja.
- **Bracket:** krepšeliai = sėklų juostos (Krepšelis 1 = sėklos 1–2, 2 = 3–4,
  3 = 5–8, …, paskutinis = be sėklų). Kiekvieno krepšelio komandos traukiamos į savo
  juostos kotvines vietas; be sėklų komandos užpildo likusias vietas atsitiktinai.
- **Krepšeliai išjungti** (`use_pots:false`): grynai atsitiktinė komanda → kita laisva vieta.

### Trys padėjimo keliai (visi rašo į tą patį `slots`)

1. **Traukti (atsitiktinis):** variklis parenka legalų `{team, slot}` pagal krepšelių
   taisykles, nustato `current` (animacijai), prideda į `history`, pašalina iš pool.
2. **Rankinis:** operatorius pasirenka komandą + konkrečią tuščią vietą → padedama
   tiesiogiai (apeina krepšelių taisykles; pataisymams / paskutinės minutės keitimams).
3. **Užrakinimas (prieš traukimą):** pritvirtina komandą prie vietos iš anksto;
   užrakintos komandos neįtraukiamos į atsitiktinį parinkimą toms vietoms. Bracket
   sėklos 1 ir 2 automatiškai užrakinamos prie viršaus/apačios (galima atrakinti).

### Undo / Reset / pabaiga

- **Undo** nuima paskutinį `history` įrašą, atlaisvina vietą, grąžina komandą į pool,
  atsuka `active_pot` jei reikia.
- **Reset** išvalo visas vietas iki užšaldyto pool.
- Pool ištuštėja → `status:'done'`. Jei krepšelio negalima patenkinti (rankiniai
  pakeitimai paliko per mažai), variklis grąžina aiškią klaidą, o ne deda nelegaliai.

## 4. Valdymo konsolė

Naujas Filament puslapis **„Traukimo valdymas"** (atskiras nuo paprasto Overlay
valdymo, nes tai realaus laiko operatoriaus konsolė):

- Pasirinkti overlay → traukimo langą. Mygtukas **„Užkrauti dalyvius iš Tournated"**.
- Didelis **TRAUKTI** mygtukas + aktyvaus krepšelio indikatorius („Krepšelis 1 · liko 2").
- Likusių komandų sąrašas su paieška → **padėti rankiniu būdu** į pasirinktą tuščią vietą.
- **Užrakinti**, **Atšaukti (Undo)**, **Išvalyti (Reset)**, ir **Play/Stop į OBS** šiam langui.
- Mini lentos peržiūra — matai tą patį, ką ir žiūrovas.

Livewire puslapis rašo į `overlay.state['draws'][windowId]` ir išsaugo; overlay
pollina ir animuoja.

## 5. Renderis, animacija, išdėstymas

Beveik per visą ekraną išskleista lenta, vienas kampas paliekamas skaidrus gyvam vaizdui.

- **Antraštė** viršuje: kategorija + „BURTAI" + aktyvaus krepšelio indikatorius.
- **Groups:** grupių lentelės tinkleliu; tuščios pozicijos rodo pilką „—".
- **Bracket:** tas pats medis kaip esamame bracket lange, tik su tuščiomis sėklų vietomis.
- **Pool** („Liko traukti"): likusios komandos kaip „chip"ai; ištraukta dingsta.
- **Atskleidimas:** paspaudus Traukti, centrinis prožektorius ~2s „ruletė" (cikliškai
  rodo likusius vardus), tada užsifiksuoja ant parinktos komandos ir rodo paskirties
  vietą („→ Grupė B · 1 vieta"); komanda animuotai įvažiuoja į vietą, kuri blyksteli
  akcento spalva.
- **Kameros kampas** — skaidri sritis (konfigūruojamas kampas); OBS'e per ją matosi
  gyvas vaizdas; lenta ten niekada nepiešia.

**Srautas / vėlavimas:** traukimo langas pollina **~1s** (vietoj 3s kitur). Traukti
įrašo `current` + naują vietą į `state`; overlay pamato kitą polling'ą ir paleidžia
ruletę, kuri paslepia ≤1s vėlavimą. Be websocket'ų — atitinka esamą polling modelį.

## 6. Klaidų valdymas

- Tuščias pool → „TRAUKIMAS BAIGTAS".
- Kategorija be dalyvių snapshot'e → konsolė įspėja, dedi rankiniu būdu.
- Krepšelis nebepatenkinamas dėl rankinių pakeitimų → konsolė blokuoja Traukti su
  aiškia žinute, o ne deda nelegaliai.
- Pasenęs snapshot'as nesvarbus — pool yra užšaldyta kopija.

## 7. Testavimas (TDD)

**Unit (`DrawEngine`):** krepšelio-po-grupę paskirstymas; bracket sėklų-vietų
atvaizdavimas 8/16/32; užrakinimai pašalinami iš atsitiktinio parinkimo; undo
atstato pool + krepšelį; rankinis padėjimas; pabaiga; nepatenkinamo krepšelio klaida.

**Feature:** `/overlay/{token}/data` payload traukimo langui (grąžina lentos būseną);
konsolės veiksmai mutuoja `state` (draw/manual/lock/undo/reset/play/stop).

## Apimties ribos (v1)

- Vienas operatorius vienu metu (last-write-wins, be konfliktų sprendimo).
- Be websocket'ų (polling pakanka).
- Be istorijos eksporto / PDF — burtų rezultatas lieka snapshot'e ir lentoje.
- Bracket sėklavimas: standartinės sėklų pozicijos dydžiams 8/16/32.

## Susiję dokumentai

- [Bendras overlay sistemos vadovas](../../overlays.md)
- Įgyvendinimo planas: `docs/superpowers/plans/2026-06-17-draw-ceremony-overlay.md` (bus sukurtas toliau)
