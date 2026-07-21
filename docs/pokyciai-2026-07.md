# Overlay pakeitimai — 2026 m. liepa

Šakelė: `feature/overlay-windows-v2`. Šiame faile — visi šios darbo sesijos
pakeitimai: nauji overlay langai, valdymo patobulinimai ir (svarbiausia
operacijai) Tournated duomenų „push" perdarymas po to, kai Tournated pakeitė
savo API.

---

## 1. SVARBIAUSIA OPERACIJAI

### Tournated prisijungimo tokenas
Tournated užrakino dalį API (draws, groups, registracijos, dalyviai) — dabar
jiems reikia prisijungimo. `push.js` naudoja **Bearer tokeną**:

- Tokenas imamas iš aplinkos kintamojo `TOURNATED_TOKEN` **arba** iš failo
  `tools/overlay-push/.token` (šis failas **į git nepatenka** — laikom kaip
  slaptažodį).
- Su tokenu gaunami **oficialūs** draws/groups/dalyviai (ne atkurti iš
  rungtynių).
- Banneryje matosi būsena ir galiojimas:
  `Tournated tokenas: ✔ prijungtas (galioja iki …)`.

**Tokeno atnaujinimas** (jis galioja ~3 dienas):
1. Prisijunk prie `play.padel.lt`.
2. DevTools (F12) → **Network** → filtras `graphql` → perkrauk puslapį.
3. Bet kurios `graphql` užklausos **Request Headers** → nukopijuok
   `authorization: Bearer …` reikšmę.
