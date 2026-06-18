# Broadcaster Downloadable App — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let anyone broadcast from any PC/Mac by downloading a self-contained `push.js` binary from the admin — no Node install.

**Architecture:** Bun `--compile` packages the existing `tools/overlay-push/push.js` (ESM) into three cross-built single-file binaries (Win x64, macOS arm64, macOS x64). A Filament "Transliacijos įrankis" page serves them from `storage/app/public/broadcaster/` with per-OS instructions. Binaries are built locally and uploaded to the server (not committed — too large).

**Tech Stack:** Bun (build only), Laravel 12, Filament 3.3, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-06-18-broadcaster-app-design.md`

---

## File Structure

| File | Responsibility | Action |
|------|----------------|--------|
| `tools/overlay-push/build.mjs` | Run the 3 Bun cross-compiles into `dist/` | Create |
| `tools/overlay-push/dist/` | Built binaries (gitignored) | Create (build output) |
| `.gitignore` | Ignore `tools/overlay-push/dist/` | Modify |
| `app/Filament/Pages/BroadcasterToolPage.php` | Download links + token/tournament info | Create |
| `resources/views/filament/pages/broadcaster-tool.blade.php` | Page UI | Create |
| `tests/Feature/BroadcasterToolTest.php` | `downloads()` reflects storage presence | Create |
| `docs/overlays.md` | Build + upload instructions | Modify |

---

## Task 1: Build script + gitignore

**Files:**
- Create: `tools/overlay-push/build.mjs`
- Modify: `.gitignore`

No automated test (depends on Bun being installed); verified manually.

- [ ] **Step 1: Create `build.mjs`**

```js
import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

// Cross-compiles push.js into self-contained binaries for Windows + macOS.
// Requires Bun (https://bun.sh). Run from anywhere:  node tools/overlay-push/build.mjs
const here = dirname(fileURLToPath(import.meta.url));
mkdirSync(join(here, 'dist'), { recursive: true });

const targets = [
  ['bun-windows-x64', 'overlay-push-win.exe'],
  ['bun-darwin-arm64', 'overlay-push-mac-arm'],
  ['bun-darwin-x64', 'overlay-push-mac-intel'],
];

for (const [target, out] of targets) {
  console.log(`Building ${out} (${target})…`);
  execSync(`bun build ./push.js --compile --target=${target} --outfile ./dist/${out}`, {
    cwd: here, stdio: 'inherit',
  });
}
console.log('Built into', join(here, 'dist'));
```

- [ ] **Step 2: Gitignore the dist dir**

Append to `.gitignore`:
```
/tools/overlay-push/dist/
```

- [ ] **Step 3: Manual verify** (needs Bun installed)

Run: `node tools/overlay-push/build.mjs`
Expected: three files appear in `tools/overlay-push/dist/`. If Bun is missing, install: `powershell -c "irm bun.sh/install.ps1 | iex"` (Win) / `curl -fsSL https://bun.sh/install | bash` (Mac).

- [ ] **Step 4: Commit**

```bash
git add tools/overlay-push/build.mjs .gitignore
git commit -m "build(overlay-push): Bun cross-compile script for broadcaster binaries"
```

---

## Task 2: BroadcasterToolPage (downloads logic, TDD)

**Files:**
- Create: `app/Filament/Pages/BroadcasterToolPage.php`
- Test: `tests/Feature/BroadcasterToolTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Pages\BroadcasterToolPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BroadcasterToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_reflect_storage_presence(): void
    {
        Storage::fake('public');

        $before = collect((new BroadcasterToolPage())->downloads())->firstWhere('os', 'win');
        $this->assertFalse($before['exists']);
        $this->assertNull($before['url']);

        Storage::disk('public')->put('broadcaster/overlay-push-win.exe', 'binary');

        $after = collect((new BroadcasterToolPage())->downloads())->firstWhere('os', 'win');
        $this->assertTrue($after['exists']);
        $this->assertNotNull($after['url']);
    }

    public function test_downloads_lists_all_three_targets(): void
    {
        Storage::fake('public');

        $oses = array_column((new BroadcasterToolPage())->downloads(), 'os');
        $this->assertSame(['win', 'mac-arm', 'mac-intel'], $oses);
    }
}
```

- [ ] **Step 2: Run, verify fail**

Run: `php artisan test --filter=BroadcasterToolTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement the page**

```php
<?php

namespace App\Filament\Pages;

