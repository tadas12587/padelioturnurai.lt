# Overlay Windows v2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn overlays into multi-window scenes with live Play/Stop, per-category group/bracket detection, configurable columns (place+medal, points, W/L, played), full color theming with 5 presets, and a rendered logo.

**Architecture:** An overlay holds a `windows` JSON array; `state.active_window_id` says which window is shown. The data endpoint resolves the active window's subgroups from the locally-stored snapshot (pushed by the bridge) and computes standings. One unified Blade template renders either a groups window or a bracket window, themed by `config.colors`, animating on window switch. The control page plays/stops windows instantly (no Save).

**Tech Stack:** Laravel 12, Filament 3.3, PHPUnit 11 (SQLite `:memory:`), Blade + vanilla JS, Node push script.

**Spec:** `docs/superpowers/specs/2026-06-16-overlay-windows-v2-design.md`

> Deployment reminder: NEVER run `php artisan config:cache`/`route:cache`/`view:cache` (chrooted host). Runtime `Cache` facade is fine. Deploy = git pull + `php artisan migrate --force` + `rm -f bootstrap/cache/*.php` + `php artisan optimize:clear`.

## File Structure

**Create:**
- `database/migrations/2026_06_16_000003_add_windows_to_overlays_table.php`
- `resources/views/overlays/window.blade.php` — unified renderer (groups + bracket)

**Modify:**
- `app/Models/Overlay.php` — `windows` cast, new defaults, `themePresets()`, default `type`
- `app/Services/OverlayData.php` — `points` in standings, `categoryStages()`, `resolveWindow()`
- `app/Http/Controllers/OverlayController.php` — `data()` windows model; `ingest()` stores `category_stages`; `show()` → unified template
- `resources/views/overlays/base.blade.php` — colors via CSS vars, logo, window-switch animation
- `app/Filament/Resources/OverlayResource.php` — windows repeater, theme/colors, columns
- `app/Filament/Pages/OverlayControlPage.php` + view — Play/Stop per window
- `tools/overlay-push/push.js` — fetch `draws`, build `category_stages`
- tests

**Delete (replaced by window.blade):**
- `resources/views/overlays/group_standings.blade.php`, `resources/views/overlays/bracket.blade.php` (in Task 6)

---

## Task 1: windows column + model defaults

**Files:**
- Create: `database/migrations/2026_06_16_000003_add_windows_to_overlays_table.php`
- Modify: `app/Models/Overlay.php`
- Test: `tests/Feature/OverlayEndpointTest.php` (update the defaults test)

- [ ] **Step 1: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overlays', function (Blueprint $table) {
            $table->json('windows')->nullable()->after('bracket_data');
        });
    }

    public function down(): void
    {
        Schema::table('overlays', function (Blueprint $table) {
            $table->dropColumn('windows');
        });
    }
};
```

- [ ] **Step 2: Update `app/Models/Overlay.php`** — add `windows` to `$fillable` and `$casts` (`'windows' => 'array'`), update the `booted` creating hook and defaults:

```php
    protected static function booted(): void
    {
        static::creating(function (Overlay $overlay) {
            if (empty($overlay->token)) {
                do {
                    $token = Str::lower(Str::random(8));
                } while (static::where('token', $token)->exists());
                $overlay->token = $token;
            }
            $overlay->type    ??= 'group_standings'; // legacy column, kept NOT NULL
            $overlay->config  ??= self::defaultConfig();
            $overlay->state   ??= self::defaultState();
            $overlay->windows ??= [];
        });
    }

    public static function defaultConfig(): array
    {
        return [
            'title'           => '',
            'theme'           => 'gold_night',
            'colors'          => self::themePresets()['gold_night']['colors'],
            'logo'            => null,
            'position'        => 'bottom-left',
            'visible_columns' => ['place', 'name', 'points', 'wins', 'losses'],
        ];
    }

    public static function defaultState(): array
    {
        return [
            'active_window_id' => null,
            'next_match'       => '',
        ];
    }

    /** @return array<string,array{label:string,colors:array<string,string>}> */
    public static function themePresets(): array
    {
        return [
            'gold_night'  => ['label' => 'Auksinė naktis', 'colors' => ['bg' => '#111118', 'text' => '#F5F5F0', 'accent' => '#C9A84C', 'muted' => '#9CA3AF']],
            'light'       => ['label' => 'Šviesi',         'colors' => ['bg' => '#FFFFFF', 'text' => '#111118', 'accent' => '#C9A84C', 'muted' => '#6B7280']],
            'court_blue'  => ['label' => 'Mėlyna (kortas)','colors' => ['bg' => '#0B1E3B', 'text' => '#F5F8FF', 'accent' => '#4FA3FF', 'muted' => '#7E93B8']],
            'court_green' => ['label' => 'Žalia (kortas)', 'colors' => ['bg' => '#0C2A1F', 'text' => '#F2FBF6', 'accent' => '#34D399', 'muted' => '#79A893']],
            'red_black'   => ['label' => 'Raudona/juoda',  'colors' => ['bg' => '#1A0D0D', 'text' => '#FBEDED', 'accent' => '#EF4444', 'muted' => '#A98686']],
        ];
    }
