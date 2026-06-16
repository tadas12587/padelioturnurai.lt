# Overlay v3 Implementation Plan — Sponsors, Scrim, Themes

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a sponsors overlay window type (corner / bottom-bar / full-screen variants, sourced from existing `Sponsor` records and/or bulk-uploaded images), a per-window themed background scrim with adjustable opacity, and expand the color theme presets to ~11.

**Architecture:** Sponsors are a new `windows[]` entry `type: 'sponsors'` resolved server-side into an `items` list and rendered/rotated client-side. The shell gains a full-viewport `#scrim` layer driven by per-window `scrim` settings, plus signature-based re-render so client rotation timers survive the 3 s poll. Themes are static presets on the `Overlay` model.

**Tech Stack:** Laravel 12, Filament 3.3, PHPUnit 11 (SQLite `:memory:`), Blade + vanilla JS.

**Spec:** `docs/superpowers/specs/2026-06-16-overlay-sponsors-scrim-themes-v3-design.md`

> Deploy reminder: NEVER run artisan config:cache/route:cache/view:cache (chrooted host). No new migrations in this plan (windows JSON is schema-free).

## File Structure

**Modify:**
- `app/Models/Overlay.php` — expand `themePresets()`
- `app/Services/OverlayData.php` — add `resolveSponsors()`
- `app/Http/Controllers/OverlayController.php` — `data()` handles sponsors + adds `scrim`
- `resources/views/overlays/base.blade.php` — `#scrim` layer + signature re-render
- `resources/views/overlays/window.blade.php` — sponsors render (3 variants) + rotation + styles
- `app/Filament/Resources/OverlayResource.php` — window repeater: sponsors fields + scrim fields + `sponsors` type
- tests

---

## Task 1: Expand theme presets

**Files:**
- Modify: `app/Models/Overlay.php`
- Test: `tests/Unit/OverlayThemeTest.php`

- [ ] **Step 1: Write the failing test** `tests/Unit/OverlayThemeTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Overlay;
use PHPUnit\Framework\TestCase;

class OverlayThemeTest extends TestCase
{
    public function test_presets_include_new_themes_with_full_color_sets(): void
    {
        $presets = Overlay::themePresets();

        $this->assertGreaterThanOrEqual(11, count($presets));
        foreach (['gold_night', 'midnight', 'graphite', 'wine_gold', 'esports', 'orange', 'ice'] as $key) {
            $this->assertArrayHasKey($key, $presets);
            foreach (['bg', 'text', 'accent', 'muted'] as $slot) {
                $this->assertArrayHasKey($slot, $presets[$key]['colors']);
            }
        }
    }
}
```

- [ ] **Step 2: Run it, verify it FAILS**

Run: `php artisan test --filter=OverlayThemeTest`
Expected: FAIL (new keys missing)

- [ ] **Step 3: Replace `themePresets()`** in `app/Models/Overlay.php` with:

```php
    /** @return array<string,array{label:string,colors:array<string,string>}> */
    public static function themePresets(): array
    {
        return [
            'gold_night'  => ['label' => 'Auksinė naktis',       'colors' => ['bg' => '#111118', 'text' => '#F5F5F0', 'accent' => '#C9A84C', 'muted' => '#9CA3AF']],
            'light'       => ['label' => 'Šviesi',               'colors' => ['bg' => '#FFFFFF', 'text' => '#111118', 'accent' => '#C9A84C', 'muted' => '#6B7280']],
            'court_blue'  => ['label' => 'Mėlyna (kortas)',      'colors' => ['bg' => '#0B1E3B', 'text' => '#F5F8FF', 'accent' => '#4FA3FF', 'muted' => '#7E93B8']],
            'court_green' => ['label' => 'Žalia (kortas)',       'colors' => ['bg' => '#0C2A1F', 'text' => '#F2FBF6', 'accent' => '#34D399', 'muted' => '#79A893']],
            'red_black'   => ['label' => 'Raudona/juoda',        'colors' => ['bg' => '#1A0D0D', 'text' => '#FBEDED', 'accent' => '#EF4444', 'muted' => '#A98686']],
            'midnight'    => ['label' => 'Naktinė mėlyna',       'colors' => ['bg' => '#0A1A2F', 'text' => '#EAF2FF', 'accent' => '#38BDF8', 'muted' => '#6E8CB0']],
            'graphite'    => ['label' => 'Grafitas',             'colors' => ['bg' => '#17181B', 'text' => '#F4F4F5', 'accent' => '#D4D4D8', 'muted' => '#8A8D93']],
            'wine_gold'   => ['label' => 'Vynas ir auksas',      'colors' => ['bg' => '#2A0E16', 'text' => '#FBEEF1', 'accent' => '#D4AF37', 'muted' => '#A77E86']],
            'esports'     => ['label' => 'Elektrinė violetinė',  'colors' => ['bg' => '#150F2B', 'text' => '#F1ECFF', 'accent' => '#8B5CF6', 'muted' => '#8A7CB8']],
            'orange'      => ['label' => 'Oranžinė energija',    'colors' => ['bg' => '#14110D', 'text' => '#FFF3E6', 'accent' => '#FB923C', 'muted' => '#B0937A']],
            'ice'         => ['label' => 'Ledo mėlyna (šviesi)', 'colors' => ['bg' => '#F4F8FC', 'text' => '#0B2238', 'accent' => '#2563EB', 'muted' => '#5B7290']],
        ];
    }
```

- [ ] **Step 4: Run it, verify it PASSES**

Run: `php artisan test --filter=OverlayThemeTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Overlay.php tests/Unit/OverlayThemeTest.php
git commit -m "feat: expand overlay theme presets to 11 broadcast palettes"
```

---

## Task 2: OverlayData::resolveSponsors

**Files:**
- Modify: `app/Services/OverlayData.php`
- Test: `tests/Feature/OverlaySponsorTest.php`

- [ ] **Step 1: Write the failing test** `tests/Feature/OverlaySponsorTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Sponsor;
use App\Services\OverlayData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverlaySponsorTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_sponsors_uses_selected_order_excludes_inactive_then_images(): void
    {
        $a = Sponsor::create(['name' => 'A', 'logo' => 'sponsors/a.png', 'url' => 'https://a.lt', 'category' => 'gold', 'is_active' => true]);
        $b = Sponsor::create(['name' => 'B', 'logo' => 'sponsors/b.png', 'category' => 'gold', 'is_active' => true]);
        $off = Sponsor::create(['name' => 'Off', 'logo' => 'sponsors/off.png', 'category' => 'gold', 'is_active' => false]);

        $window = [
            'type' => 'sponsors',
            'sponsor_ids' => [$b->id, $a->id, $off->id],
            'images' => ['overlay-sponsors/x.jpg'],
        ];

        $items = (new OverlayData)->resolveSponsors($window);

        $this->assertCount(3, $items);               // B, A (off excluded), + 1 image
        $this->assertSame('B', $items[0]['name']);
        $this->assertSame('A', $items[1]['name']);
        $this->assertSame('https://a.lt', $items[1]['url']);
        $this->assertNull($items[2]['name']);
        $this->assertStringContainsString('x.jpg', $items[2]['logo']);
    }
}
```

- [ ] **Step 2: Run it, verify it FAILS**

Run: `php artisan test --filter=OverlaySponsorTest`
Expected: FAIL (`resolveSponsors` missing)

- [ ] **Step 3: Add `resolveSponsors`** to `OverlayData` (add `use App\Models\Sponsor;` and `use Illuminate\Support\Facades\Storage;` at the top if missing):

