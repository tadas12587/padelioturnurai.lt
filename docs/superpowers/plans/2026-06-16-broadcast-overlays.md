# Broadcast Overlays Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a self-hosted OBS overlay system in the Laravel/Filament app — group-standings overlays driven by the Tournated GraphQL API and manually-entered bracket overlays — each with its own public output URL, controlled live from an admin page.

**Architecture:** DB-state + polling. Overlays and their live `state` live in one `overlays` table. OBS loads a public transparent page (`/overlay/{token}`) that polls a JSON endpoint (`/overlay/{token}/data`) every ~3 s. Laravel fetches Tournated server-side (solving CORS) and caches it ~15–20 s via the runtime `Cache` facade. A Filament control page writes `overlays.state`, which the overlay reflects on its next poll.

**Tech Stack:** Laravel 12, Filament 3.3, PHPUnit 11 (SQLite `:memory:`, array cache), Blade + vanilla JS for overlays, `Illuminate\Support\Facades\Http` for GraphQL.

**Spec:** `docs/superpowers/specs/2026-06-16-broadcast-overlays-design.md`

---

## Deployment note (read before implementing)

This app runs on chrooted shared hosting. **Never** add `php artisan config:cache` / `route:cache` / `view:cache` to any step. The runtime `Cache` facade (file driver in prod, array in tests) is fine and unrelated. Deploy is `git pull` + `rm -f bootstrap/cache/*.php` + `php artisan optimize:clear`.

## File Structure

**Create:**
- `database/migrations/2026_06_16_000001_create_overlays_table.php` — schema
- `app/Models/Overlay.php` — model, casts, token generation, route binding by token
- `app/Services/TournatedClient.php` — GraphQL fetch + cache + standings computation
- `app/Http/Controllers/OverlayController.php` — public `show` + `data`
- `resources/views/overlays/base.blade.php` — shared transparent shell + polling JS
- `resources/views/overlays/group_standings.blade.php` — adaptive standings markup
- `resources/views/overlays/bracket.blade.php` — adaptive bracket markup
- `app/Filament/Resources/OverlayResource.php` (+ `Pages/{List,Create,Edit}Overlay.php`)
- `app/Filament/Pages/OverlayControlPage.php` + `resources/views/filament/pages/overlay-control.blade.php`
- `tests/Unit/TournatedClientTest.php`
- `tests/Feature/OverlayEndpointTest.php`

**Modify:**
- `routes/web.php` — add two public overlay routes

---

## Task 1: Overlays table + model

**Files:**
- Create: `database/migrations/2026_06_16_000001_create_overlays_table.php`
- Create: `app/Models/Overlay.php`
- Test: `tests/Feature/OverlayEndpointTest.php` (model factory-free creation test for now)

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overlays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // group_standings | bracket
            $table->string('token')->unique();
            $table->string('tournament_external_id')->nullable();
            $table->json('config')->nullable();
            $table->json('state')->nullable();
            $table->json('bracket_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlays');
    }
};
```

- [ ] **Step 2: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Overlay extends Model
{
    protected $fillable = [
        'name', 'type', 'token', 'tournament_external_id',
        'config', 'state', 'bracket_data',
    ];

    protected $casts = [
        'config'       => 'array',
        'state'        => 'array',
        'bracket_data' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Overlay $overlay) {
            if (empty($overlay->token)) {
                do {
                    $token = Str::lower(Str::random(8));
                } while (static::where('token', $token)->exists());
                $overlay->token = $token;
            }
            $overlay->config ??= self::defaultConfig();
            $overlay->state  ??= self::defaultState();
        });
    }

    public static function defaultConfig(): array
    {
        return [
            'title'           => '',
            'accent_color'    => '#C9A84C',
            'logo'            => null,
            'position'        => 'bottom-left',
            'visible_columns' => ['place', 'name', 'wins', 'losses'],
        ];
    }

    public static function defaultState(): array
    {
        return [
            'active_category_id' => null,
            'active_group_id'    => null, // null = all subgroups
            'visible'            => false,
            'next_match'         => '',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
```

- [ ] **Step 3: Write a model test**

