<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reads uploaded Excel (.xlsx) or CSV files into rows and streams .xlsx downloads.
 * CSV files may use comma, semicolon (Excel with Indonesian locale) or tab as delimiter.
 */
final class SpreadsheetFile
{
    /**
     * @var list<string>
     */
    public const UPLOAD_RULES = ['required', 'file', 'extensions:xlsx,csv,txt', 'max:2048'];

    /**
     * @return array<string, string>
     */
    public static function uploadMessages(): array
    {
        return [
            'file.required' => 'Pilih file Excel (.xlsx) atau CSV.',
            'file.file' => 'File upload tidak valid.',
            'file.extensions' => 'File harus berformat Excel (.xlsx) atau CSV. File .xls lama perlu disimpan ulang sebagai .xlsx.',
            'file.max' => 'Ukuran file maksimal 2 MB.',
        ];
    }

    /**
     * @return list<list<string>>
     */
    public static function rows(UploadedFile|string $source): array
    {
        if ($source instanceof UploadedFile) {
            $path = (string) $source->getRealPath();

            if (SimpleXlsx::isXlsx($path)) {
                return SimpleXlsx::read($path);
            }

            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new InvalidArgumentException('File tidak dapat dibaca.');
            }

            return self::csvRows($contents);
        }

        return str_starts_with($source, "PK\x03\x04")
            ? SimpleXlsx::readString($source)
            : self::csvRows($source);
    }

    /**
     * @return list<list<string>>
     */
    public static function csvRows(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        $delimiter = self::detectDelimiter($contents);
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new InvalidArgumentException('File CSV tidak dapat dibaca.');
        }

        fwrite($handle, $contents);
        rewind($handle);
        $rows = [];

        while (($data = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $rows[] = $data === [null] ? [] : array_map(fn ($value) => (string) $value, $data);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function download(array $rows, string $filename, string $sheetName = 'Data'): StreamedResponse
    {
        $contents = SimpleXlsx::write($rows, $sheetName);

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $filename, [
            'Content-Type' => SimpleXlsx::MIME,
        ]);
    }

    /**
     * Picks the delimiter that occurs most often (outside quotes) in the header line.
     */
    private static function detectDelimiter(string $contents): string
    {
        $firstLine = strtok(ltrim($contents, "\r\n"), "\r\n") ?: '';
        $unquoted = preg_replace('/"[^"]*"/', '', $firstLine) ?? $firstLine;
        $counts = [
            ';' => substr_count($unquoted, ';'),
            ',' => substr_count($unquoted, ','),
            "\t" => substr_count($unquoted, "\t"),
        ];
        arsort($counts);

        return reset($counts) > 0 ? (string) array_key_first($counts) : ',';
    }

    /**
     * Accepts Indonesian-formatted numbers from CSV: "150000", "150.000", "Rp 150.000", "3,5" and "150.000,50".
     */
    public static function normalizeNumber(string $value): string
    {
        $value = str_replace(['Rp', 'rp', 'RP', ' '], '', trim($value));

        if (str_contains($value, ',')) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $value) === 1) {
            return str_replace('.', '', $value);
        }

        return $value;
    }

    /**
     * @param  list<string|null>  $row
     */
    public static function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
