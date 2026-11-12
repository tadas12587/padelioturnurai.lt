<?php

namespace App\Console\Commands;

use App\Services\Push\WebPush;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-time VAPID setup. The private key goes only to a file (never stdout),
 * so it does not end up in terminal scrollback or logs.
 * Works on the host's CLI too: WebPush falls back to a config-free key
 * generation when openssl.cnf is not readable.
 */
class PushVapidKeysCommand extends Command
{
    protected $signature = 'push:vapid-keys
        {path : Failas, į kurį įrašomas privatus raktas (PEM)}
        {--force : Perrašyti esamą failą}';

    protected $description = 'Sukuria VAPID raktų porą Web Push pranešimams';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (file_exists($path) && ! $this->option('force')) {
            $this->error("Failas jau yra: {$path} (naudok --force)");

            return self::FAILURE;
        }

        try {
            $keys = WebPush::generateVapidKeys();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (file_put_contents($path, $keys['private']) === false) {
            $this->error("Nepavyko įrašyti: {$path}");

            return self::FAILURE;
        }
        @chmod($path, 0600);

        $this->info("Privatus raktas įrašytas: {$path}");
        $this->line('Viešasis raktas (VAPID_PUBLIC_KEY):');
        $this->line($keys['public']);

        return self::SUCCESS;
    }
}
