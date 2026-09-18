<?php

namespace App\Services;

/** Bounded CFB directory/FAT inspection; rejects non-Word and encrypted OLE files. */
class LegacyWordInspector
{
    public function inspect(string $bytes): void
    {
        $invalid = fn () => new \RuntimeException('This DOC is damaged, encrypted, or is not a supported Word document. Export an unencrypted DOCX and try again.');
        if (strlen($bytes) < 512 || substr($bytes, 0, 8) !== hex2bin('d0cf11e0a1b11ae1')) throw $invalid();
        $u16 = fn ($offset) => unpack('v', substr($bytes, $offset, 2))[1];
        $u32 = fn ($offset) => unpack('V', substr($bytes, $offset, 4))[1];
        $shift = $u16(30);
        if (! in_array($shift, [9, 12], true) || $u16(28) !== 65534 || $u16(32) !== 6) throw $invalid();
        $size = 1 << $shift;
        $sectors = intdiv(strlen($bytes), $size) - 1;
        $sector = function (int $id) use ($bytes, $size, $sectors, $invalid): string {
            if ($id < 0 || $id >= $sectors) throw $invalid();
            return substr($bytes, ($id + 1) * $size, $size);
        };
        // At the 20 MB upload limit a normal Word file fits the header DIFAT.
        $fatCount = $u32(44);
        if ($fatCount < 1 || $fatCount > 109) throw $invalid();
        $fat = [];
        for ($i = 0; $i < $fatCount; $i++) $fat = array_merge($fat, array_values(unpack('V*', $sector($u32(76 + $i * 4)))));
        $chain = function (int $start, array $table, callable $read, int $limit) use ($invalid): string {
            $result = ''; $seen = []; $id = $start;
            while ($id !== 0xfffffffe) {
                if (isset($seen[$id]) || ! isset($table[$id]) || count($seen) >= $limit) throw $invalid();
                $seen[$id] = true; $result .= $read($id); $id = $table[$id];
            }
            return $result;
        };
        $directory = $chain($u32(48), $fat, $sector, $sectors);
        $word = null; $root = null;
        for ($i = 0; $i + 128 <= strlen($directory); $i += 128) {
            $entry = substr($directory, $i, 128);
            $length = unpack('v', substr($entry, 64, 2))[1];
            if ($length < 2 || $length > 64) continue;
            $name = mb_convert_encoding(substr($entry, 0, $length - 2), 'UTF-8', 'UTF-16LE');
            if (in_array(strtolower($name), ['encryptioninfo', 'encryptedpackage'], true)) throw $invalid();
            if ($name === 'WordDocument' && ord($entry[66]) === 2) $word = $entry;
            if (ord($entry[66]) === 5) $root = $entry;
        }
        if (! $word || ! $root) throw $invalid();
        $start = unpack('V', substr($word, 116, 4))[1];
        $length = unpack('V', substr($word, 120, 4))[1];
        if ($length < 32 || $length > strlen($bytes)) throw $invalid();
        if ($length >= 4096) {
            $header = $sector($start);
        } else {
            $miniFat = array_values(unpack('V*', $chain($u32(60), $fat, $sector, $sectors)));
            $miniStream = $chain(unpack('V', substr($root, 116, 4))[1], $fat, $sector, $sectors);
            if (! isset($miniFat[$start]) || ($start + 1) * 64 > strlen($miniStream)) throw $invalid();
            $header = substr($miniStream, $start * 64, 64);
        }
        if (unpack('v', substr($header, 0, 2))[1] !== 0xa5ec || (unpack('v', substr($header, 10, 2))[1] & 0x8100)) throw $invalid();
    }
}