```

- [ ] **Step 3: Update the defaults test** in `tests/Feature/OverlayEndpointTest.php` (`test_creating_overlay_assigns_token_and_defaults`): replace the visible assertion with the new state shape:

```php
        $this->assertSame('#C9A84C', $overlay->config['colors']['accent']);
        $this->assertNull($overlay->state['active_window_id']);
        $this->assertSame([], $overlay->windows);
```

- [ ] **Step 4: Run**

Run: `php artisan test --filter=OverlayEndpointTest::test_creating_overlay_assigns_token_and_defaults`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_16_000003_add_windows_to_overlays_table.php app/Models/Overlay.php tests/Feature/OverlayEndpointTest.php
git commit -m "feat: add windows column and v2 overlay defaults"
```

---

## Task 2: OverlayData — points, category stages, window resolution

**Files:**
- Modify: `app/Services/OverlayData.php`
- Test: `tests/Unit/OverlayDataTest.php` (points), `tests/Feature/OverlayWindowTest.php` (resolveWindow)

- [ ] **Step 1: Add `points` to standings** — in `computeStandings`, add `'points' => $w` to each row array (next to `wins`).

- [ ] **Step 2: Add a unit assertion** in `tests/Unit/OverlayDataTest.php` (existing test): after the wins asserts add:

```php
        $this->assertSame(1, $rows[0]['points']);
        $this->assertSame(0, $rows[1]['points']);
```

- [ ] **Step 3: Add `categoryStages` and `resolveWindow`** to `OverlayData`:

```php
    /** @return array<string,mixed> */
    public function categoryStages(string $tournamentId): array
    {
        return $this->payload($tournamentId)['category_stages'] ?? [];
    }

    /**
     * Resolve a groups-window's selected subgroups from the snapshot.
     *
     * @param  array<string,mixed>  $window
     * @return array{groups:list<array<string,mixed>>,subgroup_count:int}
     */
    public function resolveWindow(string $tournamentId, array $window): array
    {
        $groups = [];

        foreach ($window['subgroups'] ?? [] as $sel) {
            $catId = $sel['category_id'] ?? null;
            if (! $catId) {
                continue;
            }

            $raw = $this->groups($tournamentId, (int) $catId);

            $groupId = $sel['group_id'] ?? null;
            if ($groupId) {
                $raw = array_values(array_filter($raw, fn ($g) => $g['id'] == $groupId));
            }

            foreach ($raw as $g) {
                $groups[] = [
                    'id'   => $g['id'],
                    'name' => $g['name'] ?? '',
                    'rows' => $this->computeStandings($g),
                ];
            }
        }

        return ['groups' => $groups, 'subgroup_count' => count($groups)];
    }
```

