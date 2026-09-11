# Head to Head (Akistata) Overlay — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A new `type:'h2h'` overlay showing two padel teams (2 players each) facing each other with their cut-out GIF photos, a centre column (VS + scheduled time / live score / court·stage / custom text), and a slow zoom toward the viewer.

**Architecture:** Player photos are uploaded per person via a Filament `PlayerPhoto` resource and stored in a `player_photos` table, keyed by a normalised name (`person_key`). The match-up is chosen live in an `Akistata` control page (pick a fixture from the snapshot matches) which writes `state['h2h_match_id']`. `OverlayData::resolveH2h` joins the chosen match's player names to their photos (or a male/female stock SVG), and the `window.blade` h2h branch renders the facing teams + centre on a `<body>` host.

**Tech Stack:** Laravel 12, Filament 3.3, PHPUnit 11, Blade + vanilla JS.

**Spec:** `docs/superpowers/specs/2026-06-20-head-to-head-overlay-design.md`

**Key decision (deviation from spec):** photos key by **normalised name**, not Tournated user id. `matches[].team1/team2` and the participant pair names both come from the same Tournated `user{name surname}`, so names match exactly — no `push.js` change needed. (User-id keying noted as a future robustness improvement.)

---

## File Structure

| File | Responsibility | Action |
|------|----------------|--------|
| `database/migrations/xxxx_create_player_photos_table.php` | `player_photos` schema | Create |
| `app/Models/PlayerPhoto.php` | Model | Create |
| `app/Services/OverlayData.php` | `personKey`, `genderFromCategory`, `participantsPeople`, `photoFor`, `resolveH2h` | Modify |
| `app/Http/Controllers/OverlayController.php` | `data()` h2h branch | Modify |
| `app/Filament/Resources/OverlayResource.php` | `'h2h'` window type + centre config fields | Modify |
| `app/Filament/Resources/PlayerPhotoResource.php` (+ Pages) | Per-person photo upload + "load participants" | Create |
| `app/Filament/Pages/H2hControlPage.php` (+ view) | Pick fixture → `state['h2h_match_id']` + play/stop | Create |
| `public/img/h2h/player-male.svg`, `player-female.svg` | Stock silhouettes | Create |
| `resources/views/overlays/window.blade.php` | h2h render branch + CSS | Modify |
| `resources/views/overlays/base.blade.php` | cleanup + change-signature | Modify |
| `tests/Unit/H2hResolveTest.php`, `tests/Feature/H2hTest.php` | Tests | Create |

**Conventions:**
- `person_key` = lowercased, diacritics-folded full name (same fold as `DrawControlPage`).
- Gender: `'V'` (vyras → male stock), `'M'` (moteris → female stock).
- Stock chosen by the person's stored gender, else derived from the match category.

---

## Task 1: `player_photos` table + model

**Files:**
- Create: `database/migrations/2026_06_20_000000_create_player_photos_table.php`
- Create: `app/Models/PlayerPhoto.php`
- Test: `tests/Feature/H2hTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\PlayerPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class H2hTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_photo_persists(): void
    {
        $p = PlayerPhoto::create([
            'tournament_external_id' => '10424',
            'person_key' => 'jonas petraitis',
            'name' => 'Jonas Petraitis',
            'gender' => 'V',
            'photo' => 'player-photos/x.gif',
        ]);

        $this->assertDatabaseHas('player_photos', ['person_key' => 'jonas petraitis', 'gender' => 'V']);
        $this->assertSame('Jonas Petraitis', $p->fresh()->name);
    }
}
```

- [ ] **Step 2: Run, verify fail** — `php artisan test --filter=test_player_photo_persists` → FAIL (no table/model).

