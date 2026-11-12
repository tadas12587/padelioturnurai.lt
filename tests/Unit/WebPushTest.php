<?php

namespace Tests\Unit;

use App\Services\Push\WebPush;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    /** RFC 8291 §5 / Appendix A test vectors. */
    private const RFC_PLAINTEXT = 'V2hlbiBJIGdyb3cgdXAsIEkgd2FudCB0byBiZSBhIHdhdGVybWVsb24';

    private const RFC_AS_PRIVATE = 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw';

    private const RFC_AS_PUBLIC = 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8';

    private const RFC_UA_PRIVATE = 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94';

    private const RFC_UA_PUBLIC = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4';

    private const RFC_SALT = 'DGv6ra1nlYgDCS1FRnbzlw';

    private const RFC_AUTH = 'BTBZMqHH6r4Tts7J_aSIgg';

    private const RFC_MESSAGE = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27ml'
        .'mlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPT'
        .'pK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN';

    /** Appendix A: header (86 octets) and AES-GCM output, listed separately. */
    private const RFC_HEADER = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8';

    private const RFC_CIPHERTEXT = '8pfeW0KbunFT06SuDKoJH9Ql87S1QUrdirN6GcG7sFz1y1sqLgVi1VhjVkHsUoEsbI_0LpXMuGvnzQ';

    private function privateKeyFromRaw(string $d): OpenSSLAsymmetricKey
    {
        return WebPush::privateKeyFromRaw($d);
    }

    private function newEcKey(): OpenSSLAsymmetricKey
    {
        return WebPush::newEcKey();
    }

    /** Receiver (browser) side of RFC 8291, independent of the sender code paths. */
    private function decrypt(string $body, OpenSSLAsymmetricKey $uaPrivate, string $authSecret): string
    {
        $salt = substr($body, 0, 16);
        $rs = unpack('N', substr($body, 16, 4))[1];
        $idlen = ord($body[20]);
        $asPublic = substr($body, 21, $idlen);
        $record = substr($body, 21 + $idlen);
        $this->assertSame(4096, $rs);
        $this->assertSame(65, $idlen);

        $uaPublic = WebPush::rawPublicKey($uaPrivate);
        $ecdh = openssl_pkey_derive(WebPush::publicKeyFromRaw($asPublic), $uaPrivate, 32);

        $prkKey = hash_hmac('sha256', $ecdh, $authSecret, true);
        $ikm = substr(hash_hmac('sha256', "WebPush: info\0".$uaPublic.$asPublic."\x01", $prkKey, true), 0, 32);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);

        $padded = openssl_decrypt(substr($record, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($record, -16));
        $this->assertNotFalse($padded, 'AES-GCM auth failed');

        $padded = rtrim($padded, "\0");
        $this->assertSame("\x02", substr($padded, -1), 'last-record delimiter');

        return substr($padded, 0, -1);
    }

    public function test_rfc8291_known_answer(): void
    {
        $asPrivate = $this->privateKeyFromRaw(WebPush::b64urlDecode(self::RFC_AS_PRIVATE));
        $this->assertSame(self::RFC_AS_PUBLIC, WebPush::b64urlEncode(WebPush::rawPublicKey($asPrivate)));

        $body = WebPush::encryptWith(
            WebPush::b64urlDecode(self::RFC_PLAINTEXT),
            WebPush::b64urlDecode(self::RFC_UA_PUBLIC),
            WebPush::b64urlDecode(self::RFC_AUTH),
            $asPrivate,
            WebPush::b64urlDecode(self::RFC_SALT),
        );

        $this->assertSame(self::RFC_MESSAGE, WebPush::b64urlEncode($body));
        $this->assertSame(WebPush::b64urlDecode(self::RFC_HEADER).WebPush::b64urlDecode(self::RFC_CIPHERTEXT), $body);

        // And the RFC's own receiver key can read it back.
        $uaPrivate = $this->privateKeyFromRaw(WebPush::b64urlDecode(self::RFC_UA_PRIVATE));
        $this->assertSame('When I grow up, I want to be a watermelon', $this->decrypt($body, $uaPrivate, WebPush::b64urlDecode(self::RFC_AUTH)));
    }

    public function test_round_trip_with_fresh_browser_keys(): void
    {
        $vapid = WebPush::generateVapidKeys();
        $push = new WebPush($vapid['public'], $vapid['private'], 'mailto:test@example.com');

        $browser = $this->newEcKey();
        $auth = random_bytes(16);
        $payload = json_encode(['title' => 'Kortas 2', 'body' => 'Jūsų mačas po 10 min – ąčęėįšųūž']);

        $body = $push->encrypt($payload, WebPush::rawPublicKey($browser), $auth);

        $this->assertSame($payload, $this->decrypt($body, $browser, $auth));
        // Fresh salt/ephemeral key each time.
        $this->assertNotSame($body, $push->encrypt($payload, WebPush::rawPublicKey($browser), $auth));
    }

    public function test_vapid_jwt_signature_and_claims(): void
    {
        $vapid = WebPush::generateVapidKeys();
        $push = new WebPush($vapid['public'], $vapid['private'], 'https://padelioturnyrai.lt');

        $now = 1_800_000_000;
        $jwt = $push->vapidJwt('https://fcm.googleapis.com/fcm/send/abc:def?x=1', $now);
        [$h, $c, $s] = explode('.', $jwt);

        $this->assertSame(['typ' => 'JWT', 'alg' => 'ES256'], json_decode(WebPush::b64urlDecode($h), true));
        $claims = json_decode(WebPush::b64urlDecode($c), true);
        $this->assertSame('https://fcm.googleapis.com', $claims['aud']);
        $this->assertSame($now + 12 * 3600, $claims['exp']);
        $this->assertSame('https://padelioturnyrai.lt', $claims['sub']);

        $raw = WebPush::b64urlDecode($s);
        $this->assertSame(64, strlen($raw));

        $publicKey = WebPush::publicKeyFromRaw(WebPush::b64urlDecode($vapid['public']));
        $this->assertSame(1, openssl_verify("{$h}.{$c}", $this->rawToDer($raw), $publicKey, OPENSSL_ALGO_SHA256));
    }

    public function test_der_to_raw_handles_short_and_padded_integers(): void
    {
        // r with a sign-padding 0x00 (33 bytes), s shorter than 32 bytes.
        $r = "\x00".str_repeat("\xff", 32);
        $s = str_repeat("\x11", 30);
        $der = "\x30".chr(4 + 33 + 30)."\x02\x21".$r."\x02\x1e".$s;

        $raw = WebPush::derToRawSignature($der);

        $this->assertSame(str_repeat("\xff", 32)."\0\0".str_repeat("\x11", 30), $raw);
        $this->assertSame($der, $this->rawToDer($raw));

        // Many signatures: some r/s will have the high bit set or leading zeros.
        $key = $this->newEcKey();
        $pub = WebPush::publicKeyFromRaw(WebPush::rawPublicKey($key));
        for ($i = 0; $i < 50; $i++) {
            openssl_sign("m{$i}", $sig, $key, OPENSSL_ALGO_SHA256);
            $raw = WebPush::derToRawSignature($sig);
            $this->assertSame(64, strlen($raw));
            $this->assertSame(1, openssl_verify("m{$i}", $this->rawToDer($raw), $pub, OPENSSL_ALGO_SHA256));
        }
    }

    public function test_send_headers_and_gone(): void
    {
        $vapid = WebPush::generateVapidKeys();
        $push = new WebPush($vapid['public'], $vapid['private'], 'mailto:test@example.com');
        $browser = $this->newEcKey();
        $sub = [
            'endpoint' => 'https://push.example.net/wpush/v2/token123',
            'keys' => [
                'p256dh' => WebPush::b64urlEncode(WebPush::rawPublicKey($browser)),
                'auth' => WebPush::b64urlEncode(random_bytes(16)),
            ],
        ];

        Http::fake([
            'push.example.net/wpush/v2/token123' => Http::response('', 201),
            'push.example.net/*' => Http::response('expired', 410),
        ]);

        $ok = $push->send($sub, '{"t":"hi"}', 300, 'normal');
        $this->assertSame(['status' => 201, 'ok' => true, 'gone' => false, 'body' => ''], $ok);

        Http::assertSent(function (Request $request) use ($vapid, $sub) {
            return $request->method() === 'POST'
                && $request->url() === $sub['endpoint']
                && str_starts_with($request->header('Authorization')[0], 'vapid t=')
                && str_ends_with($request->header('Authorization')[0], ', k='.$vapid['public'])
                && $request->header('Content-Encoding')[0] === 'aes128gcm'
                && $request->header('Content-Type')[0] === 'application/octet-stream'
                && $request->header('TTL')[0] === '300'
                && $request->header('Urgency')[0] === 'normal'
                && strlen($request->body()) === 86 + strlen('{"t":"hi"}') + 1 + 16;
        });

        $sub['endpoint'] = 'https://push.example.net/wpush/v2/old';
        $gone = $push->send($sub, 'x');
        $this->assertSame(410, $gone['status']);
        $this->assertFalse($gone['ok']);
        $this->assertTrue($gone['gone']);
    }

    /** Raw r||s -> DER, mirroring what a JWS verifier does. */
    private function rawToDer(string $raw): string
    {
        $int = function (string $b): string {
            $b = ltrim($b, "\0");
            if ($b === '' || ord($b[0]) & 0x80) {
                $b = "\0".$b;
            }

            return "\x02".chr(strlen($b)).$b;
        };
        $seq = $int(substr($raw, 0, 32)).$int(substr($raw, 32));

        return "\x30".chr(strlen($seq)).$seq;
    }
}