- [ ] **Step 4: Write the failing feature test** `tests/Feature/OverlayWindowTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\OverlaySnapshot;
use App\Services\OverlayData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverlayWindowTest extends TestCase
{
    use RefreshDatabase;

    private function seedSnapshot(): void
    {
        OverlaySnapshot::create([
            'tournament_external_id' => '10229',
            'payload' => [
                'title' => 'T',
                'categories' => [],
                'category_stages' => ['47817' => ['has_groups' => true, 'has_bracket' => false]],
                'groups_by_category' => [
                    '47817' => [
                        ['id' => 1, 'name' => 'A', 'entries' => [], 'matches' => []],
                        ['id' => 2, 'name' => 'B', 'entries' => [], 'matches' => []],
                    ],
                ],
            ],
        ]);
    }

    public function test_resolve_window_all_subgroups(): void
    {
        $this->seedSnapshot();
        $window = ['id' => 'w1', 'type' => 'groups', 'subgroups' => [['category_id' => 47817, 'group_id' => null]]];

        $res = (new OverlayData)->resolveWindow('10229', $window);

        $this->assertSame(2, $res['subgroup_count']);
    }

    public function test_resolve_window_single_subgroup(): void
    {
        $this->seedSnapshot();
        $window = ['id' => 'w1', 'type' => 'groups', 'subgroups' => [['category_id' => 47817, 'group_id' => 2]]];

        $res = (new OverlayData)->resolveWindow('10229', $window);

        $this->assertSame(1, $res['subgroup_count']);
        $this->assertSame('B', $res['groups'][0]['name']);
    }
}
```

- [ ] **Step 5: Run**

Run: `php artisan test --filter="OverlayDataTest|OverlayWindowTest"`
Expected: PASS (all)

- [ ] **Step 6: Commit**

```bash
git add app/Services/OverlayData.php tests/Unit/OverlayDataTest.php tests/Feature/OverlayWindowTest.php
git commit -m "feat: OverlayData points, category stages, window resolution"
```

---

## Task 3: data() endpoint — windows model

**Files:**
- Modify: `app/Http/Controllers/OverlayController.php`
- Test: `tests/Feature/OverlayEndpointTest.php`

- [ ] **Step 1: Rewrite `data()` and replace `groupPayload`/`bracketPayload`** in `OverlayController`:

```php
    public function data(Overlay $overlay, OverlayData $data): JsonResponse
    {
        $config = array_merge(Overlay::defaultConfig(), $overlay->config ?? []);
        $state  = array_merge(Overlay::defaultState(), $overlay->state ?? []);

        $logo = ! empty($config['logo']) ? \Illuminate\Support\Facades\Storage::url($config['logo']) : null;

        $payload = [
            'title'       => $config['title'],
            'colors'      => $config['colors'],
            'accent'      => $config['colors']['accent'] ?? '#C9A84C',
            'logo'        => $logo,
            'position'    => $config['position'],
            'columns'     => $config['visible_columns'],
            'next_match'  => $state['next_match'],
            'visible'     => false,
            'window_id'   => null,
            'window_type' => null,
            'stale'       => false,
        ];

        $activeId = $state['active_window_id'];
        if (! $activeId) {
            return response()->json($payload);
        }

        $window = collect($overlay->windows ?? [])->firstWhere('id', $activeId);
        if (! $window) {
            return response()->json($payload);
        }

        $payload['visible']     = true;
        $payload['window_id']   = $activeId;
        $payload['window_type'] = $window['type'] ?? 'groups';

        if (($window['type'] ?? 'groups') === 'bracket') {
            $rounds = $window['bracket_data']['rounds'] ?? [];
            $payload['rounds']    = $rounds;
            $payload['draw_size'] = isset($rounds[0]['matches']) ? count($rounds[0]['matches']) * 2 : 0;
        } else {
            $resolved = $data->resolveWindow((string) $overlay->tournament_external_id, $window);
            if (empty($resolved['groups'])) {
                $resolved['stale'] = true;
            }
            $payload = array_merge($payload, $resolved);
        }

        return response()->json($payload);
    }
```