In `tests/Feature/OverlayEndpointTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Overlay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverlayEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_overlay_assigns_token_and_defaults(): void
    {
        $overlay = Overlay::create(['name' => 'Test', 'type' => 'group_standings']);

        $this->assertNotEmpty($overlay->token);
        $this->assertSame(8, strlen($overlay->token));
        $this->assertSame('#C9A84C', $overlay->config['accent_color']);
        $this->assertFalse($overlay->state['visible']);
    }
}
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --filter=test_creating_overlay_assigns_token_and_defaults`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_16_000001_create_overlays_table.php app/Models/Overlay.php tests/Feature/OverlayEndpointTest.php
git commit -m "feat: add overlays table and model"
```

---

## Task 2: TournatedClient — standings computation (pure logic, TDD)

This is the PHP port of the documented `calcStats`. Pure function, no HTTP — test first.

**Files:**
- Create: `app/Services/TournatedClient.php`
- Test: `tests/Unit/TournatedClientTest.php`

- [ ] **Step 1: Write the failing test** using the documented fixture

```php
<?php

namespace Tests\Unit;

use App\Services\TournatedClient;
use PHPUnit\Framework\TestCase;

class TournatedClientTest extends TestCase
{
    public function test_compute_standings_counts_wins_and_pairs(): void
    {
        $group = [
            'id'   => 23719,
            'name' => 'U10 Berniukai',
            'entries' => [
                ['id' => 1, 'place' => 1, 'registrationRequest' => ['users' => [
                    ['user' => ['name' => 'Garetas', 'surname' => 'Paplauskas']],
                    ['user' => ['name' => 'Oskaras', 'surname' => 'Žiūkas']],
                ]]],
                ['id' => 2, 'place' => 2, 'registrationRequest' => ['users' => [
                    ['user' => ['name' => 'Jonas', 'surname' => 'Jonaitis']],
                    ['user' => ['name' => 'Petras', 'surname' => 'Petraitis']],
                ]]],
            ],
            'matches' => [
                ['id' => 10, 'status' => 'completed', 'winner' => ['id' => 1]],
            ],
        ];

        $rows = (new TournatedClient)->computeStandings($group);

        $this->assertSame(1, $rows[0]['place']);
        $this->assertSame('Garetas Paplauskas / Oskaras Žiūkas', $rows[0]['name']);
        $this->assertSame(1, $rows[0]['wins']);
        // n=2, totalPossible=1, completed=1 => allDone => losses = 2-1-1 = 0
        $this->assertSame(0, $rows[0]['losses']);
        $this->assertSame(0, $rows[1]['wins']);
        $this->assertSame(1, $rows[1]['losses']);
    }
}
```

- [ ] **Step 2: Run it, verify it fails**

Run: `php artisan test --filter=test_compute_standings_counts_wins_and_pairs`
Expected: FAIL (class `TournatedClient` not found)

- [ ] **Step 3: Implement the service with `computeStandings` + `pairName`**

```php
<?php

namespace App\Services;

class TournatedClient
{
    /**
     * Port of the documented calcStats: wins from winner.id; losses/played
     * resolved only when every round-robin match is complete.
     *
     * @param  array<string,mixed>  $group
     * @return list<array<string,mixed>>
     */
    public function computeStandings(array $group): array
    {
        $entries = $group['entries'] ?? [];
        $matches = $group['matches'] ?? [];

        $wins = [];
        foreach ($entries as $e) {
            $wins[$e['id']] = 0;
        }

        $completed = array_filter($matches, fn ($m) => ($m['status'] ?? null) === 'completed');
        foreach ($completed as $m) {
            $winnerId = $m['winner']['id'] ?? null;
            if ($winnerId !== null && array_key_exists($winnerId, $wins)) {
                $wins[$winnerId]++;
            }
        }

        $n = count($entries);
        $totalPossible = $n > 1 ? $n * ($n - 1) / 2 : 0;
        $allDone = count($completed) >= $totalPossible && $totalPossible > 0;

        $rows = array_map(function ($e) use ($wins, $allDone, $n) {
            $w = $wins[$e['id']] ?? 0;
            return [
                'id'     => $e['id'],
                'place'  => $e['place'] ?? null,
                'name'   => $this->pairName($e),
                'wins'   => $w,
                'losses' => $allDone ? ($n - 1 - $w) : null,
                'played' => $allDone ? ($n - 1) : $w,
            ];
        }, $entries);

        usort($rows, fn ($a, $b) => ($a['place'] ?? 99) <=> ($b['place'] ?? 99));

        return array_values($rows);
    }