4. Įrašyk į `tools/overlay-push/.token` (galima su „Bearer " ar be jo).

> Pastaba: `tournament(id:)` (turnyro pavadinimas) Tournated pusėje kabo net su
> tokenu — pavadinimas paliekamas ankstesnis. Tai nekritiška.

### „Push" scenarijaus paleidimas
```powershell
cd C:\Users\Tadas\Desktop\WEB-zinovai\tools\overlay-push
node push.js
```
`push.js` yra **ES modulis** (šakninis `package.json` turi `"type":"module"`).

### Atnaujinimo dažnis
- **Grafikas + rezultatai: kas 30 s** (rungtynės siunčiamos kiekvieną ciklą).
- **Grupės / bracketai / dalyviai: kas ~60 s** (kraunama fone, negaišina
  grafiko).

Keičiama be kodo:
```powershell
$env:POLL_INTERVAL_MS=30000; $env:FULL_EVERY=2; node push.js
```
- `POLL_INTERVAL_MS` — grafiko ciklo intervalas (ms).
- `FULL_EVERY` — kas kiek grafiko ciklų atnaujinti „sunkius" duomenis.

### Deploy į serverį
Kai keitėsi **serverio** kodas (`app/…`, `resources/…`, migracijos):
```bash
cd ~/private/laravel
git fetch origin && git reset --hard origin/feature/overlay-windows-v2
rm -f bootstrap/cache/*.php
php artisan migrate --force        # tik kai pridėtos migracijos
php artisan optimize:clear
```
Po deploy **OBS Browser Source → Refresh**. Vien `push.js` pakeitimams
serverio deploy'inti nereikia.

---

## 2. TOURNATED INTEGRACIJOS PERDARYMAS (`push.js`)

Kas sulūžo Tournated pusėje ir kaip apeita:

| Užklausa | Būsena | Sprendimas |
|---|---|---|
| `matches` | veikia (viešai) | grafikas, rezultatai, Akistata — iš čia |
| `tournamentDrawCategories` | veikia (viešai) | kategorijų šaltinis |
| `tournament(id:)` | **kabo** | trumpas 5 s laikas + praleidimas; pavadinimas paliekamas ankstesnis |
| `draws` | **Unauthorized** | su tokenu — oficialu; be tokeno — atkuriama iš `matches` |
| `groups` | **tuščia / Unauthorized** | su tokenu — oficialu; be tokeno — atkuriama iš `matches` |
| `tournamentRegistrationParticipants` | **Unauthorized** | su tokenu — oficialu; be tokeno — atkuriama iš `matches` |

Papildomi patobulinimai:
- **Atsparumas gedimams:** GraphQL laiko limitas, tuščio/ne-JSON atsakymo
  gaudymas, aiškūs pranešimai vietoj „Unexpected end of JSON input".
- **Dalinis siuntimas (`partial`):** kai nežinom pavadinimo/kategorijų, serveris
  **palieka anksčiau išsaugotus** duomenis (nebeperrašo į tuščius).
- **Kategorijų papildymas:** `tournamentDrawCategories` grąžina tik tas
  kategorijas, kurios turi bracketą; grupinės-only kategorijos papildomos iš
  `matches`.
- **Atkūrimas iš `matches` (atsarginis kelias, kai nėra tokeno):**
  - grupių lentelės su standartiniu padel rikiavimu (pergalės → tarpusavis →
    setų sk. → geimų sk.);
  - bracketai (segmentai pagal `bracketType`, raundai iki finalo, 3-ios vietos
    mačas);
  - dalyviai (unikalūs iš rungtynių).
- **Greitis:** rungtynės — kiekvieną ciklą; „sunkūs" duomenys — fone, kešuojami
  ir atnaujinami rečiau; užrakintos užklausos, kai jos negalioja, praleidžiamos.

Susiję commit'ai: `74af06e`, `5c3ce06`, `bad80da`, `7decd0b`, `4de47c5`,
`edd3031`, `9b6be2a`, `0d033da`, `2611abe`, `0eb4514`.

---

## 3. NAUJI / PAKEISTI OVERLAY LANGAI IR FUNKCIJOS

### Foto sienelė (`type: photowall`) — nauja
Step-and-repeat („press wall") fonas: rėmėjų logotipai kartojami per visą
ekraną, viršuje — turnyro logo ir/ar laisvo teksto pavadinimas.
- Šaltinis: rėmėjų sąrašas / **galerija** / įkeltos nuotraukos.
- Turnyro **logo** ir **pavadinimas**: vieta (centras + 4 kampai + apačia),
  dydis (greitas presetas **arba** tikslus skaičius vw), X/Y poslinkis (gali
  užeiti už krašto), fonas po jais (uždengia logotipus). Logo — virš teksto.
- Išdėstymas: plytelės / griežtas tinklelis / įstrižas.
- Fono raštas: vientisas **arba** 2 spalvų šachmatai (iš temos).
- Animacija: eilės slenka į šonus pirmyn-atgal; greitis — **slankikliu** iki
  labai lėto (vos matomo).
- Spalvos — iš pasirinktos temos.

Commit'ai: `c4d0d3d`, `2d634a1`, `4aac219`, `2ac3e27`, `003a1f3`.

### Galerijos (daugkartinės) — nauja
Admin puslapis „Galerijos": sukuri pavadintą nuotraukų rinkinį ir naudoji jį
daug kur (rėmėjų juostoje, Foto sienelėje ir kt.).
- Pašalinus nuotrauką arba ištrynus galeriją — failai **ištrinami ir iš
  serverio** (nesikaupia šiukšlės).

Commit'ai: `b4779af`, `33f2098`.

### Akistata (H2H) — animuotas fonas
- Fono režimai: nėra / **spalvų maišymas** (temos spalvų debesys) / **fonas +
  nuotrauka** (įkelta nuotrauka daugybinama ir skraido, parallax).
- Intensyvumas: subtilus / vidutinis / ryškus.
- Centre — **tikra „Rezultatas" kortelė** (ta pati išvaizda), su fade
  perjungiant tarp laiko ir rezultato; skaičiuoklis užsikrauna su ta pačia pora
  automatiškai.

Commit'ai: `581b398`, `d9bdf94`, `0847875`.

### Keli langai vienu metu
Overlay dabar gali rodyti **kelis langus vienu metu** (pvz. Akistata +
Rezultatas + rėmėjai). Kiekvienas langas — atskiras įjungimas/išjungimas.
- „Overlay valdymas" (admin) ir mobilus — **daugialangiai**.
- „Valdymas (OBS dock)" — **išskirtinis perjungimas** (senas išsijungia, naujas
  įsijungia).

Commit'ai: `60592b7`, `7d15869`.

### Rezultatas — bendras turnyrui + mobilus valdymas
- Rezultatas saugomas **turnyro lygiu** — įvedi kartą, matomas visuose to
  turnyro overlay'uose.
- „**+ geimas**" mygtukas (visas geimas iškart), šalia „+ taškas".
- Mobilus valdymas (`/overlay/{token}/score`):
  - rungtynių **filtrai** (kortas, lygis, žaidėjo paieška);
  - **langų įjungimas/išjungimas** iš telefono (bet kurio lango, ne tik
    rezultato);
  - **rezultato centre Akistatoje** jungiklis;
  - visa būsenos juosta — paspaudžiama;
  - vedimas atskirtas nuo to, kuris langas rodomas.

Commit'ai: `9d8f3b6`, `98612bb`, `9b9eda3`, `487613d`, `d9aa6b5`, `7bd8bec`,
`e8b29ac`, `352d9bb`, `2bb6c3a`.

---

## 4. Pastabos
- Rankinis rezultato vedimas (mobiliame/admin) — **tiesioginis** ir nuo
  `push.js` nepriklauso; matomas iškart.
- Jei nėra tokeno arba jis pasibaigęs — transliacija **nenutrūksta**: grupės,
  bracketai ir dalyviai atkuriami iš rungtynių (kai kategorija jau turi
  rungtynes).