(Delete the old `groupPayload` and `bracketPayload` private methods.)

- [ ] **Step 2: Replace the window-related tests** in `tests/Feature/OverlayEndpointTest.php`. Remove `test_data_endpoint_hidden_when_not_visible`, `test_data_endpoint_marks_stale_when_no_snapshot`, `test_data_endpoint_returns_groups_from_snapshot` and add:

```php
    public function test_data_hidden_when_no_active_window(): void
    {
        $overlay = Overlay::create(['name' => 'G', 'type' => 'group_standings']);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => false]);
    }

    public function test_data_hidden_when_active_window_missing(): void
    {
        $overlay = Overlay::create([
            'name' => 'G', 'type' => 'group_standings',
            'windows' => [],
            'state' => ['active_window_id' => 'ghost', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => false]);
    }

    public function test_data_returns_active_window_groups(): void
    {
        OverlaySnapshot::create([
            'tournament_external_id' => '10229',
            'payload' => [
                'groups_by_category' => [
                    '47817' => [['id' => 5, 'name' => 'A', 'entries' => [], 'matches' => []]],
                ],
            ],
        ]);

        $overlay = Overlay::create([
            'name' => 'G', 'type' => 'group_standings',
            'tournament_external_id' => '10229',
            'windows' => [[
                'id' => 'w1', 'type' => 'groups', 'name' => 'W1',
                'subgroups' => [['category_id' => 47817, 'group_id' => null]],
            ]],
            'state' => ['active_window_id' => 'w1', 'next_match' => 'Next'],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson([
                'visible' => true,
                'window_id' => 'w1',
                'window_type' => 'groups',
                'subgroup_count' => 1,
                'next_match' => 'Next',
            ]);
    }

    public function test_data_stale_when_active_window_has_no_snapshot(): void
    {
        $overlay = Overlay::create([
            'name' => 'G', 'type' => 'group_standings',
            'tournament_external_id' => '10229',
            'windows' => [[
                'id' => 'w1', 'type' => 'groups', 'name' => 'W1',
                'subgroups' => [['category_id' => 47817, 'group_id' => null]],
            ]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson(['visible' => true, 'groups' => [], 'stale' => true]);
    }
```

Add `use App\Models\OverlaySnapshot;` if not present.

- [ ] **Step 3: Run**

Run: `php artisan test --filter=OverlayEndpointTest`
Expected: PASS (all)

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/OverlayController.php tests/Feature/OverlayEndpointTest.php
git commit -m "feat: overlay data endpoint resolves active window"
```

---

## Task 4: ingest stores category_stages

**Files:**
- Modify: `app/Http/Controllers/OverlayController.php`
- Test: `tests/Feature/OverlayIngestTest.php`

- [ ] **Step 1: Add `category_stages`** to the `ingest()` validation and stored payload:
  - validation: add `'category_stages' => 'array'`
  - stored payload array: add `'category_stages' => $validated['category_stages'] ?? []`

- [ ] **Step 2: Extend a test** in `OverlayIngestTest::test_ingest_stores_snapshot_with_valid_token`: add `'category_stages' => ['47817' => ['has_groups' => true, 'has_bracket' => false]]` to `$payload`, then assert:

```php
        $this->assertTrue($snapshot->payload['category_stages']['47817']['has_groups']);
```

- [ ] **Step 3: Run**

Run: `php artisan test --filter=OverlayIngestTest`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/OverlayController.php tests/Feature/OverlayIngestTest.php
git commit -m "feat: ingest stores per-category stage detection"
```

---

