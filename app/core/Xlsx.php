<?php
/** Minimal Excel (.xlsx) writer with no libraries. One header row per sheet (bold, frozen), automatic column widths. */
class Xlsx
{
    /** $sheets: ['Sheet name' => [[header cells], [row cells], ...]]. Sends the file to the browser. */
    public static function download($filename, array $sheets)
    {
        $zip = new XlsxZip();   // own zip writer, so the PHP zip extension is not needed
        $n = count($sheets);
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $i = 0;
        foreach ($sheets as $name => $rows) {
            $i++;
            $ct   .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $wb   .= '<sheet name="' . self::x(self::sheetName($name, $i)) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
            $zip->addFromString("xl/worksheets/sheet$i.xml", self::sheet($rows));
        }
        $rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $zip->addFromString('[Content_Types].xml', $ct . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $wb . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F6F54"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $bytes = $zip->bytes();

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    private static function sheet(array $rows)
    {
        $widths = [];
        $xml = '';
        foreach ($rows as $r => $cells) {
            $xml .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($cells) as $c => $v) {
                $ref = self::col($c) . ($r + 1);
                $text = self::clean($v);
                $widths[$c] = max($widths[$c] ?? 6, min(60, mb_strlen($text) + 2));
                $style = $r === 0 ? ' s="1"' : '';
                if ($r > 0 && (is_int($v) || is_float($v))) $xml .= '<c r="' . $ref . '"' . $style . '><v>' . $v . '</v></c>';
                else $xml .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . self::x($text) . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        $cols = '';
        foreach ($widths as $c => $w) $cols .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $w . '" customWidth="1"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
             . ($cols ? "<cols>$cols</cols>" : '') . '<sheetData>' . $xml . '</sheetData></worksheet>';
    }

    private static function col($i) { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; }
    private static function clean($v) { return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$v); }
    private static function x($v) { return htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    private static function sheetName($n, $i) { $n = trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', (string)$n)); return mb_substr($n !== '' ? $n : "Sheet$i", 0, 31); }

    /** If the server has no zip support, send a CSV that Excel also opens. */
    private static function csvFallback($filename, array $rows)
    {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', preg_replace('/\.xlsx$/', '.csv', $filename)) . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $r) fputcsv($out, array_map([self::class, 'clean'], $r));
        fclose($out);
        exit;
    }
}

/** Tiny zip writer (stored, no compression) for the few small files an .xlsx needs. */
class XlsxZip
{
    private $data = '';
    private $dir = '';
    private $count = 0;

    public function addFromString($name, $content)
    {
        $crc = crc32($content);
        $len = strlen($content);
        $offset = strlen($this->data);
        $dosTime = 0; $dosDate = (1 << 5) | 1;   // 1980-01-01; the date is not important
        $this->data .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $dosTime, $dosDate, $crc, $len, $len, strlen($name), 0) . $name . $content;
        $this->dir  .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $dosTime, $dosDate, $crc, $len, $len, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        $this->count++;
    }

    public function bytes()
    {
        return $this->data . $this->dir . pack('VvvvvVVv', 0x06054b50, 0, 0, $this->count, $this->count, strlen($this->dir), strlen($this->data), 0);
    }
}