    /** @param array<string,mixed> $entry */
    private function pairName(array $entry): string
    {
        $users = $entry['registrationRequest']['users'] ?? [];
        $fmt = fn ($u) => trim(($u['user']['name'] ?? '') . ' ' . ($u['user']['surname'] ?? ''));

        if (count($users) >= 2) {
            return $fmt($users[0]) . ' / ' . $fmt($users[1]);
        }

        return isset($users[0]) ? $fmt($users[0]) : '???';
    }
}
```

- [ ] **Step 4: Run it, verify it passes**

Run: `php artisan test --filter=test_compute_standings_counts_wins_and_pairs`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/TournatedClient.php tests/Unit/TournatedClientTest.php
git commit -m "feat: add TournatedClient standings computation"
```

---

## Task 3: TournatedClient — GraphQL fetch with caching (Http::fake)

**Files:**
- Modify: `app/Services/TournatedClient.php`
- Test: `tests/Unit/TournatedClientTest.php` (add cases) — note this case needs the
  container/cache, so place fetch tests in a Feature test instead.
- Test: `tests/Feature/TournatedFetchTest.php`

- [ ] **Step 1: Write a failing feature test with a faked GraphQL response**

Create `tests/Feature/TournatedFetchTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\TournatedClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TournatedFetchTest extends TestCase
{
    public function test_groups_sends_origin_header_and_parses(): void
    {
        Http::fake([
            'api.tournated.com/*' => Http::response([
                'data' => ['groups' => [[
                    'id' => 1, 'name' => 'A', 'segment' => 'MD',
                    'entries' => [], 'matches' => [],
                ]]],
            ]),
        ]);

        $groups = (new TournatedClient)->groups(47817);

        $this->assertCount(1, $groups);
        $this->assertSame('A', $groups[0]['name']);
        Http::assertSent(fn ($req) =>
            $req->hasHeader('Origin', 'https://play.padel.lt')
            && str_contains($req->body(), '47817')
        );
    }

    public function test_groups_returns_empty_on_failure(): void
    {
        Http::fake(['api.tournated.com/*' => Http::response(null, 500)]);

        $groups = (new TournatedClient)->groups(999);

        $this->assertSame([], $groups);
    }
}
```

- [ ] **Step 2: Run it, verify it fails**

Run: `php artisan test --filter=TournatedFetchTest`
Expected: FAIL (`groups` method missing)

- [ ] **Step 3: Add `graphql`, `groups`, `categories` methods**

Add to `TournatedClient` (and `use` statements at top):

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const ENDPOINT = 'https://api.tournated.com/graphql';
const ORIGIN   = 'https://play.padel.lt';
const CACHE_TTL = 18; // seconds

/** @return array<int,mixed> */
public function groups(int $categoryId): array
{
    return Cache::remember("overlay.groups.$categoryId", self::CACHE_TTL, function () use ($categoryId) {
        $query = '{ groups(filter: { tournamentCategory: ' . $categoryId . ' }) {
            id name segment
            entries { id place registrationRequest { users { user { name surname } } } }
            matches { id status winner { id } }
        } }';

        $data = $this->graphql($query);

        return $data['groups'] ?? [];
    });
}

/** @return array<int,mixed> */
public function categories(int $tournamentId): array
{
    return Cache::remember("overlay.categories.$tournamentId", 300, function () use ($tournamentId) {
        $query = '{ tournament(id: ' . $tournamentId . ') {
            title tournamentCategory { id category { id name } mde }
        } }';

        $data = $this->graphql($query);

        return $data['tournament']['tournamentCategory'] ?? [];
    });
}