```php
    /**
     * Build the ordered sponsor items for a sponsors window: selected active
     * sponsors first (in the chosen order), then uploaded images.
     *
     * @param  array<string,mixed>  $window
     * @return list<array{logo:string,name:?string,url:?string}>
     */
    public function resolveSponsors(array $window): array
    {
        $items = [];

        $ids = $window['sponsor_ids'] ?? [];
        if (! empty($ids)) {
            $sponsors = Sponsor::whereIn('id', $ids)->where('is_active', true)->get()->keyBy('id');
            foreach ($ids as $id) {
                $s = $sponsors->get($id);
                if ($s) {
                    $items[] = ['logo' => Storage::url($s->logo), 'name' => $s->name, 'url' => $s->url];
                }
            }
        }

        foreach ($window['images'] ?? [] as $path) {
            $items[] = ['logo' => Storage::url($path), 'name' => null, 'url' => null];
        }

        return $items;
    }
```

- [ ] **Step 4: Run it, verify it PASSES**

Run: `php artisan test --filter=OverlaySponsorTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/OverlayData.php tests/Feature/OverlaySponsorTest.php
git commit -m "feat: resolve sponsor window items from records and uploads"
```

---

## Task 3: data() endpoint — sponsors window + scrim

**Files:**
- Modify: `app/Http/Controllers/OverlayController.php`
- Test: `tests/Feature/OverlayEndpointTest.php`

- [ ] **Step 1: Update `data()`** — after the line `$payload['window_type'] = $window['type'] ?? 'groups';`, add the scrim payload (applies to every visible window):

```php
        $payload['scrim'] = [
            'enabled' => (bool) ($window['scrim_enabled'] ?? false),
            'opacity' => (int) ($window['scrim_opacity'] ?? 55),
        ];
```

Then change the type branch from the current `if bracket / else groups` to include sponsors:

```php
        $type = $window['type'] ?? 'groups';

        if ($type === 'bracket') {
            $rounds = $window['bracket_data']['rounds'] ?? [];
            $payload['rounds']    = $rounds;
            $payload['draw_size'] = isset($rounds[0]['matches']) ? count($rounds[0]['matches']) * 2 : 0;
        } elseif ($type === 'sponsors') {
            $payload['variant']        = $window['variant'] ?? 'corner';
            $payload['rotate_seconds'] = (int) ($window['rotate_seconds'] ?? 6);
            $payload['items']          = $data->resolveSponsors($window);
        } else {
            $resolved = $data->resolveWindow((string) $overlay->tournament_external_id, $window);
            if (empty($resolved['groups'])) {
                $resolved['stale'] = true;
            }
            $payload = array_merge($payload, $resolved);
        }
```

- [ ] **Step 2: Add feature tests** to `tests/Feature/OverlayEndpointTest.php`:

```php
    public function test_data_returns_sponsors_window(): void
    {
        $sponsor = \App\Models\Sponsor::create(['name' => 'A', 'logo' => 'sponsors/a.png', 'url' => 'https://a.lt', 'category' => 'gold', 'is_active' => true]);

        $overlay = Overlay::create([
            'name' => 'S', 'type' => 'group_standings',
            'windows' => [[
                'id' => 'w1', 'type' => 'sponsors', 'name' => 'Rėmėjai',
                'variant' => 'bar', 'rotate_seconds' => 8,
                'sponsor_ids' => [$sponsor->id], 'images' => [],
                'scrim_enabled' => true, 'scrim_opacity' => 40,
            ]],
            'state' => ['active_window_id' => 'w1', 'next_match' => ''],
        ]);

        $this->getJson("/overlay/{$overlay->token}/data")
            ->assertOk()
            ->assertJson([
                'visible' => true,
                'window_type' => 'sponsors',
                'variant' => 'bar',
                'rotate_seconds' => 8,
                'scrim' => ['enabled' => true, 'opacity' => 40],
            ])
            ->assertJsonPath('items.0.name', 'A');
    }
```

- [ ] **Step 3: Run**

