<?php

namespace App\Support;

use InvalidArgumentException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal single-sheet .xlsx writer/reader built on ZipArchive + SimpleXML (no spreadsheet library).
 * Reading returns plain strings: numbers without float noise and date-formatted cells as Y-m-d.
 */
final class SimpleXlsx
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * Built-in Excel number formats that represent dates.
     */
    private const DATE_FORMAT_IDS = [14, 15, 16, 17, 22, 27, 30, 36, 45, 46, 47, 50, 57];

    /**
     * The first row is written bold and frozen as the header. int/float values become numeric cells,
     * everything else is stored as text so codes and phone numbers keep their leading zeros.
     *
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function write(array $rows, string $sheetName = 'Data'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new InvalidArgumentException('File Excel tidak dapat dibuat.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml());
        $zip->addFromString('_rels/.rels', self::rootRelsXml());
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml());
        $zip->addFromString('xl/styles.xml', self::stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheetXml($rows));
        $zip->close();

        $contents = (string) file_get_contents($path);
        @unlink($path);

        return $contents;
    }

    /**
     * Rows of the first worksheet; missing cells are returned as empty strings.
     *
     * @return list<list<string>>
     */
    public static function read(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('File Excel tidak dapat dibuka.');
        }

        try {
            $sheetXml = $zip->getFromName(self::firstSheetPath($zip));

            if ($sheetXml === false) {
                throw new InvalidArgumentException('Sheet pertama pada file Excel tidak ditemukan.');
            }

            $sharedStrings = self::sharedStrings($zip);
            $dateStyles = self::dateStyleIndexes($zip);
            $sheet = self::loadXml($sheetXml);
            $rows = [];

            foreach ($sheet->sheetData->row as $rowNode) {
                $cells = [];

                foreach ($rowNode->c as $cell) {
                    $column = self::columnIndex((string) $cell['r']) ?? count($cells);
                    $cells[$column] = self::cellValue($cell, $sharedStrings, $dateStyles);
                }

                if ($cells === []) {
                    $rows[] = [];

                    continue;
                }

                $row = array_fill(0, max(array_keys($cells)) + 1, '');

                foreach ($cells as $column => $value) {
                    $row[$column] = $value;
                }

                $rowNumber = (int) $rowNode['r'];

                while ($rowNumber > 0 && count($rows) < $rowNumber - 1) {
                    $rows[] = [];
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<list<string>>
     */
    public static function readString(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-');
        file_put_contents($path, $contents);

        try {
            return self::read($path);
        } finally {
            @unlink($path);
        }
    }

    public static function isXlsx(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $signature = (string) fread($handle, 4);
        fclose($handle);

        return $signature === "PK\x03\x04";
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    private static function sheetXml(array $rows): string
    {
        $columnCount = max(1, ...array_map('count', $rows ?: [[]]));
        $widths = array_fill(0, $columnCount, 10);

        foreach ($rows as $row) {
            foreach (array_values($row) as $index => $value) {
                $widths[$index] = min(60, max($widths[$index], mb_strlen((string) $value) + 2));
            }
        }

        $cols = '';

        foreach ($widths as $index => $width) {
            $cols .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        $data = '';

        foreach (array_values($rows) as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $style = $rowIndex === 0 ? ' s="1"' : '';
            $data .= '<row r="'.$rowNumber.'">';

            foreach (array_values($row) as $columnIndex => $value) {
                $reference = self::columnLetter($columnIndex).$rowNumber;

                if (is_int($value) || is_float($value)) {
                    $data .= '<c r="'.$reference.'"'.$style.'><v>'.$value.'</v></c>';
                } elseif ($value !== null && $value !== '') {
                    $data .= '<c r="'.$reference.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'.self::escape((string) $value).'</t></is></c>';
                }
            }

            $data .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="'.self::MAIN_NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols>'
            .'<sheetData>'.$data.'</sheetData>'
            .'</worksheet>';
    }

    /**
     * @param  list<string>  $sharedStrings
     * @param  array<int, true>  $dateStyles
     */
    private static function cellValue(SimpleXMLElement $cell, array $sharedStrings, array $dateStyles): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            return self::richText($cell->is);
        }

        $raw = (string) $cell->v;

        if ($type === 's') {
            return $sharedStrings[(int) $raw] ?? '';
        }

        if ($type === 'b') {
            return $raw === '1' ? 'TRUE' : 'FALSE';
        }

        if ($type === 'str' || $type === 'e' || $raw === '' || ! is_numeric($raw)) {
            return $raw;
        }

        if (isset($dateStyles[(int) $cell['s']])) {
            return gmdate('Y-m-d', (int) round(((float) $raw - 25569) * 86400));
        }

        $number = round((float) $raw, 8);

        return floor($number) === $number && abs($number) < 1e15
            ? (string) (int) $number
            : (string) $number;
    }

    private static function richText(?SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }

        if (isset($node->t)) {
            return (string) $node->t;
        }

        $text = '';

        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $strings = [];

        foreach (self::loadXml($xml)->si as $item) {
            $strings[] = self::richText($item);
        }

        return $strings;
    }

    /**
     * @return array<int, true> cellXfs indexes whose number format is a date
     */
    private static function dateStyleIndexes(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');

        if ($xml === false) {
            return [];
        }

        $styles = self::loadXml($xml);
        $customDateFormats = [];

        foreach ($styles->numFmts->numFmt ?? [] as $format) {
            $code = strtolower(preg_replace('/"[^"]*"|\[[^\]]*\]/', '', (string) $format['formatCode']) ?? '');

            if (preg_match('/[dy]/', $code) === 1) {
                $customDateFormats[(int) $format['numFmtId']] = true;
            }
        }

        $indexes = [];
        $position = 0;

        foreach ($styles->cellXfs->xf ?? [] as $xf) {
            $formatId = (int) $xf['numFmtId'];

            if (in_array($formatId, self::DATE_FORMAT_IDS, true) || isset($customDateFormats[$formatId])) {
                $indexes[$position] = true;
            }

            $position++;
        }

        return $indexes;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relations = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $relations === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $firstSheet = self::loadXml($workbook)->sheets->sheet[0] ?? null;
        $relationId = $firstSheet?->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? null;

        foreach (self::loadXml($relations)->Relationship as $relationship) {
            if ((string) $relationship['Id'] === (string) $relationId) {
                $target = ltrim((string) $relationship['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function loadXml(string $xml): SimpleXMLElement
    {
        $element = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);

        if ($element === false) {
            throw new InvalidArgumentException('File Excel rusak atau tidak valid.');
        }

        return $element;
    }

    private static function columnIndex(string $reference): ?int
    {
        if (preg_match('/^([A-Z]+)\d*$/i', $reference, $matches) !== 1) {
            return null;
        }

        $index = 0;

        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private static function columnLetter(int $index): string
    {
        $letters = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letters = chr(65 + (($index - 1) % 26)).$letters;
        }

        return $letters;
    }

    private static function escape(string $value): string
    {
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private static function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="'.self::MAIN_NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::escape(mb_substr($sheetName, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private static function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="'.self::MAIN_NS.'">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'</styleSheet>';
    }
}