/** @return array<string,mixed> */
private function graphql(string $query): array
{
    try {
        $res = Http::timeout(5)
            ->withHeaders(['Origin' => self::ORIGIN])
            ->post(self::ENDPOINT, ['query' => $query]);

        if ($res->failed()) {
            Log::warning('Tournated request failed: ' . $res->status());
            return [];
        }

        return $res->json('data') ?? [];
    } catch (\Throwable $e) {
        Log::warning('Tournated request error: ' . $e->getMessage());
        return [];
    }
}
```

- [ ] **Step 4: Run it, verify it passes**

Run: `php artisan test --filter=TournatedFetchTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/TournatedClient.php tests/Feature/TournatedFetchTest.php
git commit -m "feat: add cached Tournated GraphQL fetch"
```

---

## Task 4: Public overlay routes + data endpoint (feature test)

**Files:**
- Create: `app/Http/Controllers/OverlayController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OverlayEndpointTest.php` (add cases)

- [ ] **Step 1: Write failing feature tests for the data endpoint**

Add to `OverlayEndpointTest.php`:

```php
public function test_data_endpoint_404_for_unknown_token(): void
{
    $this->get('/overlay/nope1234/data')->assertNotFound();
}

public function test_data_endpoint_hidden_when_not_visible(): void
{
    $overlay = Overlay::create(['name' => 'G', 'type' => 'group_standings']);

    $this->getJson("/overlay/{$overlay->token}/data")
        ->assertOk()
        ->assertJson(['visible' => false]);
}

public function test_data_endpoint_returns_groups_when_visible(): void
{
    \Illuminate\Support\Facades\Http::fake([
        'api.tournated.com/*' => \Illuminate\Support\Facades\Http::response([
            'data' => ['groups' => [[
                'id' => 5, 'name' => 'A', 'segment' => 'MD',
                'entries' => [], 'matches' => [],
            ]]],
        ]),
    ]);

    $overlay = Overlay::create([
        'name' => 'G', 'type' => 'group_standings',
        'tournament_external_id' => '10229',
        'state' => ['active_category_id' => 47817, 'active_group_id' => null, 'visible' => true, 'next_match' => 'Next: A vs B'],
    ]);

    $this->getJson("/overlay/{$overlay->token}/data")
        ->assertOk()
        ->assertJson([
            'visible' => true,
            'type'    => 'group_standings',
            'next_match' => 'Next: A vs B',
            'subgroup_count' => 1,
        ]);
}
```

- [ ] **Step 2: Run them, verify they fail**

Run: `php artisan test --filter=OverlayEndpointTest`
Expected: FAIL (route/controller missing)

- [ ] **Step 3: Add the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Overlay;
use App\Services\TournatedClient;
use Illuminate\Http\JsonResponse;

class OverlayController extends Controller
{
    public function show(Overlay $overlay)
    {
        $view = $overlay->type === 'bracket' ? 'overlays.bracket' : 'overlays.group_standings';

        return view($view, ['overlay' => $overlay]);
    }

    public function data(Overlay $overlay, TournatedClient $client): JsonResponse
    {
        $config = array_merge(Overlay::defaultConfig(), $overlay->config ?? []);
        $state  = array_merge(Overlay::defaultState(), $overlay->state ?? []);

        $payload = [
            'type'       => $overlay->type,
            'visible'    => (bool) $state['visible'],
            'title'      => $config['title'],
            'accent'     => $config['accent_color'],
            'logo'       => $config['logo'],
            'position'   => $config['position'],
            'columns'    => $config['visible_columns'],
            'next_match' => $state['next_match'],
            'stale'      => false,
        ];

        if (! $payload['visible']) {
            return response()->json($payload);
        }

        if ($overlay->type === 'group_standings') {
            $payload += $this->groupPayload($overlay, $state, $client);
        } else {
            $payload += $this->bracketPayload($overlay);
        }

        return response()->json($payload);
    }

    /** @param array<string,mixed> $state */
    private function groupPayload(Overlay $overlay, array $state, TournatedClient $client): array
    {
        $categoryId = $state['active_category_id'];
        if (! $categoryId) {
            return ['groups' => [], 'subgroup_count' => 0];
        }

        $raw = $client->groups((int) $categoryId);

        if ($state['active_group_id']) {
            $raw = array_values(array_filter($raw, fn ($g) => $g['id'] == $state['active_group_id']));
        }

        $groups = array_map(fn ($g) => [
            'id'   => $g['id'],
            'name' => $g['name'] ?? '',
            'rows' => $client->computeStandings($g),
        ], $raw);

        return ['groups' => $groups, 'subgroup_count' => count($groups)];
    }

    private function bracketPayload(Overlay $overlay): array
    {
        $data = $overlay->bracket_data ?? ['rounds' => []];
        $rounds = $data['rounds'] ?? [];
        $drawSize = isset($rounds[0]['matches']) ? count($rounds[0]['matches']) * 2 : 0;

        return ['rounds' => $rounds, 'draw_size' => $drawSize];
    }
}
```