Run: `php artisan test --filter=OverlayEndpointTest`
Expected: PASS (all)

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/OverlayController.php tests/Feature/OverlayEndpointTest.php
git commit -m "feat: overlay data endpoint serves sponsors window and scrim"
```

---

## Task 4: shell — scrim layer + signature re-render

**Files:**
- Modify: `resources/views/overlays/base.blade.php`

Visual/behavioral — verified by render check + manual OBS later.

- [ ] **Step 1: Add the `#scrim` element** — change the body's root markup from
  `<div id="root" class="pos-bottom-left"><div id="stage"></div></div>` to:

```blade
    <div id="scrim"></div>
    <div id="root" class="pos-bottom-left"><div id="stage"></div></div>
```

- [ ] **Step 2: Add scrim CSS** (in the `<style>`, near `#root`):

```css
        #scrim { position: fixed; inset: 0; opacity: 0; pointer-events: none;
            transition: opacity .5s cubic-bezier(.16,1,.3,1); z-index: -1; }
```

- [ ] **Step 3: Update the polling JS** — add the scrim element handle, an `applyScrim`
  helper, a `lastSig` variable, and signature-based re-render. Replace the `tick()` body so it reads:

```js
        const scrim = document.getElementById('scrim');
        let shown = false, introTimer = null, currentWindow = null, lastSig = null;

        function setColors(c) { /* unchanged */ }

        function applyScrim(d) {
            const s = d.scrim || {};
            if (s.enabled && s.opacity > 0) {
                scrim.style.background = (d.colors && d.colors.bg) || '#111118';
                scrim.style.opacity = Math.min(1, s.opacity / 100);
            } else {
                scrim.style.opacity = 0;
            }
        }

        function render(d) { @yield('render_fn_body') }

        function playIntro() { /* unchanged */ }

        async function tick() {
            try {
                const res = await fetch(DATA_URL, { cache: 'no-store' });
                const d = await res.json();

                if (!d.visible) {
                    if (shown) { stage.classList.remove('in'); shown = false; currentWindow = null; }
                    scrim.style.opacity = 0;
                    return;
                }

                setColors(d.colors);
                applyScrim(d);
                root.className = 'pos-' + (d.position || 'bottom-left');

                const sig = JSON.stringify({ w: d.window_id, g: d.groups, r: d.rounds,
                    it: d.items, nm: d.next_match, v: d.variant, tt: d.tournament_title,
                    ti: d.title, lg: d.logo, c: d.columns });

                if (!shown) {
                    render(d); playIntro(); shown = true; currentWindow = d.window_id; lastSig = sig;
                } else if (d.window_id !== currentWindow) {
                    stage.classList.remove('in'); currentWindow = d.window_id; lastSig = sig;
                    setTimeout(() => { render(d); playIntro(); }, 420);
                } else if (sig !== lastSig) {
                    lastSig = sig; render(d);
                }
            } catch (e) { /* keep last good frame */ }
        }
        tick();
        setInterval(tick, POLL_MS);
```

Keep `setColors` and `playIntro` exactly as they are. The key change: render only on first show, window switch, or content-signature change — so sponsor rotation timers (Task 5) keep running between polls.

- [ ] **Step 4: Render check**

```
php artisan view:clear
php artisan tinker --execute="\$o=App\Models\Overlay::firstOrCreate(['name'=>'wv2','type'=>'group_standings']); echo app('Illuminate\Contracts\Http\Kernel')->handle(Illuminate\Http\Request::create('/overlay/'.\$o->token,'GET'))->status();"
```
Expected: `200`.

- [ ] **Step 5: Commit**

```bash
git add resources/views/overlays/base.blade.php
git commit -m "feat: overlay scrim layer and signature-based re-render"
```

---

## Task 5: window template — sponsors variants + rotation

**Files:**
- Modify: `resources/views/overlays/window.blade.php`

- [ ] **Step 1: Add sponsors styles** to `@section('styles')`:

