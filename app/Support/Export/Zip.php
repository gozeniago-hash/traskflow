<?php

namespace App\Support\Export;

/**
 * Minimal ZIP writer (deflate when zlib is available, otherwise stored).
 * Enough for building .xlsx files without extra PHP extensions or packages.
 */
class Zip
{
    /** @var array<int, array{name: string, data: string, crc: int, size: int, method: int, offset: int}> */
    protected array $entries = [];

    protected string $body = '';

    public function add(string $name, string $data): static
    {
        $crc = crc32($data);
        $size = strlen($data);
        $method = 0;
        if (function_exists('gzdeflate')) {
            $deflated = gzdeflate($data, 6);
            if ($deflated !== false && strlen($deflated) < $size) {
                [$data, $method] = [$deflated, 8];
            }
        }

        // DOS time/date for 1980-01-01 00:00 keeps output deterministic.
        [$time, $date] = [0, 0x21];
        $header = pack('VvvvvvVVVvv', 0x04034B50, 20, 0x0800, $method, $time, $date, $crc, strlen($data), $size, strlen($name), 0);

        $this->entries[] = ['name' => $name, 'crc' => $crc, 'size' => $size, 'csize' => strlen($data), 'method' => $method, 'offset' => strlen($this->body)];
        $this->body .= $header.$name.$data;

        return $this;
    }

    public function output(): string
    {
        $central = '';
        foreach ($this->entries as $e) {
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0x0800, $e['method'], 0, 0x21, $e['crc'], $e['csize'], $e['size'], strlen($e['name']), 0, 0, 0, 0, 0, $e['offset']).$e['name'];
        }
        $end = pack('VvvvvVVv', 0x06054B50, 0, 0, count($this->entries), count($this->entries), strlen($central), strlen($this->body), 0);

        return $this->body.$central.$end;
    }
}
