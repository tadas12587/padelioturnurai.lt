<?php

namespace App\Services;

/**
 * Minimal .xlsx reader (no external libraries) for a "grupės" export: one
 * worksheet per category, each row a pair with "Žaidėjas N" / "Miestas N"
 * columns. Pulls out personKey => city so the global player library can be
 * supplemented with cities without touching anything else.
 */
class PlayerCityImporter
{
    /**
     * Parse every worksheet in the file into a personKey => city map. Players
     * with no city cell are skipped (never overwrite with a blank). If the same
     * player appears more than once (e.g. several categories), the last
     * non-empty city wins.
     *
     * @return array<string,string>
     */
    public static function citiesFromFile(string $path): array
    {
        $overlayData = app(OverlayData::class);
        $out = [];

        foreach (self::readAllSheets($path) as $rows) {
            if (count($rows) < 2) {
                continue;
            }

            // The header isn't always row 1 (row 0 can be a merged title), so
            // find the row that actually contains "žaidėjas 1".
            $headerIdx = null;
            $hi = [];
            foreach ($rows as $i => $row) {
                $map = [];
                foreach ($row as $idx => $label) {
                    $map[mb_strtolower(trim((string) $label))] = $idx;
                }
                if (isset($map['žaidėjas 1'])) {
                    $headerIdx = $i;
                    $hi = $map;
                    break;
                }
            }
            if ($headerIdx === null) {
                continue;
            }

            $col = fn (array $row, string $header) => isset($hi[$header]) ? trim((string) ($row[$hi[$header]] ?? '')) : '';

            foreach (array_slice($rows, $headerIdx + 1) as $row) {
                foreach ([['žaidėjas 1', 'miestas 1'], ['žaidėjas 2', 'miestas 2']] as [$nameCol, $cityCol]) {
                    // Collapse stray double spaces (e.g. "Almantas  Oželis") so the
                    // key matches what's already stored for the same player.
                    $name = trim(preg_replace('/\s+/', ' ', $col($row, $nameCol)));
                    $city = $col($row, $cityCol);
                    if ($name === '' || $city === '') {
                        continue;
                    }
                    $out[$overlayData->personKey($name)] = $city;
                }
            }
        }

        return $out;
    }

    /**
     * Apply a personKey => city map to the global player library. Only updates
     * players that already exist (never creates new ones) and only when the
     * map has a non-empty city for that player.
     *
     * @param  array<string,string>  $cities
     * @return int number of players updated
     */
    public static function apply(array $cities): int
    {
        if (empty($cities)) {
            return 0;
        }

        $n = 0;
        \App\Models\PlayerPhoto::whereIn('person_key', array_keys($cities))->get()->each(function ($row) use ($cities, &$n) {
            $city = $cities[$row->person_key] ?? null;
            if ($city !== null && $city !== '') {
                $row->city = $city;
                $row->save();
                $n++;
            }
        });

        return $n;
    }

    /**
     * Read every worksheet as a list of rows (each row a [colIndex => value] map).
     *
     * @return list<list<array<int,string>>>
     */
    private static function readAllSheets(string $path): array
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

        $sheetPaths = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', (string) $n)) {
                $sheetPaths[] = $n;
            }
        }
        natsort($sheetPaths);

        $sheets = [];
        foreach ($sheetPaths as $sheetPath) {
            $sheet = $zip->getFromName($sheetPath);
            if ($sheet === false) {
                continue;
            }
            $x = @simplexml_load_string($sheet);
            if ($x === false) {
                continue;
            }
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
            $sheets[] = $rows;
        }
        $zip->close();

        return $sheets;
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