```css
    .spons { --pad: 16px; }
    .sp-item { opacity: 0; transition: opacity .6s ease, transform .6s cubic-bezier(.16,1,.3,1); }
    .sp-item.show { opacity: 1; }
    /* corner */
    .spons.corner { position: relative; width: 240px; height: 120px; background: var(--ov-bg);
        border: 1px solid rgba(127,127,127,.28); border-top: 3px solid var(--ov-accent); border-radius: 8px;
        box-shadow: 0 20px 45px -20px rgba(0,0,0,.75); }
    .spons.corner .sp-item { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding: 18px; transform: scale(.96); }
    .spons.corner .sp-item.show { transform: none; }
    .spons.corner img { max-width: 80%; max-height: 70%; object-fit: contain; }
    /* bar */
    .spons.bar { position: fixed; left: 0; right: 0; bottom: 0; height: 92px; background: var(--ov-bg);
        border-top: 3px solid var(--ov-accent); box-shadow: 0 -10px 30px -12px rgba(0,0,0,.6); }
    .spons.bar .sp-item { position: absolute; inset: 0; display: flex; align-items: center; gap: 22px; padding: 0 48px; transform: translateY(12px); }
    .spons.bar .sp-item.show { transform: none; }
    .spons.bar img { height: 60px; width: auto; object-fit: contain; }
    .spons.bar .meta { display: flex; flex-direction: column; }
    .spons.bar .nm { font-family: 'Oswald',sans-serif; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; font-size: 22px; color: var(--ov-text); }
    .spons.bar .url { font-family: 'Barlow',sans-serif; font-size: 16px; color: var(--ov-accent); }
    /* fullscreen */
    .spons.full { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center;
        background: radial-gradient(120% 120% at 50% 30%, var(--ov-bg), #000); }
    .spons.full .sp-item { position: absolute; display: flex; flex-direction: column; align-items: center; gap: 28px; transform: scale(.92); }
    .spons.full .sp-item.show { transform: none; }
    .spons.full img { max-width: 56vw; max-height: 52vh; object-fit: contain; filter: drop-shadow(0 12px 40px rgba(0,0,0,.5)); }
    .spons.full .nm { font-family: 'Oswald',sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; font-size: 34px; color: var(--ov-text); }
```

- [ ] **Step 2: Add the sponsors branch** at the very start of `@section('render_fn_body')`,
  BEFORE the bracket branch (so it returns early). It must clear any previous rotation timer
  via a window-scoped handle:

```js
    if ((d.window_type || 'groups') === 'sponsors') {
        clearInterval(window.__spTimer);
        const items = d.items || [];
        if (!items.length) { stage.innerHTML = ''; return; }
        const variant = d.variant || 'corner';

        const itemHtml = (it, i) => {
            if (variant === 'bar') {
                const meta = (it.name || it.url)
                    ? `<div class="meta">${it.name ? `<span class="nm">${it.name}</span>` : ''}${it.url ? `<span class="url">${it.url}</span>` : ''}</div>`
                    : '';
                return `<div class="sp-item${i === 0 ? ' show' : ''}"><img src="${it.logo}" alt="">${meta}</div>`;
            }
            if (variant === 'fullscreen') {
                return `<div class="sp-item${i === 0 ? ' show' : ''}"><img src="${it.logo}" alt="">${it.name ? `<span class="nm">${it.name}</span>` : ''}</div>`;
            }
            return `<div class="sp-item${i === 0 ? ' show' : ''}"><img src="${it.logo}" alt=""></div>`;
        };

        const cls = variant === 'bar' ? 'bar' : (variant === 'fullscreen' ? 'full' : 'corner');
        stage.innerHTML = `<div class="spons ${cls}">${items.map(itemHtml).join('')}</div>`;

        const els = stage.querySelectorAll('.sp-item');
        if (els.length > 1) {
            let i = 0;
            window.__spTimer = setInterval(() => {
                els[i].classList.remove('show');
                i = (i + 1) % els.length;
                els[i].classList.add('show');
            }, (d.rotate_seconds || 6) * 1000);
        }
        return;
    }
```