## Task 5: push script — detect groups vs bracket

**Files:**
- Modify: `tools/overlay-push/push.js`

- [ ] **Step 1: Add a `fetchDraws` helper** (after `fetchGroups`):

```js
async function fetchDraws(categoryId) {
  // `draws` returns raw JSON objects; presence indicates an elimination/main draw.
  const data = await gql(`{ draws(filter: { tournamentCategory: ${categoryId} }) }`);
  return data.draws || [];
}
```

- [ ] **Step 2: Build `category_stages` in `pushOnce`** — alongside `groupsByCategory`:

```js
  const categoryStages = {};
  for (const cat of categories) {
    const groups = groupsByCategory[String(cat.id)] || [];
    let draws = [];
    try { draws = await fetchDraws(cat.id); } catch (_) { draws = []; }
    categoryStages[String(cat.id)] = {
      has_groups: groups.length > 0,
      has_bracket: draws.length > 0,
      draw_type: draws[0]?.type ?? null,
      draw_size: draws[0]?.size ?? null,
    };
  }
```

Then include `category_stages: categoryStages` in the `snapshot` object sent to `/overlay/ingest`.

- [ ] **Step 3: Syntax check**

Run: `node --check tools/overlay-push/push.js`
Expected: no output (valid)

- [ ] **Step 4: Commit**

```bash
git add tools/overlay-push/push.js
git commit -m "feat: push script detects groups vs bracket per category"
```

---

## Task 6: unified overlay template (colors, logo, medal, points, bracket, switch animation)

**Files:**
- Modify: `resources/views/overlays/base.blade.php`
- Create: `resources/views/overlays/window.blade.php`
- Delete: `resources/views/overlays/group_standings.blade.php`, `resources/views/overlays/bracket.blade.php`
- Modify: `app/Http/Controllers/OverlayController.php` (`show()` → `overlays.window`)

- [ ] **Step 1: Update `show()`** to always use the unified template:

```php
    public function show(Overlay $overlay)
    {
        return view('overlays.window', ['overlay' => $overlay]);
    }
```

- [ ] **Step 2: Update `base.blade.php`** — drive colors from the payload and replay the
  entrance animation when `window_id` changes (not only on hide→show). Replace the `<style>`
  hardcoded panel colors with CSS variables set at runtime, and update the polling JS:

  - In `:root`-level CSS, use `var(--ov-bg)`, `var(--ov-text)`, `var(--ov-accent)`,
    `var(--ov-muted)` for panel background, text, accent, muted lines.
  - In `tick()`, after parsing `d`: set the CSS vars from `d.colors`
    (`document.documentElement.style.setProperty('--ov-bg', d.colors.bg)` etc.).
  - Track `let currentWindow = null;`. Logic:
    ```js
    if (!d.visible) { if (shown){ stage.classList.remove('in'); shown=false; currentWindow=null; } return; }
    setColors(d.colors);
    root.className = 'pos-' + (d.position || 'bottom-left');
    if (!shown) { render(d); playIntro(); shown = true; currentWindow = d.window_id; }
    else if (d.window_id !== currentWindow) {            // switch: animate out, then in
        stage.classList.remove('in');
        currentWindow = d.window_id;
        setTimeout(() => { render(d); playIntro(); }, 420);
    } else { render(d); }                                // same window: refresh data only
    ```

- [ ] **Step 3: Create `resources/views/overlays/window.blade.php`** extending base, whose
  `render_fn_body` branches on `d.window_type`. It must:
  - Set CSS vars are already set in base; here just build markup into `stage.innerHTML`.
  - **groups** branch: adaptive grid (cols-1/2/4 from `d.subgroup_count`), card per group with
    the group name header; table columns from `d.columns` (`place`,`name`,`points`,`wins`,
    `losses`,`played`) with Lithuanian labels (`#`,`Pora`,`Taškai`,`W`,`L`,`Ž`); render a medal
    (🥇🥈🥉) before place for rows 1–3; optional logo `<img src="${d.logo}">` in the header
    when `d.logo`; lower bar for `d.next_match`.
  - **bracket** branch: the rounds markup from the old bracket template (teams, win highlight).
  - Use the color CSS vars for borders/accents (`var(--ov-accent)`, `var(--ov-bg)`, etc.).
  - Port styles from the old `group_standings.blade.php` and `bracket.blade.php` `@section('styles')`
    into this file's `@section('styles')`, swapping hardcoded hex for the CSS vars.

