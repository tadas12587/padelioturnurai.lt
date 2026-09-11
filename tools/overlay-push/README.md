# Overlay push scenarijus

Šis scenarijus paleidžiamas **tavo kompiuteryje** (ne serveryje), nes serveris
negali pasiekti `api.tournated.com`. Jis periodiškai nuskaito Tournated turnyro
duomenis ir nusiunčia į svetainę (`POST /overlay/ingest`), iš kur juos rodo
overlay'ai.

## Reikalavimai

- [Node.js](https://nodejs.org/) 18 arba naujesnė versija.

## Paruošimas (vieną kartą)

1. Serveryje, `.env` faile, pridėk slaptą raktą (bet kokią ilgą atsitiktinę eilutę):

   ```
   OVERLAY_INGEST_TOKEN=tavo-ilgas-slaptas-raktas
   ```

2. Šiame scenarijuje (`push.js`) viršuje įrašyk tą patį raktą į `INGEST_TOKEN`
   ir nustatyk `TOURNAMENT_ID`.

## Paleidimas (kiekvienos transliacijos pradžioje)

```bash
node push.js
```

Arba nenurodant nustatymų faile:

```bash
TOURNAMENT_ID=10424 INGEST_TOKEN=tavo-raktas node push.js
```

Pamatysi `✅ Nusiųsta: ...` kas ~20 sekundžių. Palik langą atidarytą visą
transliaciją. Uždarius (Ctrl+C) — duomenys nustoja atsinaujinti (overlay rodys
paskutinę gautą būseną).

## Nustatymai

| Kintamasis        | Numatyta                     | Aprašymas                          |
|-------------------|------------------------------|------------------------------------|
| `SITE_URL`        | `https://padelioturnyrai.lt` | Tavo svetainės adresas             |
| `INGEST_TOKEN`    | —                            | Slaptas raktas (kaip serverio .env)|
| `TOURNAMENT_ID`   | `10424`                      | Tournated turnyro ID               |
| `POLL_INTERVAL_MS`| `120000`                     | Kas kiek ms siųsti (grafikas/rezultatai; sunkūs duomenys ~2x rečiau) |

## Viešas "Grafikas" puslapis (`schedule-push.js`)

Atskiras scenarijus tam pačiam tikslui, bet viešam tvarkaraščio/rezultatų/
lentelių puslapiui (`/grafikas/{turnyras}`), skirtam dalyviams ir svečiams —
ne OBS overlay'ams. Naudoja **naują oficialų Tournated `/api/v2`** (su API
raktu), o ne seną GraphQL.

1. Portale (`api.tournated.com` → API Keys) susikurk raktą ir įrašyk į
   `tools/overlay-push/.api-key` (į git nepatenka) arba `TOURNATED_API_KEY`
   aplinkos kintamąjį.
2. Ingest tokeną (tą patį, kaip serverio `.env` `OVERLAY_INGEST_TOKEN`)
   įrašyk į `tools/overlay-push/.ingest-token` arba `INGEST_TOKEN` kintamąjį.
3. Paleidimas:
   ```bash
   TOURNAMENT_ID=11532 node schedule-push.js
   ```

**Svarbu:** šio turnyro tipo (klubų lyga, dideli mačų sąstatai) `/matches`
endpoint'as Tournated pusėje yra nestabilus daugiau nei 1 mačui viename
atsakyme (502) — scenarijus todėl eina po vieną mačą su bandymų kartojimu, ir
vienas pilnas ciklas gali užtrukti kelias minutes. Tai apribojimas Tournated
API pusėje, ne šio scenarijaus klaida.