- [ ] **Step 4: Add routes** (near the sitemap route, OUTSIDE the locale groups)

In `routes/web.php` add after the sitemap line:

```php
use App\Http\Controllers\OverlayController;

// Broadcast overlays (public, polled by OBS browser sources)
Route::get('/overlay/{overlay}',      [OverlayController::class, 'show'])->name('overlay.show');
Route::get('/overlay/{overlay}/data', [OverlayController::class, 'data'])->name('overlay.data');
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter=OverlayEndpointTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/OverlayController.php routes/web.php tests/Feature/OverlayEndpointTest.php
git commit -m "feat: add public overlay show and data endpoints"
```

---

## Task 5: Overlay Blade templates (transparent + polling + adaptive)

UI/visual — verified manually in a browser (and later OBS), not by automated tests.

**Files:**
- Create: `resources/views/overlays/base.blade.php`
- Create: `resources/views/overlays/group_standings.blade.php`
- Create: `resources/views/overlays/bracket.blade.php`

- [ ] **Step 1: Shared base shell with polling JS**

`resources/views/overlays/base.blade.php`:

```blade
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overlay</title>
    <style>
        html, body { margin: 0; background: transparent; overflow: hidden;
            font-family: 'Inter', system-ui, sans-serif; color: #F5F5F0; }
        #root { position: fixed; opacity: 0; transition: opacity .4s ease; }
        #root.visible { opacity: 1; }
        .pos-bottom-left  { left: 40px; bottom: 40px; }
        .pos-bottom-right { right: 40px; bottom: 40px; }
        .pos-top-left     { left: 40px; top: 40px; }
        .pos-center       { left: 50%; top: 50%; transform: translate(-50%,-50%); }
        @yield('styles')
    </style>
</head>
<body>
    <div id="root"></div>
    <script>
        const DATA_URL = "{{ route('overlay.data', $overlay) }}";
        const POLL_MS  = 3000;
        const root = document.getElementById('root');

        function render(d) { @yield('render_fn_body') }

        async function tick() {
            try {
                const res = await fetch(DATA_URL, { cache: 'no-store' });
                const d = await res.json();
                if (!d.visible) { root.classList.remove('visible'); return; }
                root.className = 'pos-' + (d.position || 'bottom-left');
                render(d);
                requestAnimationFrame(() => root.classList.add('visible'));
            } catch (e) { /* keep last good frame on error */ }
        }
        tick();
        setInterval(tick, POLL_MS);
    </script>
    @yield('extra_script')
</body>
</html>
```

- [ ] **Step 2: Group standings template (adaptive grid)**

`resources/views/overlays/group_standings.blade.php`:

```blade
@extends('overlays.base')

@section('styles')
    .card { background: rgba(17,17,24,.92); border: 1px solid var(--accent);
        backdrop-filter: blur(4px); }
    .wrap { display: grid; gap: 16px; }
    .wrap.cols-1 { grid-template-columns: 1fr; }
    .wrap.cols-2 { grid-template-columns: 1fr 1fr; }
    .wrap.cols-4 { grid-template-columns: repeat(2, 1fr); }
    .title { color: var(--accent); font-weight: 800; letter-spacing:.15em;
        text-transform: uppercase; font-size: 14px; padding: 12px 16px 0; }
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 8px 16px; text-align: left; font-size: 18px; }
    thead th { color: var(--accent); font-size: 12px; text-transform: uppercase; }
    tbody tr + tr { border-top: 1px solid rgba(255,255,255,.08); }
    .lower { margin-top: 16px; background: var(--accent); color: #0A0A0F;
        font-weight: 700; padding: 10px 18px; }
@endsection

@section('render_fn_body')
    document.documentElement.style.setProperty('--accent', d.accent || '#C9A84C');
    const n = d.subgroup_count || (d.groups ? d.groups.length : 0);
    const cols = n >= 4 ? 'cols-4' : (n === 2 ? 'cols-2' : 'cols-1');
    const colLabels = { place: '#', name: 'Pora', wins: 'W', losses: 'L', played: 'Ž' };
    const cs = d.columns || ['place','name','wins','losses'];

    let html = '';
    if (d.title) html += `<div class="title">${d.title}</div>`;
    html += `<div class="wrap ${cols}">`;
    for (const g of (d.groups || [])) {
        html += `<div class="card"><div class="title">${g.name || ''}</div><table><thead><tr>`;
        for (const c of cs) html += `<th>${colLabels[c] || c}</th>`;
        html += `</tr></thead><tbody>`;
        for (const r of g.rows) {
            html += '<tr>';
            for (const c of cs) html += `<td>${r[c] ?? '-'}</td>`;
            html += '</tr>';
        }
        html += `</tbody></table></div>`;
    }
    html += `</div>`;
    if (d.next_match) html += `<div class="lower">${d.next_match}</div>`;
    root.innerHTML = html;
@endsection
```

- [ ] **Step 3: Bracket template (adaptive rounds)**

`resources/views/overlays/bracket.blade.php`:

```blade
@extends('overlays.base')

@section('styles')
    .bracket { display: flex; gap: 40px; align-items: center;
        background: rgba(17,17,24,.92); border: 1px solid var(--accent); padding: 24px; }
    .round { display: flex; flex-direction: column; gap: 24px; }
    .match { background: #0A0A0F; border: 1px solid rgba(255,255,255,.1); min-width: 220px; }
    .team { padding: 8px 12px; font-size: 16px; display: flex; justify-content: space-between; }
    .team + .team { border-top: 1px solid rgba(255,255,255,.1); }
    .team.win { color: var(--accent); font-weight: 700; }
    .title { color: var(--accent); font-weight: 800; text-transform: uppercase;
        letter-spacing: .15em; font-size: 14px; margin-bottom: 12px; }
@endsection

@section('render_fn_body')
    document.documentElement.style.setProperty('--accent', d.accent || '#C9A84C');
    let html = '';
    if (d.title) html += `<div class="title">${d.title}</div>`;
    html += `<div class="bracket">`;
    for (const round of (d.rounds || [])) {
        html += `<div class="round">`;
        for (const m of (round.matches || [])) {
            html += `<div class="match">`;
            for (const t of (m.teams || [])) {
                html += `<div class="team ${t.winner ? 'win' : ''}">
                    <span>${t.name || 'TBD'}</span><span>${t.score ?? ''}</span></div>`;
            }
            html += `</div>`;
        }
        html += `</div>`;
    }
    html += `</div>`;
    root.innerHTML = html;
@endsection
```

- [ ] **Step 4: Manual visual check**

Run `php artisan serve`, create an overlay via tinker with `visible=true` and a faked
category, open `http://127.0.0.1:8000/overlay/{token}` in a browser. Confirm it renders
on a transparent (browser-white) background and updates. (Full OBS check happens at the end.)

- [ ] **Step 5: Commit**

```bash
git add resources/views/overlays/
git commit -m "feat: add adaptive overlay blade templates"
```

---

## Task 6: Filament OverlayResource (CRUD + copy OBS URL)

**Files:**
- Create: `app/Filament/Resources/OverlayResource.php`
- Create: `app/Filament/Resources/OverlayResource/Pages/{ListOverlays,CreateOverlay,EditOverlay}.php`

- [ ] **Step 1: Generate the resource scaffold**

Run: `php artisan make:filament-resource Overlay --generate`
(Then replace the generated form/table per below; keep the generated Pages.)

- [ ] **Step 2: Define the form and table**

