<?php

namespace Tests\Unit;

use App\Support\SimpleXlsx;
use App\Support\SpreadsheetFile;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class SimpleXlsxTest extends TestCase
{
    public function test_written_file_can_be_read_back_with_types_preserved(): void
    {
        $contents = SimpleXlsx::write([
            ['code', 'name', 'amount', 'phone'],
            ['A-1', 'Kabel, "NYM" & <klem>', 1500.25, '0812'],
            ['A-2', '', 3, ''],
        ]);

        $this->assertStringStartsWith("PK\x03\x04", $contents);
        $this->assertSame([
            ['code', 'name', 'amount', 'phone'],
            ['A-1', 'Kabel, "NYM" & <klem>', '1500.25', '0812'],
            ['A-2', '', '3'],
        ], SimpleXlsx::readString($contents));
    }

    public function test_reads_excel_style_shared_strings_dates_and_gaps(): void
    {
        $sheet = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="D1" t="s"><v>2</v></c></row>'
            .'<row r="3"><c r="A3" t="s"><v>3</v></c><c r="B3" s="1"><v>46037</v></c><c r="C3"><v>3.3000000000000003</v></c><c r="D3" t="b"><v>1</v></c></row>'
            .'</sheetData></worksheet>';
        $sharedStrings = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<si><t>name</t></si><si><t>date</t></si><si><t>active</t></si>'
            .'<si><r><t>Bor </t></r><r><rPr><b/></rPr><t>Makita</t></r></si>'
            .'</sst>';
        $styles = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="14" applyNumberFormat="1"/></cellXfs>'
            .'</styleSheet>';
        $workbook = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Isi" sheetId="1" r:id="rId7"/></sheets></workbook>';
        $relations = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId7" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/isi.xml"/>'
            .'</Relationships>';

        $path = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relations);
        $zip->addFromString('xl/worksheets/isi.xml', $sheet);
        $zip->addFromString('xl/sharedStrings.xml', $sharedStrings);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->close();

        try {
            $this->assertSame([
                ['name', 'date', '', 'active'],
                [],
                ['Bor Makita', '2026-01-15', '3.3', 'TRUE'],
            ], SimpleXlsx::read($path));
        } finally {
            unlink($path);
        }
    }

    public function test_csv_delimiter_is_detected(): void
    {
        $this->assertSame(
            [['a', 'b,c'], ['1', '2,5']],
            SpreadsheetFile::csvRows("\xEF\xBB\xBFa;b,c\r\n1;\"2,5\"\r\n")
        );
        $this->assertSame([['a', 'b'], ['1', '2']], SpreadsheetFile::csvRows("a,b\n1,2\n"));
        $this->assertSame([['a', 'b'], ['1', '2']], SpreadsheetFile::csvRows("a\tb\n1\t2\n"));
    }
}