Also, at the top of `render_fn_body`, the `headerHtml` line runs for groups/bracket; the
sponsors branch returns before using it — fine. Ensure the sponsors branch is placed BEFORE
the `const bigTitle = ...` header code OR that the header code does not error for sponsors
(it doesn't — it just builds an unused string). Place the sponsors branch first to be safe.

- [ ] **Step 3: Render check** (same tinker command as Task 4) → `200`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/overlays/window.blade.php
git commit -m "feat: sponsors overlay variants (corner, bar, fullscreen) with rotation"
```

---

## Task 6: Filament window editor — sponsors + scrim fields

**Files:**
- Modify: `app/Filament/Resources/OverlayResource.php`

UI task — verify `php -l` + suite green (apart from the pre-existing stock `ExampleTest`).

- [ ] **Step 1: Add `sponsors` to the window `type` options:** `['groups' => 'Grupės', 'bracket' => 'Brackets', 'sponsors' => 'Rėmėjai']`.

- [ ] **Step 2: Add scrim fields to every window** (inside the windows repeater schema, after `name`/`type`):

```php
                            Forms\Components\Toggle::make('scrim_enabled')->label('Tamsinti foną')->default(false),
                            TextInput::make('scrim_opacity')->label('Fono tamsumas %')->numeric()->minValue(0)->maxValue(100)->default(55)
                                ->visible(fn (Forms\Get $get) => (bool) $get('scrim_enabled')),
```

- [ ] **Step 3: Add sponsors fields** (visible only when `type === 'sponsors'`):

```php
                            Select::make('variant')
                                ->label('Variantas')
                                ->options(['corner' => 'Kampe (besikeičiantys logo)', 'bar' => 'Apačios juosta', 'fullscreen' => 'Per visą ekraną'])
                                ->default('corner')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'sponsors'),

                            Select::make('sponsor_ids')
                                ->label('Rėmėjai iš sąrašo')
                                ->multiple()
                                ->options(fn () => \App\Models\Sponsor::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'sponsors'),

                            FileUpload::make('images')
                                ->label('Arba įkelk nuotraukas (masiškai)')
                                ->image()->multiple()->reorderable()
                                ->disk('public')->directory('overlay-sponsors')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'sponsors'),

                            TextInput::make('rotate_seconds')
                                ->label('Keitimo intervalas (s)')
                                ->numeric()->default(6)->minValue(2)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'sponsors'),
```

- [ ] **Step 4: Gate the existing group/bracket fields** so they only show for their types
  (the subgroups repeater already uses `=== 'groups'`; the `bracket_data` textarea already
  uses `=== 'bracket'` — leave them). Ensure none show for `sponsors`.

- [ ] **Step 5: Verify**

`php -l app/Filament/Resources/OverlayResource.php`; `php artisan test` (green apart from the
pre-existing `ExampleTest`). Manually open `/admin/overlays`, add a sponsors window, pick a
variant + a couple of sponsors and/or upload images, save.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/OverlayResource.php
git commit -m "feat: window editor sponsors fields and per-window scrim"
```

---

## Done criteria

- `php artisan test` green (apart from the unrelated stock `ExampleTest`).
- Sponsors windows (corner/bar/fullscreen) render and rotate; scrim dims the feed at the chosen %; 11 themes selectable.
- Group/bracket windows and existing endpoints unaffected.

## Manual OBS verification (Tadas)

1. Deploy (git pull + clear caches; no migration needed). Restart `node push.js` (unchanged).
2. Create a sponsors window per variant; pick sponsors and/or upload images; Play it.
3. Confirm rotation cadence, smooth animations, and that the scrim dims the background.
4. Switch themes; confirm sponsor + standings windows recolor.