Key form fields (group into sections):
- `name` (TextInput, required)
- `type` (Select: `group_standings` => 'Grupės', `bracket` => 'Brackets', required, live)
- `tournament_external_id` (TextInput, visible when type=group_standings, helper: "Tournated turnyro ID")
- `config.title` (TextInput)
- `config.accent_color` (ColorPicker, default `#C9A84C`)
- `config.logo` (FileUpload, image, optional)
- `config.position` (Select: bottom-left/bottom-right/top-left/center)
- `config.visible_columns` (CheckboxList: place/name/wins/losses/played) — group type only
- `bracket_data` (KeyValue or Textarea JSON) — bracket type only, helper explaining structure

Use dot-notation statePath so Filament writes into the `config` json cast
(`TextInput::make('config.title')`). Ensure the model casts handle nested arrays
(they do via `'config' => 'array'`).

Table columns: `name`, `type` (badge), `token`. Add a row Action "Kopijuoti OBS URL"
that copies `url('/overlay/' . $record->token)`:

```php
Tables\Actions\Action::make('copyUrl')
    ->label('OBS URL')
    ->icon('heroicon-o-clipboard')
    ->action(fn () => null)
    ->extraAttributes(fn ($record) => [
        'x-on:click' => "navigator.clipboard.writeText('" . url('/overlay/' . $record->token) . "'); \$tooltip('Nukopijuota!')",
    ]);
```

Set `protected static ?string $navigationGroup = 'Transliacijos';` and
`$navigationIcon = 'heroicon-o-tv';` and `$navigationLabel = 'Overlay\'ai';`.

- [ ] **Step 3: Smoke-test the admin loads**

Run: `php artisan test` (full suite still green) and manually open `/admin/overlays`,
create a `group_standings` overlay, confirm the OBS URL action appears.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/OverlayResource.php app/Filament/Resources/OverlayResource/
git commit -m "feat: add Filament Overlay resource"
```

---

## Task 7: Filament OverlayControlPage (live control)

**Files:**
- Create: `app/Filament/Pages/OverlayControlPage.php`
- Create: `resources/views/filament/pages/overlay-control.blade.php`

- [ ] **Step 1: Build the Livewire page**

Behavior:
- Select an overlay (dropdown of all overlays).
- For `group_standings`: load categories via `TournatedClient::categories($overlay->tournament_external_id)` into a Select; a second Select for subgroup (options from `TournatedClient::groups($categoryId)` plus an "Visi pogrupiai" = null option); a Toggle for `visible`; a TextInput for `next_match`.
- For `bracket`: a Toggle for `visible` (+ optional next_match).
- A "Pritaikyti" (Apply) button that writes the chosen values into `$overlay->state` and saves. Because the overlay polls every 3 s, no realtime push is needed.
- Show the live output URL for convenience.

Skeleton:

```php
<?php

namespace App\Filament\Pages;