- [ ] **Step 3: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_photos', function (Blueprint $table) {
            $table->id();
            $table->string('tournament_external_id')->index();
            $table->string('person_key');
            $table->string('name');
            $table->string('gender', 1)->default('V'); // V = male, M = female
            $table->string('photo')->nullable();
            $table->timestamps();
            $table->unique(['tournament_external_id', 'person_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_photos');
    }
};
```

- [ ] **Step 4: Model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerPhoto extends Model
{
    protected $fillable = ['tournament_external_id', 'person_key', 'name', 'gender', 'photo'];
}
```

- [ ] **Step 5: Run, verify pass.** Commit:

```bash
git add database/migrations/*_create_player_photos_table.php app/Models/PlayerPhoto.php tests/Feature/H2hTest.php
git commit -m "feat(h2h): player_photos table and model"
```

---

## Task 2: OverlayData — people, gender, photo lookup, resolveH2h (TDD)

**Files:**
- Modify: `app/Services/OverlayData.php`
- Test: `tests/Unit/H2hResolveTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Unit;

use App\Models\OverlaySnapshot;
use App\Models\PlayerPhoto;
use App\Services\OverlayData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class H2hResolveTest extends TestCase
{
    use RefreshDatabase;

    private function snapshot(): void
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'participants_by_category' => ['53636' => [
                ['id' => 'r1', 'name' => 'Jonas Petraitis / Antanas Kazlauskas'],
                ['id' => 'r2', 'name' => 'Garcia Lopez / Marius Šernius'],
            ]],
            'matches' => [[
                'id' => 99, 'date' => '2026-04-18', 'time' => '20:00', 'court' => 'Kortas 2',
                'category' => 'Vyrai A', 'round' => 'Pusfinalis', 'status' => 'pending',
                'in_progress' => false, 'score' => null,
                'team1' => ['Jonas Petraitis', 'Antanas Kazlauskas'],
                'team2' => ['Garcia Lopez', 'Marius Šernius'],
            ]],
        ]]);
    }

    public function test_participants_people_splits_pairs(): void
    {
        $this->snapshot();
        $people = app(OverlayData::class)->participantsPeople('10424');

        $this->assertContains('Jonas Petraitis', $people);
        $this->assertContains('Marius Šernius', $people);
        $this->assertCount(4, $people);
    }

    public function test_resolve_h2h_joins_photos_and_stock(): void
    {
        $this->snapshot();
        Storage::fake('public');
        PlayerPhoto::create([
            'tournament_external_id' => '10424', 'person_key' => 'jonas petraitis',
            'name' => 'Jonas Petraitis', 'gender' => 'V', 'photo' => 'player-photos/j.gif',
        ]);

        $h = app(OverlayData::class)->resolveH2h('10424', 99, []);

        $this->assertTrue($h['found']);
        $this->assertSame('Jonas Petraitis', $h['team1'][0]['name']);
        $this->assertStringContainsString('player-photos/j.gif', $h['team1'][0]['photo']);
        $this->assertFalse($h['team1'][0]['is_stock']);
        // No photo for the partner → male stock (category "Vyrai A").
        $this->assertTrue($h['team1'][1]['is_stock']);
        $this->assertStringContainsString('player-male', $h['team1'][1]['photo']);
        $this->assertSame('20:00', $h['center']['time']);
        $this->assertSame('Kortas 2', $h['center']['court']);
    }

    public function test_resolve_h2h_missing_match(): void
    {
        $this->snapshot();
        $this->assertFalse(app(OverlayData::class)->resolveH2h('10424', 12345, [])['found']);
    }
}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement in `OverlayData.php`**

```php
/** Lowercase + strip Lithuanian/Polish diacritics (stable person key). */
public function personKey(string $name): string
{
    $map = [
        'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
        'ł' => 'l', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z', 'ń' => 'n', 'ć' => 'c',
    ];

    return trim(strtr(mb_strtolower($name), $map));
}

private function genderFromCategory(?string $cat): string
{
    $c = mb_strtolower($cat ?? '');

    return (str_contains($c, 'moter') || str_contains($c, 'women') || str_contains($c, 'female')) ? 'M' : 'V';
}

