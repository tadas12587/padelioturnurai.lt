# Planas: Tournated API → svetainė be tavo kompiuterio

Data: 2026-11-12. Tikslas: svetainė pati (be PC) gauna visą turnyro informaciją iš Tournated ir ją naudoja **žmonėms** (grafikas, grupės, tinkleliai, rezultatai, žaidėjo paieška) ir **transliacijoms** (OBS grafika, kortų pultas).
Pavyzdžiai: **10210** (įprastas turnyras, baigtas – visi formatai) ir **11532** (klubų lyga – toks bus kitas turnyras).

---

## 1. Ką gauname (patikrinta 2026-11-12)

### 10210 – Lietuvos jaunių čempionatas (įprastas formatas)
| Duomenys | Endpoint | Kiekis | Būklė |
|---|---|---|---|
| Turnyras, datos, laiko juosta, kortai, kategorijos | `/tournaments/{id}?include=courts,categories` | 8 kortai, 10 kategorijų | ✅ |
| Mačai: data, laikas, kortas, žaidėjai, setai, nugalėtojas, vyksta/ne, raundas, grupė/tinklelis | `/matches?view=full` | 213 (149 sužaisti), 3 užklausos, 1,6 s | ✅ (cursor nuo `id:0`) |
| Grupės + nariai su vieta + mačų sąrašas | `/groups?include=entries,matches` | 9 grupės | ✅ |
| Tinkleliai (8/16), pozicijos, paguodos tinkleliai 5-8, 9-16, 13-16 | `/draws?include=entries,matches` | 7 tinkleliai | ✅ |
| Galutinės vietos ir taškai | `/standings` | 10 lentelių | ✅ |
| Žaidėjai | `include=participants` | 141 | ✅ (⚠ su el. paštu/telefonu – viešai NErodyti) |
| Gyvas mačo rezultatas | `/matches/{id}/live` (≥5 s) | 1 mačas / užklausa | ✅ |

### 11532 – Klaipėdos klubų lyga (kito turnyro formatas)
| Duomenys | Šaltinis | Būklė |
|---|---|---|
| Turnyras, kortai (6), komandos (4 klubai) su sudėtimis | API `/tournaments`, `/teams?fields=members` | ✅ |
| Grupė „Bendra“: 4 klubai su vieta, 80 mačų ID | API `/groups` | ✅ |
| Klubų lentelė | API `/standings` | ✅ |
| Kiekvieno mačo rezultatas | API `/matches/{id}/live` | ✅ (po vieną) |
| **Mačų sąrašas** (kortas, laikas, poros, lygis „Moterys B 1“) | API `/matches` | ❌ **502 – Tournated klaida** |
| ↳ tas pats per viešą GraphQL | `play.padel.lt/api/graphql` | ✅ 72 mačai, ~9 s |

**Išvada:** įprastiems turnyrams užtenka oficialaus API. Klubų lygai, kol Tournated nesutaisys `/matches`, mačų sąrašą imsime iš GraphQL, o viską kitą – iš API. Programa pati renkasi šaltinį ir persijungia, kai API pradės veikti.

---

## 2. Hostingas – PATIKRINTA 2026-11-12: kliūties nėra

Per SSH (raktas „Claude (Tado PC)“, `~/.ssh/padelioturnyrai_ed25519`) patikrinta: serveris **pasiekia** `api.tournated.com` (401 per 0,08 s, t. y. tiesiog be rakto) ir `play.padel.lt` GraphQL (200). PHP `curl` taip pat veikia. Senas teiginys `docs/overlays.md`, kad ryšys blokuojamas, nebegalioja.

Hostingo pulte yra **CRON** (iki „kas 1 minutę“, tipas „PHP skriptas (CLI)“ arba URL, max 2 val.). `crontab` per SSH nėra – užduotis kuriama pulte.

**→ Variantas A: svetainė traukia pati.** Laravel komanda `tournated:sync` + Laravel scheduler, kurį pulto CRON paleidžia kas 1 min. 0 €, jokio papildomo serverio, PC nebereikia.

---

## 3. Architektūra

```
Tournated API ─┐                        ┌─> Viešas puslapis /grafikas/{id}  (žmonėms)
               ├─> SYNC (A: Laravel cron │
GraphQL ───────┘      B: Worker)  ──> DB ┼─> OBS grafika /overlay/...        (transliacija)
 (tik klubų lygai)                       └─> Kortų pultas (court-node): „kas žaidžia 3 korte“
```

Vienas duomenų šaltinis svetainėje (**turnyro snapshot v2**), iš kurio maitinasi ir viešas puslapis, ir transliacijų grafika – nebeliks dviejų atskirų „push“ programų (`push.js` ir `schedule-push.js`).

