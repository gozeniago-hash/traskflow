<?php

namespace App\Support\Export;

/**
 * Small .xlsx builder: one sheet, a title block, a bold header row, typed columns and
 * optional bold group/total rows. Dates are real Excel dates and hours real numbers,
 * so the file sorts, filters and sums correctly in Excel, Numbers and Google Sheets.
 *
 * Columns: [['title' => 'Date', 'type' => 'date|text|hours|number', 'width' => 12], …]
 * Rows:    [['cells' => [...], 'style' => 'normal|group|total'], …]
 */
class Xlsx
{
    protected const STYLE = [
        // [normal, bold] style index per column type; see styles() for the cellXfs order.
        'text' => [0, 1], 'date' => [2, 3], 'hours' => [4, 5], 'number' => [6, 7],
    ];

    protected const TITLE = 8;
    protected const SUBTITLE = 9;
    protected const HEADER = 10;

    public function __construct(
        protected string $sheetName,
        protected array $columns,
        protected array $rows,
        protected ?string $title = null,
        protected array $subtitleLines = [],
    ) {
    }

    public function render(): string
    {
        return (new Zip)
            ->add('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>')
            ->add('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>')
            ->add('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="'.$this->esc($this->safeSheetName()).'" sheetId="1" r:id="rId1"/></sheets></workbook>')
            ->add('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>')
            ->add('xl/styles.xml', $this->styles())
            ->add('xl/worksheets/sheet1.xml', $this->sheet())
            ->output();
    }

    protected function sheet(): string
    {
        $xml = [];
        $r = 0;
        if ($this->title !== null) {
            $xml[] = $this->row(++$r, [$this->inline(1, $r, $this->title, self::TITLE)], 'ht="22" customHeight="1"');
            foreach ($this->subtitleLines as $line) {
                $xml[] = $this->row(++$r, [$this->inline(1, $r, $line, self::SUBTITLE)]);
            }
            $r++; // blank spacer row
        }

        $headerRow = ++$r;
        $cells = [];
        foreach ($this->columns as $i => $col) {
            $cells[] = $this->inline($i + 1, $r, $col['title'], self::HEADER);
        }
        $xml[] = $this->row($r, $cells);

        foreach ($this->rows as $row) {
            $r++;
            $bold = ($row['style'] ?? 'normal') !== 'normal';
            $cells = [];
            foreach ($this->columns as $i => $col) {
                $value = $row['cells'][$i] ?? null;
                if ($value === null || $value === '') {
                    if ($bold) {
                        $cells[] = '<c r="'.$this->ref($i + 1, $r).'" s="1"/>';
                    }
                    continue;
                }
                $type = $col['type'] ?? 'text';
                // Labels such as "Total" or a day heading can sit in a date or number column.
                if (($type === 'date' && ! $this->isDate($value)) || (in_array($type, ['hours', 'number'], true) && ! is_numeric($value))) {
                    $type = 'text';
                }
                $style = self::STYLE[$type][$bold ? 1 : 0];
                $cells[] = match ($type) {
                    'date' => '<c r="'.$this->ref($i + 1, $r).'" s="'.$style.'"><v>'.$this->excelDate($value).'</v></c>',
                    'hours', 'number' => '<c r="'.$this->ref($i + 1, $r).'" s="'.$style.'"><v>'.(0 + $value).'</v></c>',
                    default => $this->inline($i + 1, $r, (string) $value, $style),
                };
            }
            $xml[] = $this->row($r, $cells);
        }

        $cols = '';
        foreach ($this->columns as $i => $col) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.($col['width'] ?? 14).'" customWidth="1"/>';
        }
        $last = $this->ref(count($this->columns), max($r, $headerRow));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols>'
            .'<sheetData>'.implode('', $xml).'</sheetData>'
            .'<autoFilter ref="A'.$headerRow.':'.$last.'"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    protected function styles(): string
    {
        // cellXfs order: text, text bold, date, date bold, hours, hours bold, number, number bold, title, subtitle, header
        $xf = fn (int $numFmt, int $font, int $fill = 0) => '<xf numFmtId="'.$numFmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="0" xfId="0"'
            .($numFmt ? ' applyNumberFormat="1"' : '').($font ? ' applyFont="1"' : '').($fill ? ' applyFill="1"' : '').'/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/><numFmt numFmtId="165" formatCode="0.00"/></numFmts>'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/></font>'
            .'<font><sz val="10"/><color rgb="FF6B7280"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="11">'
            .$xf(0, 0).$xf(0, 1).$xf(164, 0).$xf(164, 1).$xf(165, 0).$xf(165, 1).$xf(0, 0).$xf(0, 1)
            .$xf(0, 2).$xf(0, 3).$xf(0, 1, 2)
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    protected function row(int $r, array $cells, string $attrs = ''): string
    {
        return '<row r="'.$r.'"'.($attrs ? ' '.$attrs : '').'>'.implode('', $cells).'</row>';
    }

    protected function inline(int $col, int $row, string $text, int $style): string
    {
        // Strip characters XML 1.0 can't carry.
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text) ?? '';

        return '<c r="'.$this->ref($col, $row).'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'.$this->esc($text).'</t></is></c>';
    }

    protected function ref(int $col, int $row): string
    {
        $name = '';
        for (; $col > 0; $col = intdiv($col - 1, 26)) {
            $name = chr(65 + ($col - 1) % 26).$name;
        }

        return $name.$row;
    }

    protected function isDate(mixed $value): bool
    {
        return $value instanceof \DateTimeInterface
            || (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) && strtotime($value) !== false);
    }

    protected function excelDate(\DateTimeInterface|string $date): int
    {
        $d = $date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable($date);
        $epoch = new \DateTimeImmutable('1899-12-30', new \DateTimeZone('UTC'));

        return (int) $epoch->diff(new \DateTimeImmutable($d->format('Y-m-d'), new \DateTimeZone('UTC')))->days;
    }

    protected function safeSheetName(): string
    {
        return mb_substr(trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $this->sheetName)) ?: 'Sheet1', 0, 31);
    }

    protected function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