/** Distinct individual people across a tournament's participant pairs. @return list<string> */
public function participantsPeople(string $tournamentId): array
{
    $out = [];
    foreach ($this->payload($tournamentId)['participants_by_category'] ?? [] as $teams) {
        foreach ($teams as $t) {
            foreach (explode(' / ', (string) ($t['name'] ?? '')) as $person) {
                $person = trim($person);
                if ($person !== '') {
                    $out[$this->personKey($person)] = $person;
                }
            }
        }
    }

    return array_values($out);
}

/** @return array{name:string,photo:string,is_stock:bool} */
public function photoFor(string $tournamentId, string $name, string $fallbackGender): array
{
    $row = \App\Models\PlayerPhoto::where('tournament_external_id', $tournamentId)
        ->where('person_key', $this->personKey($name))
        ->first();

    if ($row && $row->photo) {
        return ['name' => $name, 'photo' => Storage::url($row->photo), 'is_stock' => false];
    }

    $gender = $row->gender ?? $fallbackGender;
    $file = $gender === 'M' ? 'player-female.svg' : 'player-male.svg';

    return ['name' => $name, 'photo' => asset('img/h2h/' . $file), 'is_stock' => true];
}

/**
 * Resolve the chosen head-to-head match into two photo-bearing sides + centre.
 *
 * @param  array<string,mixed>  $window
 * @return array<string,mixed>
 */
public function resolveH2h(string $tournamentId, $matchId, array $window): array
{
    $matches = $this->payload($tournamentId)['matches'] ?? [];
    $m = collect($matches)->first(fn ($x) => (string) ($x['id'] ?? '') === (string) $matchId);

    if (! $m) {
        return ['found' => false];
    }

    $gender = $this->genderFromCategory($m['category'] ?? null);
    $side = fn ($players) => array_map(fn ($n) => $this->photoFor($tournamentId, $n, $gender), $players ?: []);

    return [
        'found'       => true,
        'team1'       => $side($m['team1'] ?? []),
        'team2'       => $side($m['team2'] ?? []),
        'category'    => $m['category'] ?? null,
        'center'      => [
            'time'        => $m['time'] ?? null,
            'date'        => $m['date'] ?? null,
            'score'       => $m['score'] ?? null,
            'court'       => $m['court'] ?? null,
            'round'       => $m['round'] ?? null,
            'in_progress' => ! empty($m['in_progress']),
        ],
        'show'        => $window['h2h_center'] ?? ['time', 'score', 'court'],
        'custom_text' => $window['h2h_text'] ?? 'VS',
        'animate'     => (bool) ($window['h2h_animate'] ?? true),
    ];
}
```

- [ ] **Step 4: Run, verify pass.** Commit:

```bash
git add app/Services/OverlayData.php tests/Unit/H2hResolveTest.php
git commit -m "feat(h2h): resolveH2h + people/photo/gender helpers"
```

---

## Task 3: Stock silhouettes (male / female)

**Files:**
- Create: `public/img/h2h/player-male.svg`, `public/img/h2h/player-female.svg`

No test (static assets). Simple flat cut-out silhouettes on transparent background, broadcast-neutral.

- [ ] **Step 1: Create `player-male.svg`** (a head+shoulders silhouette, `viewBox="0 0 300 420"`, accent-grey fill, transparent bg). Female variant differs in hair/shoulder shape. Keep them simple vector shapes.

- [ ] **Step 2: Commit**

```bash
git add public/img/h2h/
git commit -m "feat(h2h): male/female stock silhouettes"
```

---

## Task 4: Controller h2h branch + OverlayResource window fields

**Files:**
- Modify: `app/Http/Controllers/OverlayController.php`
- Modify: `app/Filament/Resources/OverlayResource.php`
- Test: `tests/Feature/H2hTest.php`

- [ ] **Step 1: Add failing feature test**

```php
public function test_data_returns_h2h(): void
{
    \App\Models\OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
        'matches' => [[
            'id' => 99, 'time' => '20:00', 'court' => 'Kortas 2', 'category' => 'Vyrai A',
            'round' => 'Pusfinalis', 'in_progress' => false, 'score' => null,
            'team1' => ['Jonas Petraitis', 'Antanas Kazlauskas'],
            'team2' => ['Garcia Lopez', 'Marius Šernius'],
        ]],
    ]]);

    $overlay = \App\Models\Overlay::create([
        'name' => 'H', 'type' => 'group_standings', 'tournament_external_id' => '10424',
        'windows' => [['id' => 'w1', 'type' => 'h2h', 'name' => 'Akistata',
            'h2h_center' => ['time', 'court'], 'h2h_text' => 'VS', 'h2h_animate' => true]],
        'state' => ['active_window_id' => 'w1', 'next_match' => '', 'h2h_match_id' => 99],
    ]);

    $this->getJson("/overlay/{$overlay->token}/data")
        ->assertOk()
        ->assertJson(['visible' => true, 'window_type' => 'h2h'])
        ->assertJsonPath('h2h.found', true)
        ->assertJsonPath('h2h.team1.0.name', 'Jonas Petraitis')
        ->assertJsonPath('h2h.center.court', 'Kortas 2');
}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3a: Controller branch** (`OverlayController::data()`, before the `else` groups branch)