### Sinchronizavimo ritmas (limitas 100 užklausų / min.)
| Kas | Kada | Užklausos |
|---|---|---|
| Turnyrų sąrašas (`status=upcoming,active`) – automatiškai randa naujus turnyrus | kas 30 min. | 1 |
| Struktūra: kortai, kategorijos, grupės, tinkleliai, komandos | kas 10 min.; turnyro dieną kas 2 min. | ~5 |
| Mačai (`view=full` arba GraphQL) | turnyro dieną kas 1 min. (galima `updatedSince` – tik pasikeitę) | 1–3 |
| Lentelės / galutinės vietos | kas 5 min. | 1 |
| Gyvi rezultatai (`/live`) tik **vykstantiems** mačams | kas 10 s, tik kai yra transliacija | ≤6 kortai |

Ne turnyro dienomis – beveik nulis užklausų.

---

## 4. Ką gaus žmonės (viešas puslapis)

Esamas `/grafikas/{id}` papildomas:
1. **Tvarkaraštis** (jau yra) – tikslūs laikai ir kortai, „vyksta dabar“ žymė.
2. **Grupės** – oficialios Tournated vietos (ne mūsų skaičiavimas), mačai po lentele.
3. **Tinkleliai** (naujas) – ketvirtfinaliai → pusfinaliai → finalas + 3 vieta, paguodos tinkleliai; rezultatai įsirašo patys.
4. **Rezultatai / vietos** (naujas) – galutinės vietos kiekvienoje kategorijoje.
5. **Žaidėjo paieška** (jau yra) – visi žaidėjo mačai ir kur/kada žaidžia.
6. **Klubų lygai**: klubų lentelė + kiekvieno susitikimo (12 mačų) suvestinė, lygiai („Moterys B 1“).
7. **Turnyrų sąrašas** – svetainė pati rodo artėjančius ir vykstančius turnyrus (nereikia rankiniu būdu įvesti ID).

Privatumas: el. paštai, telefonai, gimimo datos į svetainės DB **nesaugomi**.

## 5. Ką gaus transliacijos

1. **OBS grafika** (esami langai: tvarkaraštis, rezultatų juosta, grupės, tinklelis) maitinasi iš to paties snapshot – be `push.js`.
2. **Kortų pultas (court-node)**: mygtukas „imti mačą iš Tournated“ – pasirenki kortą, pultas parodo, kas dabar/toliau žaidžia, vardai „J. Pavardė“ įsirašo patys (kaip dabar iš piramidės iššūkio).
3. **Rezultato šaltinis**: mūsų pultas lieka pagrindinis (be vėlavimo); Tournated `/live` – atsarginis/kortams be operatoriaus.
4. Po mačo – rezultatas automatiškai matosi tvarkaraštyje ir lentelėse.

---

## 6. Darbo etapai

| # | Etapas | Rezultatas | Trukmė |
|---|---|---|---|
| 0 | ~~Hostingo patikra~~ ✅ atlikta – variantas A | – | – |
| 1 | Sync branduolys: API klientas (limitai, pakartojimai, cursor nuo id:0), GraphQL atsarginis, snapshot v2 + ingest | Duomenys svetainėje be PC; testai su 10210 ir 11532 įrašais | 1 d. |
| 2 | Administravimas: Filament puslapis „Tournated“ – turnyrų sąrašas, įjungti sync, paskutinio sync laikas/klaidos | Matai, ar viskas gyva | 0,5 d. |
| 3 | Viešas puslapis: Grupės (oficialios), Tinkleliai, Rezultatai, turnyrų sąrašas | Žmonės mato viską | 1–1,5 d. |
| 4 | Klubų lygos vaizdas (11532 formatas) | Paruošta kitam turnyrui | 0,5 d. |
| 5 | Transliacijos: OBS langai iš snapshot v2; court-node „imti mačą iš kortų“ | Viena sistema | 1 d. |
| 6 | `push.js`/`schedule-push.js` palikti tik avariniam atvejui | Mažiau programų | – |

**Prioritetas kitam turnyrui (11532 formatas):** 0 → 1 → 2 → 4 → (3, 5).

## 7. Rizikos
- **Tournated `/matches` klubų lygai 502** – sprendžiama GraphQL; Tournated atsakyti su URL ir laiku (atsakymo tekstas jau paruoštas pokalbyje).
- **GraphQL neoficialus** – gali pasikeisti be įspėjimo; todėl jis tik atsarginis.
- **Rakto galiojimas 6 mėn.** – „Svetaine-grafikas“ baigiasi 2027-05-12; admin puslapis įspės prieš 30 d.
- **Cursor klaida** – apeinama (`id:0`), bet pranešti Tournated.
- **PII** – `/players` grąžina kontaktus net be teisės; nesaugoti, pranešti Tournated.

## 8. Sprendimai, kurių reikia iš tavęs
1. Kada kitas klubų lygos turnyras (data ir, jei yra, Tournated ID)?
2. Kurie transliacijos langai svarbiausi kitam turnyrui?
