<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'site_settings';

    protected $fillable = ['key', 'value'];

    /** @var array<string,mixed> */
    protected static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$cache)) {
            return static::$cache[$key] ?? $default;
        }

        $value = static::where('key', $key)->value('value');
        static::$cache[$key] = $value;

        return $value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::$cache[$key] = $value;
    }

    /** Load all settings into config at once (called from AppServiceProvider) */
    public static function applyMailConfig(): void
    {
        try {
            $mailer = static::get('mail_mailer');
            if (! $mailer) {
                return;
            }

            config(['mail.default' => $mailer]);

            if ($mailer === 'smtp') {
                config([
                    'mail.mailers.smtp.host'       => static::get('mail_host', config('mail.mailers.smtp.host')),
                    'mail.mailers.smtp.port'       => (int) static::get('mail_port', config('mail.mailers.smtp.port')),
                    'mail.mailers.smtp.username'   => static::get('mail_username'),
                    'mail.mailers.smtp.password'   => static::get('mail_password'),
                    'mail.mailers.smtp.encryption' => static::get('mail_encryption', 'tls'),
                ]);
            }

            // phpmail uses our custom NativeMailTransport — no extra config needed
            if ($mailer === 'phpmail') {
                config(['mail.mailers.phpmail' => ['transport' => 'phpmail']]);
            }

            if ($from = static::get('mail_from_address')) {
                config(['mail.from.address' => $from]);
            }
            if ($name = static::get('mail_from_name')) {
                config(['mail.from.name' => $name]);
            }
        } catch (\Throwable) {
            // DB not yet available (migrations not run etc.)
        }
    }
}