```php
} elseif ($type === 'h2h') {
    $payload['h2h'] = $data->resolveH2h(
        (string) $overlay->tournament_external_id,
        $state['h2h_match_id'] ?? null,
        $window,
    );
```

- [ ] **Step 3b: OverlayResource window type + fields**

Add `'h2h' => 'Akistata'` to the window type Select options. Add these fields, gated `visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h')`:

```php
CheckboxList::make('h2h_center')->label('Ką rodyti centre')
    ->options(['time' => 'Rungtynių laikas', 'score' => 'Live rezultatas', 'court' => 'Kortas / etapas', 'vs' => 'VS / tekstas'])
    ->default(['time', 'score', 'court', 'vs'])->columns(2)
    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),
TextInput::make('h2h_text')->label('Centro tekstas (kai „VS / tekstas")')->default('VS')
    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),
Toggle::make('h2h_animate')->label('Lėta animacija (zoom link žiūrovo)')->default(true)
    ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),
```

- [ ] **Step 4: Run, verify pass.** Commit:

```bash
git add app/Http/Controllers/OverlayController.php app/Filament/Resources/OverlayResource.php tests/Feature/H2hTest.php
git commit -m "feat(h2h): data endpoint branch + window config fields"
```

---

## Task 5: PlayerPhoto Filament resource (upload + load participants)

**Files:**
- Create: `app/Filament/Resources/PlayerPhotoResource.php` + `Pages/` (List/Create/Edit)
- Test: `tests/Feature/H2hTest.php`

The resource manages one row per person. A header action on the list page upserts rows for all of a tournament's people (gender from category).

