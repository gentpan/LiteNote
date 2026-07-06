<?php
declare(strict_types=1);

namespace App\Services\WebAuthn;

/**
 * 精简 CBOR 解码器，供 WebAuthn attestation / COSE 公钥解析使用。
 */
final class CborDecoder
{
    /**
     * @return array{0:mixed,1:int}
     */
    public static function decode(string $data, int $offset = 0): array
    {
        if ($offset >= strlen($data)) {
            throw new \InvalidArgumentException('CBOR 数据不完整');
        }

        $initial = ord($data[$offset]);
        $major = $initial >> 5;
        $info = $initial & 0x1f;
        $offset++;

        if ($major === 7) {
            return match ($info) {
                20 => [false, $offset],
                21 => [true, $offset],
                22 => [null, $offset],
                default => throw new \InvalidArgumentException('不支持的 CBOR 简单值'),
            };
        }

        [$length, $offset] = self::readLength($data, $offset, $info);

        return match ($major) {
            0 => [$length, $offset],
            1 => [-1 - $length, $offset],
            2 => [substr($data, $offset, $length), $offset + $length],
            3 => [substr($data, $offset, $length), $offset + $length],
            4 => self::decodeArray($data, $offset, $length),
            5 => self::decodeMap($data, $offset, $length),
            default => throw new \InvalidArgumentException('不支持的 CBOR 主类型'),
        };
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function readLength(string $data, int $offset, int $info): array
    {
        if ($info < 24) {
            return [$info, $offset];
        }
        if ($info === 24) {
            return [ord($data[$offset]), $offset + 1];
        }
        if ($info === 25) {
            $len = unpack('n', substr($data, $offset, 2))[1];
            return [$len, $offset + 2];
        }
        if ($info === 26) {
            $len = unpack('N', substr($data, $offset, 4))[1];
            return [$len, $offset + 4];
        }
        throw new \InvalidArgumentException('CBOR 长度字段过大');
    }

    /**
     * @return array{0:list<mixed>,1:int}
     */
    private static function decodeArray(string $data, int $offset, int $length): array
    {
        $items = [];
        for ($i = 0; $i < $length; $i++) {
            [$value, $offset] = self::decode($data, $offset);
            $items[] = $value;
        }
        return [$items, $offset];
    }

    /**
     * @return array{0:array<int|string,mixed>,1:int}
     */
    private static function decodeMap(string $data, int $offset, int $length): array
    {
        $map = [];
        for ($i = 0; $i < $length; $i++) {
            [$key, $offset] = self::decode($data, $offset);
            [$value, $offset] = self::decode($data, $offset);
            $map[is_int($key) ? $key : (string) $key] = $value;
        }
        return [$map, $offset];
    }
}
