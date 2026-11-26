<?php
/**
 * Reads the first sheet of an .xlsx file with no libraries and without the PHP zip extension.
 * rows() returns [excelRowNumber => [cell text, ...]]. Throws RuntimeException with a plain message on a bad file.
 */
class XlsxReader
{
    private const MAX_PART = 10485760;   // 10 MB per unpacked part, guards against zip bombs
    private const MAX_COLS = 60;

    public static function rows($bytes, $maxRows = 1000)
    {
        $parts = self::unzip($bytes);
        $sheetPath = self::firstSheetPath($parts);
        if (!isset($parts[$sheetPath])) throw new RuntimeException('This Excel file has no sheet to read.');
        $shared = isset($parts['xl/sharedStrings.xml']) ? self::sharedStrings($parts['xl/sharedStrings.xml']) : [];

        $doc = self::dom($parts[$sheetPath]);
        $out = [];
        $seq = 0;
        foreach ($doc->getElementsByTagName('row') as $row) {
            $seq++;
            $r = (int)$row->getAttribute('r') ?: $seq;
            $cells = [];
            $next = 0;
            foreach ($row->getElementsByTagName('c') as $c) {
                $ref = $c->getAttribute('r');
                $idx = $ref !== '' ? self::colIndex($ref) : $next;
                $next = $idx + 1;
                if ($idx >= self::MAX_COLS) continue;
                $cells[$idx] = self::cellText($c, $shared);
            }
            if (!$cells) continue;
            $line = [];
            for ($i = 0, $n = max(array_keys($cells)) + 1; $i < $n; $i++) $line[] = $cells[$i] ?? '';
            $out[$r] = $line;
            if (count($out) > $maxRows + 1) throw new RuntimeException("The file has more than $maxRows rows. Split it into smaller files.");
        }
        return $out;
    }

    private static function cellText(DOMElement $c, array $shared)
    {
        $t = $c->getAttribute('t');
        $v = '';
        foreach ($c->childNodes as $n) {
            if ($n->nodeName === 'v') $v = $n->textContent;
            if ($n->nodeName === 'is') $v = self::richText($n);
        }
        if ($t === 's') return $shared[(int)$v] ?? '';
        if ($t === 'b') return $v === '1' ? '1' : '0';
        if ($t === 'e') return '';
        if ($t === 'inlineStr' || $t === 'str') return trim($v);
        $v = trim($v);
        if ($v !== '' && preg_match('/^-?\d+(\.\d+)?([eE][+-]?\d+)?$/', $v) && preg_match('/[.eE]/', $v)) {   // 1.00000000006E11 -> 100000000006
            $f = (float)$v;
            $v = (abs($f) < 1e15 && $f == floor($f)) ? sprintf('%.0f', $f) : rtrim(rtrim(sprintf('%.10F', $f), '0'), '.');
        }
        return $v;
    }

    private static function richText(DOMNode $si)
    {
        $s = '';
        foreach ($si->childNodes as $n) {
            if ($n->nodeName === 't') $s .= $n->textContent;
            elseif ($n->nodeName === 'r') foreach ($n->childNodes as $t) if ($t->nodeName === 't') $s .= $t->textContent;
        }
        return $s;
    }

    private static function sharedStrings($xml)
    {
        $out = [];
        foreach (self::dom($xml)->getElementsByTagName('si') as $si) $out[] = self::richText($si);
        return $out;
    }

    private static function firstSheetPath(array $parts)
    {
        $default = 'xl/worksheets/sheet1.xml';
        if (!isset($parts['xl/workbook.xml']) || !isset($parts['xl/_rels/workbook.xml.rels'])) return $default;
        $rid = null;
        foreach (self::dom($parts['xl/workbook.xml'])->getElementsByTagName('sheet') as $s) {
            $rid = $s->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            break;
        }
        if (!$rid) return $default;
        foreach (self::dom($parts['xl/_rels/workbook.xml.rels'])->getElementsByTagName('Relationship') as $r) {
            if ($r->getAttribute('Id') === $rid) {
                $t = ltrim($r->getAttribute('Target'), '/');
                return strpos($t, 'xl/') === 0 ? $t : 'xl/' . $t;
            }
        }
        return $default;
    }

    private static function colIndex($ref)
    {
        preg_match('/^([A-Za-z]+)/', $ref, $m);
        $n = 0;
        foreach (str_split(strtoupper($m[1] ?? 'A')) as $ch) $n = $n * 26 + (ord($ch) - 64);
        return $n - 1;
    }

    private static function dom($xml)
    {
        $d = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $d->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors(); libxml_use_internal_errors($prev);
        if (!$ok) throw new RuntimeException('This does not look like a valid Excel (.xlsx) file.');
        return $d;
    }

    /** Unpacks the zip into [name => contents], for the few parts that are needed. */
    private static function unzip($b)
    {
        $len = strlen($b);
        $eocd = strrpos($b, "PK\x05\x06");
        if ($len < 22 || $eocd === false) throw new RuntimeException('This does not look like an Excel (.xlsx) file. Save it as "Excel Workbook (.xlsx)" and try again.');
        $e = unpack('vdisk/vcdDisk/ventriesDisk/ventries/Vsize/Voffset', substr($b, $eocd + 4, 18));
        $want = ['xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/sharedStrings.xml'];
        $pos = $e['offset'];
        $parts = [];
        for ($i = 0; $i < $e['entries']; $i++) {
            if (substr($b, $pos, 4) !== "PK\x01\x02") break;
            $h = unpack('vmethod/x4/Vcrc/Vcsize/Vusize/vnlen/velen/vclen', substr($b, $pos + 10, 28));
            $off = unpack('Voff', substr($b, $pos + 42, 4))['off'];
            $name = substr($b, $pos + 46, $h['nlen']);
            $pos += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
            if (!in_array($name, $want, true) && !preg_match('#^xl/worksheets/[^/]+\.xml$#', $name)) continue;
            if ($h['usize'] > self::MAX_PART) throw new RuntimeException('The Excel file is too large to read. Remove extra data and try again.');
            $lh = unpack('vnlen/velen', substr($b, $off + 26, 4));
            $data = substr($b, $off + 30 + $lh['nlen'] + $lh['elen'], $h['csize']);
            if ($h['method'] === 8) $data = @gzinflate($data, self::MAX_PART);
            elseif ($h['method'] !== 0) $data = false;
            if ($data === false) throw new RuntimeException('This Excel file could not be opened. Save it again as "Excel Workbook (.xlsx)".');
            $parts[$name] = $data;
        }
        return $parts;
    }
}