- [ ] **Step 4: Delete old templates**

```bash
git rm resources/views/overlays/group_standings.blade.php resources/views/overlays/bracket.blade.php
```

- [ ] **Step 5: Manual render check**

```
php artisan view:clear
php artisan tinker --execute="\$o=App\Models\Overlay::firstOrCreate(['name'=>'wv2','type'=>'group_standings']); echo app('Illuminate\Contracts\Http\Kernel')->handle(Illuminate\Http\Request::create('/overlay/'.\$o->token,'GET'))->status();"
```
Expected: `200`. (FORBIDDEN: `view:cache`.)

- [ ] **Step 6: Commit**

```bash
git add resources/views/overlays/ app/Http/Controllers/OverlayController.php
git commit -m "feat: unified themed overlay template with windows, medals, points, logo"
```

---

## Task 7: OverlayResource form — windows, theme, colors, columns

**Files:**
- Modify: `app/Filament/Resources/OverlayResource.php`

UI task — verify suite stays green + `php -l`. Match Filament 3.3 patterns already in this file.

- [ ] **Step 1: Remove the top-level `type` select and old `config` appearance fields**; keep
  `name`, `tournament_external_id`. (The `type` column is defaulted in the model now.)

- [ ] **Step 2: Add an Appearance section** with:
  - `Select::make('config.theme')` options from `Overlay::themePresets()` mapped to labels,
    `->live()`, with `afterStateUpdated(fn ($state, Forms\Set $set) => $set('config.colors', Overlay::themePresets()[$state]['colors'] ?? []))` to prefill colors.
  - `ColorPicker::make('config.colors.bg')` / `.text` / `.accent` / `.muted` (labels: Fonas,
    Tekstas, Akcentas, Antrinė).
  - `FileUpload::make('config.logo')->image()->directory('overlay-logos')`.
  - `Select::make('config.position')` (bottom-left/bottom-right/top-left/center).
  - `CheckboxList::make('config.visible_columns')` options `place`=>Vieta, `name`=>Pora,
    `points`=>Taškai, `wins`=>Laimėta, `losses`=>Pralaimėta, `played`=>Sužaista.

- [ ] **Step 3: Add a `Repeater::make('windows')`** (`Filament\Forms\Components\Repeater`):
  - `TextInput::make('id')->default(fn () => 'w' . \Illuminate\Support\Str::random(6))->required()` (hidden-ish; keep simple, can be a visible read-only or use `->hidden()` with default via `mutateRelationshipDataBeforeCreateUsing` — simplest: a `Hidden::make('id')->default(...)`).
  - `TextInput::make('name')->label('Lango pavadinimas')->required()`.
  - `Select::make('type')->options(['groups' => 'Grupės', 'bracket' => 'Brackets'])->default('groups')->live()`.
  - groups: nested `Repeater::make('subgroups')` visible when type=groups, each with
    `Select::make('category_id')` (options from `app(OverlayData::class)->categories($tournamentId)` annotated with stage from `categoryStages`, e.g. "Vyrai 35+ (grupės)") `->live()`, and
    `Select::make('group_id')` (options from `app(OverlayData::class)->groups($tournamentId,$categoryId)` + `'' => 'Visi pogrupiai'`). Use the form's `tournament_external_id` via `Forms\Get`.
  - bracket: `Textarea::make('bracket_data')` (JSON, formatStateUsing/dehydrateStateUsing like the old resource) visible when type=bracket.
  - `->collapsible()->itemLabel(fn ($state) => $state['name'] ?? 'Langas')`.