- [ ] **Step 1: Failing test for the upsert helper** (put the upsert logic in a static method so it's testable)

```php
public function test_load_people_upserts_rows_with_gender(): void
{
    \App\Models\OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
        'participants_by_category' => ['53636' => [['id' => 'r1', 'name' => 'Jonas Petraitis / Antanas Kazlauskas']]],
        'matches' => [],
    ]]);

    \App\Filament\Resources\PlayerPhotoResource::loadPeople('10424', 'V');

    $this->assertDatabaseHas('player_photos', ['person_key' => 'jonas petraitis', 'name' => 'Jonas Petraitis', 'gender' => 'V']);
    $this->assertSame(2, \App\Models\PlayerPhoto::where('tournament_external_id', '10424')->count());
}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement the resource.** Key points:
  - Model `PlayerPhoto`, nav group "Transliacijos", label "Žaidėjų nuotraukos".
  - **Form:** `tournament_external_id` (TextInput or hidden), `name` (disabled), `gender` Select `['V'=>'Vyras','M'=>'Moteris']`, `photo` FileUpload `->image()->acceptedFileTypes(['image/gif','image/png','image/webp'])->disk('public')->directory('player-photos')`.
  - **Table:** thumbnail (ImageColumn `photo`), `name`, `gender` badge, `tournament_external_id`; searchable by name.
  - **`loadPeople(string $tid, string $defaultGender = 'V')` static:** for each `app(OverlayData::class)->participantsPeople($tid)`, `PlayerPhoto::updateOrCreate(['tournament_external_id'=>$tid,'person_key'=>personKey($name)], ['name'=>$name, 'gender'=> (existing ?? genderFor)])` — don't overwrite an existing gender/photo. Derive default gender per person from the category the person appears in when possible; else `$defaultGender`.
  - **List page header action** "Užkrauti dalyvius": a form with a tournament id select (distinct `tournament_external_id` from overlays) → calls `loadPeople($tid)`, notifies count.

```php
/** Upsert a player_photos row for every person of a tournament (keep existing photo/gender). */
public static function loadPeople(string $tid, string $defaultGender = 'V'): int
{
    $data = app(\App\Services\OverlayData::class);
    $n = 0;
    foreach ($data->participantsPeople($tid) as $name) {
        \App\Models\PlayerPhoto::updateOrCreate(
            ['tournament_external_id' => $tid, 'person_key' => $data->personKey($name)],
            ['name' => $name], // gender/photo only set on first insert via the column defaults
        );
        $n++;
    }

    return $n;
}
```

- [ ] **Step 4: Run, verify pass** (the upsert test). Manual-verify the resource UI: load participants, upload a GIF, set gender.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/PlayerPhotoResource.php app/Filament/Resources/PlayerPhotoResource/ tests/Feature/H2hTest.php
git commit -m "feat(h2h): player photo resource + load-participants"
```

---

## Task 6: Akistata control page (pick fixture)

**Files:**
- Create: `app/Filament/Pages/H2hControlPage.php` + `resources/views/filament/pages/h2h-control.blade.php`
- Test: `tests/Feature/H2hTest.php`

Model on `DrawControlPage`. Pick overlay → h2h window → a fixture from the snapshot matches → sets `state['h2h_match_id']` and shows the window.

- [ ] **Step 1: Failing test**

```php
public function test_show_match_sets_state_and_active_window(): void
{
    \App\Models\OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
        'matches' => [['id' => 99, 'team1' => ['A B'], 'team2' => ['C D'], 'time' => '20:00']],
    ]]);
    $overlay = \App\Models\Overlay::create([
        'name' => 'H', 'type' => 'group_standings', 'tournament_external_id' => '10424',
        'windows' => [['id' => 'w1', 'type' => 'h2h', 'name' => 'Akistata']],
        'state' => ['active_window_id' => null, 'next_match' => ''],
    ]);

    \Livewire\Livewire::test(\App\Filament\Pages\H2hControlPage::class)
        ->set('overlayId', $overlay->id)->set('windowId', 'w1')
        ->call('showMatch', 99);

    $overlay->refresh();
    $this->assertSame(99, $overlay->state['h2h_match_id']);
    $this->assertSame('w1', $overlay->state['active_window_id']);
}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement `H2hControlPage`.** Properties `overlayId`, `windowId`, `search`. Methods:
  - `windowOptions()` — `type==='h2h'` windows of the overlay.
  - `matches()` — `OverlayData::resolveSchedule` is overkill; just read `payload['matches']` for the overlay's tournament, optionally filter by `search` (team names). Provide id, team1, team2, time, court, category.
  - `showMatch($id)` — `state['h2h_match_id'] = $id; state['active_window_id'] = $this->windowId;` save.
  - `stop()` — `state['active_window_id'] = null`.
  - View: overlay + window selects, search box, list of matches (team1 vs team2, time/court) each `wire:click="showMatch(id)"`, plus a Stop button. Reuse the `overlay-control` styling.

- [ ] **Step 4: Run, verify pass.** Commit:

```bash
git add app/Filament/Pages/H2hControlPage.php resources/views/filament/pages/h2h-control.blade.php tests/Feature/H2hTest.php
git commit -m "feat(h2h): Akistata control page (pick fixture)"
```

---

## Task 7: Renderer — h2h board, centre, slow zoom

**Files:**
- Modify: `resources/views/overlays/window.blade.php`
- Modify: `resources/views/overlays/base.blade.php`

No automated test (browser render); manual verify.

- [ ] **Step 1: change-signature + cleanup** (`base.blade.php`): add `h2: d.h2h` to the `sig` object. In the not-visible branch and the `window.blade` top cleanup, remove a `#ov-h2h` body host (mirrors `#ov-draw`/`#ov-spons`).

- [ ] **Step 2: h2h render branch** (`window.blade.php`, after the sponsors branch). Render onto a `<body>` host `#ov-h2h` (position:fixed). Layout:
  - `.h2h-stage` full screen, split background (left team colour → centre → right team colour).
  - Top plates: team1 name (left), category label (centre), team2 name (right).
  - Left side: team1 players — **front player larger, partner slightly behind/smaller**; `<img>` of `player.photo` (GIF animates itself); name caption under each. Right side mirrors team2 (facing in).
  - Centre column: big **VS** (or `custom_text` when `show` includes `vs`); below it the auto content — if `center.in_progress` and `show` includes `score` and `score` present → score; else if `show` includes `time` → `date`+`time`; plus a `court · round` line when `show` includes `court`.
  - If `!h2h.found` → a muted "Pasirink rungtynes" placeholder.
  - Photos use `object-fit: contain; object-position: bottom`.

- [ ] **Step 3: CSS** — broadcast sizes (names ~26–30px, VS ~64px, centre content ~30px, shadows over photos). Slow zoom: when `animate`, apply `animation: h2hZoom 22s ease-in-out infinite alternate` to the player images (`transform: scale(1) → scale(1.06)`), origin bottom. Keep it subtle.

- [ ] **Step 4: Manual verify** — see Task 8.

- [ ] **Step 5: Commit**

```bash
git add resources/views/overlays/window.blade.php resources/views/overlays/base.blade.php
git commit -m "feat(h2h): on-screen facing-teams board + centre + slow zoom"
```

---

## Task 8: E2E verify & docs

**Files:**
- Modify: `docs/overlays.md`

- [ ] **Step 1: Full suite** — `php artisan test` → new H2h tests green (plus existing).
- [ ] **Step 2: Live walk-through** — load a tournament; in "Žaidėjų nuotraukos" load participants, upload a GIF for one player, set genders; create an overlay with an "Akistata" window; in "Akistata" valdymas pick a fixture and Play; open `/overlay/{token}` and confirm the two teams face each other, the centre shows time (or live score when `in_progress`), missing photos fall back to male/female stock, and the slow zoom runs.
- [ ] **Step 3: Document** — add a "Akistata / Head to Head (`h2h`)" subsection to `docs/overlays.md` (window type, centre options, the photo library, the control page, name-based photo keying, stock fallback).
- [ ] **Step 4: Commit**

```bash
git add docs/overlays.md
git commit -m "docs: head-to-head overlay usage"
```

---

## Notes for the implementer

- **No `push.js` change** — player names already arrive per-person in `matches[].team1/team2` and in participant pair names; key photos by `personKey(name)`.
- **Gender** drives only the stock fallback; auto from category, editable per person.
- **GIF** photos animate on their own; the slow zoom is an additional subtle transform — keep it gentle (≥20s) so it doesn't distract.
- **Body host** for the h2h board (position:fixed), like the draw board and sponsors — `#stage` would otherwise clip it.
- **YAGNI:** no live websockets (1–3s poll is fine — score updates with the push cadence), no full stats panel.
