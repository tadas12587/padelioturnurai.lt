# Supaprastintas overlay valdymo skydelis (OBS dock) — dizainas

**Data:** 2026-06-19
**Statusas:** Patvirtinta

## Tikslas

Greitas, be prisijungimo valdymo skydelis, kurį galima įsidėti kaip **OBS „Custom
Browser Dock"** ir vienu paspaudimu perjungti, kuris overlay langas rodomas.

## Sprendimas

- **`GET /overlay/{token}/control`** — atskiras, minimalus, tamsus HTML puslapis
  (ne Filament). Rodo overlay langų sąrašą dideliais mygtukais; paspaudus → langas
  rodomas; aktyvus paryškintas; apačioje **Sustabdyti**. Token URL’e autorizuoja.
- **`POST /overlay/{token}/control`** `{action:'play'|'stop', window_id?}` → rašo tą
  patį `state.active_window_id` kaip admin `OverlayControlPage`. CSRF išjungtas
  (`overlay/*/control` → `bootstrap/app.php` except), nes OBS dock neturi sesijos/token.
- **Sinchronizacija**: JS pollina esamą `GET /overlay/{token}/data` kas ~2 s, skaito
  `window_id` (jei `visible`) ir paryškina aktyvų langą — atsispindi ir kai perjungiama
  iš admin.

## Apimties ribos (v1)

- Tik langų Play/Stop (be „Kitas susitikimas", be traukimo veiksmų — tai lieka admin).
- Apsauga = token URL’e (kas turi nuorodą, gali valdyti — kaip ir OBS šaltinio URL).
- Be realaus laiko push (polling pakanka).

## Failai

- `routes/web.php` — GET + POST `/overlay/{overlay}/control`.
- `bootstrap/app.php` — `overlay/*/control` į CSRF `except`.
- `app/Http/Controllers/OverlayController.php` — `control()`, `controlAction()`.
- `resources/views/overlays/control.blade.php` — skydelis.
- `tests/Feature/OverlayEndpointTest.php` — play/stop + render testai.

## Testavimas

- POST play → `active_window_id` nustatomas; stop → išvalo (JSON atsakymas).
- GET puslapis atvaizduoja langų pavadinimus.