- [ ] **Step 4: Keep the table** (name, type badge, token, copy URL) as-is.

- [ ] **Step 5: Verify**

Run: `php -l app/Filament/Resources/OverlayResource.php` then `php artisan test` (suite green apart from the pre-existing ExampleTest). Manually open `/admin/overlays`, create an overlay, add a window with a subgroup, pick a theme, save.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/OverlayResource.php
git commit -m "feat: overlay resource windows repeater, themes, colors, columns"
```

---

## Task 8: control page — Play/Stop per window

**Files:**
- Modify: `app/Filament/Pages/OverlayControlPage.php` + `resources/views/filament/pages/overlay-control.blade.php`

UI task — verify suite green + manual.

- [ ] **Step 1: Rewrite the page** to list the selected overlay's windows with Play/Stop:

```php
    public ?int $overlayId = null;

    public function selectedOverlay(): ?Overlay
    {
        return $this->overlayId ? Overlay::find($this->overlayId) : null;
    }

    /** @return array<int,string> */
    public function overlayOptions(): array
    {
        return Overlay::orderBy('name')->pluck('name', 'id')->all();
    }

    public function play(string $windowId): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $state['active_window_id'] = $windowId;
        $overlay->state = $state;
        $overlay->save();
        Notification::make()->title('▶ Rodoma')->success()->send();
    }

    public function stop(): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $state = array_merge(Overlay::defaultState(), $overlay->state ?? []);
        $state['active_window_id'] = null;
        $overlay->state = $state;
        $overlay->save();
        Notification::make()->title('■ Sustabdyta')->send();
    }

    public function activeWindowId(): ?string
    {
        return $this->selectedOverlay()?->state['active_window_id'] ?? null;
    }
```

- [ ] **Step 2: Rewrite the view** — overlay `<select wire:model.live="overlayId">`, then when
  selected: show the OBS URL, and list `$overlay->windows` as rows. Each row shows the window
  name and two buttons: `<x-filament::button wire:click="play('{{ $w['id'] }}')" :color="..." icon="heroicon-o-play">Play</x-filament::button>` and a single
  `<x-filament::button wire:click="stop" color="gray" icon="heroicon-o-stop">Stop</x-filament::button>`.
  Highlight the row whose `id === $this->activeWindowId()` (e.g. a "● gyvai" badge). Keep an
  optional `next_match` input + an `apply` (or fold next_match into a small instant save). No
  form Save button.

- [ ] **Step 3: Verify**

`php -l` the page; `php artisan test` (green apart from ExampleTest). Manually: pick overlay,
click Play on a window → open the overlay URL in a browser → confirm it appears within ~3s;
click another window's Play → confirm swap animation; Stop → confirm it animates out.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Pages/OverlayControlPage.php resources/views/filament/pages/overlay-control.blade.php
git commit -m "feat: control page play/stop per window"
```

---

## Done criteria

- `php artisan test` green (apart from the unrelated stock `ExampleTest`).
- An overlay can hold multiple windows; each group-window freely selects subgroups across categories.
- Control page Play/Stop switches windows live with animation; no Save needed.
- Snapshot/push records per-category group vs bracket; window creation reflects it.
- Standings show configurable columns incl. points and medals; logo renders; themes + custom colors work.

## Manual OBS verification (Tadas)

1. Deploy (git pull + migrate --force + clear caches). Run `node push.js` locally.
2. Build an overlay with 2 windows (different subgroup sets), pick a theme, add a logo.
3. OBS Browser Source = overlay URL. On the control page Play window 1 → appears; Play window 2 → swaps; Stop → leaves.
4. Try 1/2/4 subgroups in a window → grid adapts. Switch themes → colors change.
