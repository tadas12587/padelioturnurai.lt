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
| `POLL_INTERVAL_MS`| `20000`                      | Kas kiek ms siųsti                 |