use App\Models\Overlay;
use App\Services\TournatedClient;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class OverlayControlPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-play';
    protected static ?string $navigationGroup = 'Transliacijos';
    protected static ?string $navigationLabel = 'Overlay valdymas';
    protected static ?string $title           = 'Overlay valdymas';
    protected static string  $view            = 'filament.pages.overlay-control';

    public ?int $overlayId = null;
    public ?array $data = [];

    public function updatedOverlayId(): void
    {
        $overlay = Overlay::find($this->overlayId);
        $this->data = $overlay?->state ?? Overlay::defaultState();
    }

    public function categoryOptions(): array
    {
        $overlay = Overlay::find($this->overlayId);
        if (! $overlay?->tournament_external_id) return [];
        $cats = app(TournatedClient::class)->categories((int) $overlay->tournament_external_id);
        return collect($cats)->mapWithKeys(fn ($c) => [$c['id'] => $c['category']['name'] ?? ('#'.$c['id'])])->all();
    }

    public function groupOptions(): array
    {
        $catId = $this->data['active_category_id'] ?? null;
        if (! $catId) return ['' => 'Visi pogrupiai'];
        $groups = app(TournatedClient::class)->groups((int) $catId);
        return ['' => 'Visi pogrupiai'] + collect($groups)->mapWithKeys(fn ($g) => [$g['id'] => $g['name'] ?? ('#'.$g['id'])])->all();
    }

    public function apply(): void
    {
        $overlay = Overlay::findOrFail($this->overlayId);
        $overlay->state = array_merge(Overlay::defaultState(), $this->data, [
            'active_group_id' => $this->data['active_group_id'] ?: null,
        ]);
        $overlay->save();

        Notification::make()->title('Pritaikyta!')->success()->send();
    }
}
```

- [ ] **Step 2: Build the blade view**

`resources/views/filament/pages/overlay-control.blade.php` — a simple form: overlay
`<select wire:model.live="overlayId">`, then (when selected) the category/group selects
bound to `wire:model="data.active_category_id"` etc., a visible toggle
(`wire:model="data.visible"`), a next-match text input, and an Apply button
(`wire:click="apply"`). Show the output URL with the same copy button as the resource.
Use `@foreach($this->categoryOptions() as $id => $label)` to fill the selects.

- [ ] **Step 3: Manual check**

Open `/admin/overlay-valdymas` (or whatever slug Filament assigns), pick an overlay,
toggle visible + set a category, click Apply, and confirm the open overlay page reflects
the change within ~3 s.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Pages/OverlayControlPage.php resources/views/filament/pages/overlay-control.blade.php
git commit -m "feat: add overlay live control page"
```

---

## Task 8: Stale-data resilience + final verification

**Files:**
- Modify: `app/Http/Controllers/OverlayController.php`
- Test: `tests/Feature/OverlayEndpointTest.php`

- [ ] **Step 1: Write a failing test — visible overlay with failing API still returns 200 and `stale`**

```php
public function test_data_endpoint_marks_stale_when_api_down_and_no_cache(): void
{
    \Illuminate\Support\Facades\Http::fake(['api.tournated.com/*' => \Illuminate\Support\Facades\Http::response(null, 500)]);

    $overlay = Overlay::create([
        'name' => 'G', 'type' => 'group_standings',
        'tournament_external_id' => '10229',
        'state' => ['active_category_id' => 47817, 'visible' => true],
    ]);

    $this->getJson("/overlay/{$overlay->token}/data")
        ->assertOk()
        ->assertJson(['visible' => true, 'groups' => [], 'stale' => true]);
}
```

- [ ] **Step 2: Run it, verify it fails** (currently `stale` is always false)

Run: `php artisan test --filter=test_data_endpoint_marks_stale`
Expected: FAIL

- [ ] **Step 3: Set `stale` when a visible group overlay resolves zero groups from a configured category**

In `groupPayload`, when `$categoryId` is set but `$raw` is empty, return
`['groups' => [], 'subgroup_count' => 0, 'stale' => true]`. Merge that `stale` into the
payload (override the default `false`). Keep the simple heuristic — true stale-cache
retention is provided naturally by the 18 s `Cache::remember` (a recent good fetch stays
served during a brief outage).

- [ ] **Step 4: Run the full suite**

Run: `php artisan test`
Expected: PASS (all)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/OverlayController.php tests/Feature/OverlayEndpointTest.php
git commit -m "feat: mark overlay data stale when upstream unavailable"
```

- [ ] **Step 6: Final manual OBS verification (done by Tadas)**

1. Deploy (git pull + clear caches, no artisan cache commands).
2. In admin, create a group overlay with a real Tournated tournament id, copy its OBS URL.
3. Add a Browser Source in OBS (1920×1080) with that URL.
4. On the control page, pick a category, toggle visible — confirm it appears in OBS within ~3 s.
5. Switch subgroups / "all subgroups" and confirm the layout adapts (2-up / 4-up).
6. Enter a bracket overlay's `bracket_data` (8 then 16 teams) and confirm rounds adapt.

---

## Done criteria

- `php artisan test` green.
- Admin can create/configure multiple overlays, each with a copyable OBS URL.
- Group overlays render live Tournated standings and adapt to 1/2/4 subgroups.
- Bracket overlays render manually-entered draws and adapt to 8/16 sizes.
- Control page toggles visibility and switches category/group, reflected in ≤3 s.
- Overlays never blank/error on a transient API failure.
