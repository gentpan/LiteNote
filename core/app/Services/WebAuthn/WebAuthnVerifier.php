<?php
declare(strict_types=1);

namespace App\Services\WebAuthn;

/**
 * WebAuthn 注册/登录断言校验（ES256 / COSE EC2）。
 */
final class WebAuthnVerifier
{
    private const FLAG_USER_PRESENT = 0x01;
    private const FLAG_ATTESTED_CREDENTIAL_DATA = 0x40;

    /**
     * @return array{credential_id:string,public_key:string,counter:int}
     */
    public static function verifyRegistration(
        array $credential,
        string $expectedChallenge,
        string $rpId,
        string $origin
    ): array {
        $credentialId = (string) ($credential['id'] ?? '');
        $response = $credential['response'] ?? null;
        if ($credentialId === '' || !is_array($response)) {
            throw new \InvalidArgumentException('Passkey 凭证数据无效');
        }

        $clientDataJson = self::decodeBase64Url((string) ($response['clientDataJSON'] ?? ''));
        $attestationObject = self::decodeBase64Url((string) ($response['attestationObject'] ?? ''));
        if ($clientDataJson === '' || $attestationObject === '') {
            throw new \InvalidArgumentException('Passkey 响应字段缺失');
        }

        self::verifyClientData($clientDataJson, 'webauthn.create', $expectedChallenge, $origin);

        [$attestation,] = CborDecoder::decode($attestationObject);
        if (!is_array($attestation)) {
            throw new \InvalidArgumentException('attestationObject 无效');
        }
        $authData = (string) ($attestation['authData'] ?? '');
        if ($authData === '') {
            throw new \InvalidArgumentException('authData 缺失');
        }

        self::verifyRpIdHash($authData, $rpId);
        $flags = ord($authData[32]);
        if (($flags & self::FLAG_ATTESTED_CREDENTIAL_DATA) === 0) {
            throw new \InvalidArgumentException('缺少 attested credential data');
        }

        $offset = 37;
        $offset += 16; // aaguid
        $credIdLen = unpack('n', substr($authData, $offset, 2))[1];
        $offset += 2;
        $rawCredentialId = substr($authData, $offset, $credIdLen);
        $offset += $credIdLen;

        $coseBytes = substr($authData, $offset);
        [$coseKey,] = CborDecoder::decode($coseBytes);
        if (!is_array($coseKey)) {
            throw new \InvalidArgumentException('COSE 公钥无效');
        }

        $encodedCredentialId = self::encodeBase64Url($rawCredentialId);
        if (!hash_equals($encodedCredentialId, $credentialId)) {
            throw new \InvalidArgumentException('credential id 不匹配');
        }

        $signCount = self::readSignCount($authData);

        return [
            'credential_id' => $credentialId,
            'public_key' => 'cose:' . base64_encode($coseBytes),
            'counter' => $signCount,
        ];
    }

    /**
     * @return array{counter:int}
     */
    public static function verifyAssertion(
        array $credential,
        string $expectedChallenge,
        string $rpId,
        string $origin,
        string $storedPublicKey,
        int $storedCounter
    ): array {
        $response = $credential['response'] ?? null;
        if (!is_array($response)) {
            throw new \InvalidArgumentException('Passkey 凭证数据无效');
        }

        $clientDataJson = self::decodeBase64Url((string) ($response['clientDataJSON'] ?? ''));
        $authData = self::decodeBase64Url((string) ($response['authenticatorData'] ?? ''));
        $signature = self::decodeBase64Url((string) ($response['signature'] ?? ''));
        if ($clientDataJson === '' || $authData === '' || $signature === '') {
            throw new \InvalidArgumentException('Passkey 响应字段缺失');
        }

        self::verifyClientData($clientDataJson, 'webauthn.get', $expectedChallenge, $origin);
        self::verifyRpIdHash($authData, $rpId);

        $flags = ord($authData[32]);
        if (($flags & self::FLAG_USER_PRESENT) === 0) {
            throw new \InvalidArgumentException('用户未确认');
        }

        $signCount = self::readSignCount($authData);
        if ($signCount !== 0 && $signCount <= $storedCounter) {
            throw new \InvalidArgumentException('签名计数器无效');
        }

        $clientDataHash = hash('sha256', $clientDataJson, true);
        $signedData = $authData . $clientDataHash;
        $pem = self::publicKeyPem($storedPublicKey);
        if (!openssl_verify($signedData, $signature, $pem, OPENSSL_ALGO_SHA256)) {
            throw new \InvalidArgumentException('Passkey 签名验证失败');
        }

        return ['counter' => $signCount];
    }

    private static function verifyClientData(
        string $clientDataJson,
        string $type,
        string $expectedChallenge,
        string $origin
    ): void {
        $clientData = json_decode($clientDataJson, true);
        if (!is_array($clientData)) {
            throw new \InvalidArgumentException('clientDataJSON 无效');
        }
        if (($clientData['type'] ?? '') !== $type) {
            throw new \InvalidArgumentException('clientData type 无效');
        }
        $challenge = (string) ($clientData['challenge'] ?? '');
        if ($challenge === '' || !hash_equals($expectedChallenge, $challenge)) {
            throw new \InvalidArgumentException('challenge 不匹配');
        }
        if (!hash_equals($origin, (string) ($clientData['origin'] ?? ''))) {
            throw new \InvalidArgumentException('origin 不匹配');
        }
    }

    private static function verifyRpIdHash(string $authData, string $rpId): void
    {
        if (strlen($authData) < 37) {
            throw new \InvalidArgumentException('authData 太短');
        }
        $hash = substr($authData, 0, 32);
        if (!hash_equals(hash('sha256', $rpId, true), $hash)) {
            throw new \InvalidArgumentException('rpIdHash 不匹配');
        }
    }

    private static function readSignCount(string $authData): int
    {
        return unpack('N', substr($authData, 33, 4))[1];
    }

    private static function publicKeyPem(string $storedPublicKey): string
    {
        if (!str_starts_with($storedPublicKey, 'cose:')) {
            throw new \InvalidArgumentException('Passkey 公钥格式无效，请重新绑定');
        }
        $coseBytes = base64_decode(substr($storedPublicKey, 5), true);
        if ($coseBytes === false || $coseBytes === '') {
            throw new \InvalidArgumentException('Passkey 公钥损坏');
        }
        [$coseKey,] = CborDecoder::decode($coseBytes);
        if (!is_array($coseKey)) {
            throw new \InvalidArgumentException('COSE 公钥无效');
        }

        $x = (string) ($coseKey[-2] ?? '');
        $y = (string) ($coseKey[-3] ?? '');
        if ($x === '' || $y === '') {
            throw new \InvalidArgumentException('COSE EC2 坐标缺失');
        }

        $der = self::ecP256PublicKeyDer($x, $y);
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    private static function ecP256PublicKeyDer(string $x, string $y): string
    {
        $x = str_pad($x, 32, "\x00", STR_PAD_LEFT);
        $y = str_pad($y, 32, STR_PAD_LEFT);
        $point = "\x04" . $x . $y;

        $algoOid = hex2bin('301306072a8648ce3d020106082a8648ce3d030107');
        $bitString = "\x03" . chr(strlen($point) + 1) . "\x00" . $point;
        $spki = $algoOid . $bitString;

        return "\x30" . chr(strlen($spki)) . $spki;
    }

    private static function decodeBase64Url(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        return (string) base64_decode($value, true);
    }

    private static function encodeBase64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
