<?php

namespace App\Services\Push;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Minimal Web Push sender (RFC 8030 + RFC 8291 encryption + RFC 8292 VAPID).
 *
 * Written by hand because the production host has no gmp/bcmath, which
 * minishlink/web-push and web-token require. Everything here needs only
 * ext-openssl (EC P-256, ECDH, ES256, AES-128-GCM) and hash_hkdf.
 */
final class WebPush
{
    /** SPKI DER prefix for an uncompressed P-256 public key (id-ecPublicKey + prime256v1). */
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** RFC 8188 record size; one record is plenty for a push payload (max ~4 KB). */
    private const RECORD_SIZE = 4096;

    private OpenSSLAsymmetricKey $vapidPrivateKey;

    public function __construct(
        private readonly string $vapidPublicKeyB64Url,
        string $vapidPrivateKeyPem,
        private readonly string $subject,
    ) {
        $key = openssl_pkey_get_private($vapidPrivateKeyPem);
        if ($key === false) {
            throw new RuntimeException('Netinkamas VAPID privatus raktas: '.openssl_error_string());
        }
        $this->vapidPrivateKey = $key;
    }

    /**
     * @param  array{endpoint: string, keys: array{p256dh: string, auth: string}}  $subscription
     * @return array{status: int, ok: bool, gone: bool, body: string}
     *   gone = subscription expired (404/410) – caller should delete it
     */
    public function send(array $subscription, string $payload, int $ttl = 600, string $urgency = 'high'): array
    {
        $endpoint = $subscription['endpoint'];
        $body = $this->encrypt(
            $payload,
            self::b64urlDecode($subscription['keys']['p256dh']),
            self::b64urlDecode($subscription['keys']['auth']),
        );

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'vapid t='.$this->vapidJwt($endpoint).', k='.$this->vapidPublicKeyB64Url,
                    'TTL' => (string) $ttl,
                    'Urgency' => $urgency,
                    'Content-Encoding' => 'aes128gcm',
                ])
                ->withBody($body, 'application/octet-stream')
                ->post($endpoint);
        } catch (ConnectionException $e) {
            return ['status' => 0, 'ok' => false, 'gone' => false, 'body' => $e->getMessage()];
        }

        $status = $response->status();

        return [
            'status' => $status,
            'ok' => $status >= 200 && $status < 300,
            'gone' => $status === 404 || $status === 410,
            'body' => $response->body(),
        ];
    }

    /** aes128gcm-encrypted body for one subscriber, with a fresh ephemeral key and salt. */
    public function encrypt(string $payload, string $uaPublicRaw, string $authSecret): string
    {
        return self::encryptWith($payload, $uaPublicRaw, $authSecret, self::newEcKey(), random_bytes(16));
    }

    /**
     * Fresh P-256 private key.
     *
     * openssl_pkey_new() needs a readable openssl.cnf, which is missing in
     * some setups (the host's PHP CLI, XAMPP on Windows). In that case pick a
     * random scalar in [1, n-1] and load it as a SEC1 key – OpenSSL derives
     * the public point itself, no config file involved.
     */
    public static function newEcKey(): OpenSSLAsymmetricKey
    {
        $key = @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key !== false) {
            return $key;
        }
        while (openssl_error_string() !== false) {
            // drain the config-file errors so they don't confuse later messages
        }

        $n = hex2bin('ffffffff00000000ffffffffffffffffbce6faada7179e84f3b9cac2fc632551');
        do {
            $d = random_bytes(32);
        } while ($d === str_repeat("\0", 32) || strcmp($d, $n) >= 0);

        return self::privateKeyFromRaw($d);
    }

    /** Load a raw 32-byte P-256 private scalar (SEC1 ECPrivateKey without the optional public key). */
    public static function privateKeyFromRaw(string $d): OpenSSLAsymmetricKey
    {
        $der = hex2bin('30310201010420').$d.hex2bin('a00a06082a8648ce3d030107');
        $key = openssl_pkey_get_private(self::pem('EC PRIVATE KEY', $der));
        if ($key === false) {
            throw new RuntimeException('Netinkamas EC privatus raktas: '.openssl_error_string());
        }

        return $key;
    }

    /**
     * RFC 8291 §3.4 + RFC 8188 with caller-supplied ephemeral key and salt.
     *
     * @internal public only so the RFC 8291 §5 known-answer test can pin both.
     */
    public static function encryptWith(string $payload, string $uaPublicRaw, string $authSecret, OpenSSLAsymmetricKey $ephemeral, string $salt): string
    {
        if (strlen($uaPublicRaw) !== 65 || $uaPublicRaw[0] !== "\x04") {
            throw new RuntimeException('p256dh turi būti 65 baitų nesuspaustas P-256 raktas');
        }
        if (strlen($payload) > self::RECORD_SIZE - 16 - 1 - 86) {
            throw new RuntimeException('Push turinys per ilgas');
        }

        $asPublicRaw = self::rawPublicKey($ephemeral);
        $ecdhSecret = openssl_pkey_derive(self::publicKeyFromRaw($uaPublicRaw), $ephemeral, 32);
        if ($ecdhSecret === false) {
            throw new RuntimeException('ECDH nepavyko: '.openssl_error_string());
        }

        // PRK_key = HMAC(auth_secret, ecdh_secret); IKM = HMAC(PRK_key, key_info || 0x01)
        $keyInfo = "WebPush: info\0".$uaPublicRaw.$asPublicRaw;
        $ikm = hash_hkdf('sha256', $ecdhSecret, 32, $keyInfo, $authSecret);

        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // Single (last) record: plaintext + 0x02 delimiter, no extra padding.
        $tag = '';
        $ciphertext = openssl_encrypt($payload."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ciphertext === false) {
            throw new RuntimeException('AES-GCM nepavyko: '.openssl_error_string());
        }

        $header = $salt.pack('N', self::RECORD_SIZE).chr(strlen($asPublicRaw)).$asPublicRaw;

        return $header.$ciphertext.$tag;
    }

    /**
     * VAPID JWT (ES256) for the push service origin of $endpoint.
     *
     * @internal public for tests.
     */
    public function vapidJwt(string $endpoint, ?int $now = null): string
    {
        $parts = parse_url($endpoint);
        $aud = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        $header = self::b64urlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64urlEncode(json_encode([
            'aud' => $aud,
            'exp' => ($now ?? time()) + 12 * 3600,
            'sub' => $this->subject,
        ], JSON_UNESCAPED_SLASHES));

        $signingInput = $header.'.'.$claims;
        if (! openssl_sign($signingInput, $der, $this->vapidPrivateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('VAPID parašas nepavyko: '.openssl_error_string());
        }

        return $signingInput.'.'.self::b64urlEncode(self::derToRawSignature($der));
    }

    /**
     * New VAPID key pair: public = base64url of the raw 65-byte point (what
     * the browser's applicationServerKey wants), private = PEM.
     *
     * @return array{public: string, private: string}
     */
    public static function generateVapidKeys(): array
    {
        $key = self::newEcKey();
        $public = self::rawPublicKey($key);
        $d = str_pad(openssl_pkey_get_details($key)['ec']['d'], 32, "\0", STR_PAD_LEFT);

        // Build the SEC1 PEM by hand: openssl_pkey_export() also wants openssl.cnf.
        $der = hex2bin('30770201010420').$d
            .hex2bin('a00a06082a8648ce3d030107')
            .hex2bin('a144034200').$public;

        return ['public' => self::b64urlEncode($public), 'private' => self::pem('EC PRIVATE KEY', $der)];
    }

    private static function pem(string $label, string $der): string
    {
        return "-----BEGIN {$label}-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END {$label}-----\n";
    }

    /** Uncompressed point 0x04 || X || Y of an EC key. */
    public static function rawPublicKey(OpenSSLAsymmetricKey $key): string
    {
        $ec = openssl_pkey_get_details($key)['ec'] ?? null;
        if (! $ec) {
            throw new RuntimeException('Raktas nėra EC');
        }

        return "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    }

    /** Wrap a raw 65-byte P-256 point in SPKI so openssl can load it. */
    public static function publicKeyFromRaw(string $raw): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_get_public(self::pem('PUBLIC KEY', hex2bin(self::P256_SPKI_PREFIX).$raw));
        if ($key === false) {
            throw new RuntimeException('Netinkamas P-256 viešasis raktas');
        }

        return $key;
    }

    /** ECDSA DER SEQUENCE{INTEGER r, INTEGER s} -> 64-byte r||s as JWS ES256 requires. */
    public static function derToRawSignature(string $der): string
    {
        $pos = 0;
        if (ord($der[$pos++]) !== 0x30) {
            throw new RuntimeException('Netinkamas DER parašas');
        }
        self::derLength($der, $pos);

        $out = '';
        for ($i = 0; $i < 2; $i++) {
            if (ord($der[$pos++]) !== 0x02) {
                throw new RuntimeException('Netinkamas DER parašas');
            }
            $len = self::derLength($der, $pos);
            $int = ltrim(substr($der, $pos, $len), "\0"); // drop sign-padding zero(s)
            $pos += $len;
            if (strlen($int) > 32) {
                throw new RuntimeException('Netinkamas DER parašas');
            }
            $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
        }

        return $out;
    }

    private static function derLength(string $der, int &$pos): int
    {
        $len = ord($der[$pos++]);
        if ($len & 0x80) {
            $n = $len & 0x7F;
            $len = 0;
            for ($i = 0; $i < $n; $i++) {
                $len = ($len << 8) | ord($der[$pos++]);
            }
        }

        return $len;
    }

    public static function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Netinkamas base64url');
        }

        return $decoded;
    }
}
