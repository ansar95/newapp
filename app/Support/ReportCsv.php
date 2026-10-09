<?php

namespace App\Support;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCsv
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function download(string $filename, array $headers, array $rows): StreamedResponse
    {
        $csv = self::toCsv(array_merge([$headers], $rows));

        return response()->streamDownload(
            function () use ($csv): void {
                echo $csv;
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new InvalidArgumentException('Tidak dapat menulis CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                fn ($value) => $value === null ? '' : (string) $value,
                $row
            ));
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }
}