use App\Models\Overlay;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class BroadcasterToolPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationGroup = 'Transliacijos';
    protected static ?string $navigationLabel = 'Transliacijos įrankis';
    protected static ?string $title = 'Transliacijos įrankis';
    protected static string $view = 'filament.pages.broadcaster-tool';

    /** @return list<array{os:string,label:string,file:string,url:?string,exists:bool}> */
    public function downloads(): array
    {
        $items = [
            ['os' => 'win', 'label' => 'Windows', 'file' => 'broadcaster/overlay-push-win.exe'],
            ['os' => 'mac-arm', 'label' => 'Mac (Apple Silicon)', 'file' => 'broadcaster/overlay-push-mac-arm'],
            ['os' => 'mac-intel', 'label' => 'Mac (Intel)', 'file' => 'broadcaster/overlay-push-mac-intel'],
        ];

        return array_map(function ($i) {
            $i['exists'] = Storage::disk('public')->exists($i['file']);
            $i['url'] = $i['exists'] ? Storage::disk('public')->url($i['file']) : null;

            return $i;
        }, $items);
    }

    public function token(): ?string
    {
        return config('services.overlay.ingest_token');
    }

    /** @return list<string> */
    public function tournaments(): array
    {
        return Overlay::query()
            ->whereNotNull('tournament_external_id')
            ->where('tournament_external_id', '!=', '')
            ->pluck('tournament_external_id')
            ->map(fn ($i) => (string) $i)
            ->unique()->values()->all();
    }
}
```

- [ ] **Step 4: Run, verify pass** — `php artisan test --filter=BroadcasterToolTest` → PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Pages/BroadcasterToolPage.php tests/Feature/BroadcasterToolTest.php
git commit -m "feat(broadcaster): admin page download logic"
```

---

## Task 3: BroadcasterToolPage view

**Files:**
- Create: `resources/views/filament/pages/broadcaster-tool.blade.php`

No automated test (markup); manual verify.

- [ ] **Step 1: Create the view**

```blade
<x-filament-panels::page>
    <div class="space-y-6 max-w-2xl">
        <p class="text-sm text-gray-500">
            Parsisiųsk įrankį į kompiuterį, iš kurio transliuosi, ir paleisk. Jis automatiškai
            siunčia turnyro duomenis į svetainę — Node.js diegti nereikia. Programą laikyk
            paleistą visą transliacijos laiką.
        </p>

        <div class="space-y-3">
            @foreach($this->downloads() as $d)
                <div class="flex items-center justify-between gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                    <span class="font-medium">{{ $d['label'] }}</span>
                    @if($d['exists'])
                        <x-filament::button tag="a" href="{{ $d['url'] }}" icon="heroicon-o-arrow-down-tray">
                            Atsisiųsti
                        </x-filament::button>
                    @else
                        <span class="text-sm text-amber-600">dar neįkelta</span>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="text-sm space-y-2">
            <div class="font-medium">Kaip paleisti</div>
            <ul class="list-disc pl-5 space-y-1 text-gray-600 dark:text-gray-400">
                <li><b>Windows:</b> dukart spustelėk atsisiųstą <code>.exe</code>. Jei „Windows protected your PC" — „More info" → „Run anyway".</li>
                <li><b>Mac:</b> pirmą kartą — dešinys pelės mygtukas ant failo → „Open" → „Open" (nes programa nepasirašyta). Vėliau dukart spustelėk.</li>
            </ul>
        </div>

        <div class="text-sm text-gray-500">
            Aktyvūs turnyrai: <b>{{ implode(', ', $this->tournaments()) ?: '—' }}</b>
        </div>
    </div>
</x-filament-panels::page>
```

- [ ] **Step 2: Manual verify** — `php artisan serve`, open the page; buttons show "dar neįkelta" until binaries are uploaded.

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/pages/broadcaster-tool.blade.php
git commit -m "feat(broadcaster): admin page view"
```

---

## Task 4: Docs — build & upload workflow

**Files:**
- Modify: `docs/overlays.md`

- [ ] **Step 1: Add a "Transliacijos įrankis (parsisiunčiama programa)" subsection** to the push.js section, covering:
  - Why (machine-independent broadcasting; no Node install).
  - Build: install Bun, run `node tools/overlay-push/build.mjs` → three files in `dist/`.
  - Upload: copy the three files to the server `storage/app/public/broadcaster/` (FTP or scp). Ensure `php artisan storage:link` exists (public symlink).
  - Rebuild only when `push.js` or the token changes.
  - macOS Gatekeeper note (`xattr -d com.apple.quarantine <file>`).

- [ ] **Step 2: Commit**

```bash
git add docs/overlays.md
git commit -m "docs: broadcaster app build and upload steps"
```

---

## Notes for the implementer

- **`storage:link` required:** the public disk URL works only if `public/storage` symlink exists (`php artisan storage:link`). On the chroot host it may already exist; if downloads 404, that's the cause.
- **Don't commit binaries** — they're large and platform-specific; `dist/` is gitignored, files live in `storage` on the server.
- **No code signing** in v1 — document the Mac right-click-Open workaround.
- **Token** stays embedded in `push.js` defaults; rebuild + re-upload if it rotates.
