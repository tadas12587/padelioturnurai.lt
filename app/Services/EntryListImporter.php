<?php

namespace App\Services;

use App\Models\EntryList;

/**
 * Minimal .xlsx reader (no external libraries) that turns an entry-list export
 * into pairs grouped by category, ready for the draw.
 */
class EntryListImporter
{
    /**
     * Parse a tournament entry-list .xlsx into { normCategory => [teams] } plus
     * a display-name map and totals.
     *
     * @return array{by_cat:array<string,array<int,array<string,mixed>>>,names:array<string,string>,total:int}
     */
    public static function fromFile(string $path): array
    {
        $rows = self::readSheet($path);
        if (count($rows) < 2) {
            return ['by_cat' => [], 'names' => [], 'total' => 0];
        }

        // Map header text → column index (first row).
        $hi = [];
        foreach ($rows[0] as $idx => $label) {
            $key = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $label)));
            if ($key !== '' && ! isset($hi[$key])) {
                $hi[$key] = $idx;
            }
        }
        $col = fn (array $row, string $header) => isset($hi[$header]) ? trim((string) ($row[$hi[$header]] ?? '')) : '';
        $genderCode = fn (string $g) => str_starts_with(mb_strtolower($g), 'f') || str_contains(mb_strtolower($g), 'moter') || str_contains(mb_strtolower($g), 'women') ? 'M' : 'V';

        $byCat = [];
        $names = [];
        $total = 0;
        $seq = 0;
        foreach (array_slice($rows, 1) as $row) {
            $catName = $col($row, 'category');
            if ($catName === '') {
                continue;
            }
            $p1 = trim($col($row, 'player 1 name') . ' ' . $col($row, 'player 1 surname'));
            $p2 = trim($col($row, 'player 2 name') . ' ' . $col($row, 'player 2 surname'));
            $name = $p2 !== '' ? "{$p1} / {$p2}" : $p1;
            if (trim($name) === '' || trim($name) === '/') {
                continue;
            }

            $players = [];
            if ($p1 !== '') {
                $players[] = ['name' => $p1, 'country' => $col($row, 'player 1 country') ?: null];
            }
            if ($p2 !== '') {
                $players[] = ['name' => $p2, 'country' => $col($row, 'player 2 country') ?: null];
            }

            $norm = EntryList::normCategory($catName);
            $names[$norm] = $catName;
            $byCat[$norm][] = [
                'id'      => 'xls-' . (++$seq),
                'name'    => $name,
                'seed'    => ($s = $col($row, 'seed')) !== '' && $s !== '0' ? $s : null,
                'pot'     => null,
                'gender'  => $genderCode($col($row, 'player 1 gender')),
                'country' => $col($row, 'player 1 country') ?: null,
                'players' => $players,
            ];
            $total++;
        }

        return ['by_cat' => $byCat, 'names' => $names, 'total' => $total];
    }

    /** Parse + persist for a tournament (replaces any previous import). */
    public static function import(string $tournamentId, string $path, ?string $sourceName = null): array
    {
        $parsed = self::fromFile($path);
        EntryList::updateOrCreate(
            ['tournament_external_id' => $tournamentId],
            ['data' => $parsed['by_cat'], 'names' => $parsed['names'], 'source_name' => $sourceName],
        );

        return $parsed;
    }

    /**
     * Read the first worksheet of an .xlsx as a list of rows, each row a
     * [columnIndex => stringValue] map. Handles shared/inline strings.
     *
     * @return list<array<int,string>>
     */
    private static function readSheet(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Nepavyko atidaryti .xlsx failo.');
        }

        $shared = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            $x = @simplexml_load_string($ss);
            if ($x !== false) {
                foreach ($x->si as $si) {
                    if (isset($si->t)) {
                        $shared[] = (string) $si->t;
                    } else {
                        $t = '';
                        foreach ($si->r as $r) {
                            $t .= (string) $r->t;
                        }
                        $shared[] = $t;
                    }
                }
            }
        }

        $sheetPath = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', (string) $n)) {
                $sheetPath = $n;
                break;
            }
        }
        $sheet = $sheetPath ? $zip->getFromName($sheetPath) : false;
        $zip->close();
        if ($sheet === false) {
            throw new \RuntimeException('Nerastas lapas .xlsx faile.');
        }

        $x = simplexml_load_string($sheet);
        $rows = [];
        foreach ($x->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $colLetters = preg_replace('/\d+/', '', $ref);
                $idx = self::colIndex($colLetters);
                $t = (string) $c['t'];
                if ($t === 's') {
                    $cells[$idx] = $shared[(int) (string) $c->v] ?? '';
                } elseif ($t === 'inlineStr') {
                    $cells[$idx] = (string) $c->is->t;
                } else {
                    $cells[$idx] = (string) $c->v;
                }
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord(strtoupper($ch)) - 64);
        }

        return $n - 1;
    }
}
