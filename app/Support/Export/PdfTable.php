<?php

namespace App\Support\Export;

/**
 * Dependency-free PDF for tabular reports: A4 landscape, title block, repeating table
 * header, group and total rows, zebra striping and "Page x of y" footers. Uses the
 * built-in Helvetica fonts (Latin text; other characters are transliterated).
 *
 * Columns: [['title' => 'Date', 'width' => 0.1, 'align' => 'left|right'], …]  (widths are fractions)
 * Rows:    [['cells' => [...], 'style' => 'normal|group|total'], …]
 */
class PdfTable
{
    protected const W = 842.0;   // A4 landscape, points
    protected const H = 595.0;
    protected const M = 36.0;    // margin
    protected const ROW = 16.0;  // row height
    protected const SIZE = 8.5;  // body font size

    /** Helvetica glyph widths (1/1000 em) for ASCII 32–126. */
    protected const WIDTHS = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722,
        667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667,
        611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556,
        556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];

    /** @var array<int, string> content stream per page */
    protected array $pages = [];

    protected string $ops = '';

    protected float $y = 0;

    public function __construct(
        protected string $title,
        protected array $subtitleLines,
        protected array $columns,
        protected array $rows,
        protected string $footerNote = '',
    ) {
    }

    public function render(): string
    {
        $this->newPage(true);
        $zebra = false;
        foreach ($this->rows as $row) {
            $style = $row['style'] ?? 'normal';
            if ($this->y - self::ROW < self::M + 18) {
                $this->newPage(false);
                $zebra = false;
            }
            $top = $this->y;
            if ($style === 'group') {
                $this->rect(self::M, $top - self::ROW, self::W - 2 * self::M, self::ROW, [0.953, 0.957, 0.965]);
            } elseif ($style === 'total') {
                $this->line(self::M, $top, self::W - self::M, $top, [0.2, 0.2, 0.25], 0.8);
            } elseif ($zebra) {
                $this->rect(self::M, $top - self::ROW, self::W - 2 * self::M, self::ROW, [0.985, 0.985, 0.99]);
            }
            $zebra = $style === 'normal' ? ! $zebra : false;
            $this->cells($row['cells'], $style !== 'normal', $top, span: $style === 'group');
            $this->y -= self::ROW;
        }
        if (! $this->rows) {
            $this->text(self::M, $this->y - 14, 'No time recorded for this selection.', 9, false, [0.42, 0.45, 0.5]);
        }
        $this->pages[] = $this->ops;

        return $this->assemble();
    }

    protected function newPage(bool $first): void
    {
        if (! $first) {
            $this->pages[] = $this->ops;
        }
        $this->ops = '';
        $this->y = self::H - self::M;

        if ($first) {
            $this->text(self::M, $this->y - 14, $this->title, 15, true, [0.07, 0.08, 0.1]);
            $this->y -= 22;
            foreach ($this->subtitleLines as $line) {
                $this->text(self::M, $this->y - 11, $line, 9, false, [0.42, 0.45, 0.5]);
                $this->y -= 13;
            }
            $this->y -= 10;
        }

        // Table header
        $this->rect(self::M, $this->y - self::ROW - 2, self::W - 2 * self::M, self::ROW + 2, [0.12, 0.13, 0.16]);
        $this->cells(array_column($this->columns, 'title'), true, $this->y - 1, [1, 1, 1]);
        $this->y -= self::ROW + 2;
    }

    /** With $span, the first cell runs across every column but the last (group headings). */
    protected function cells(array $cells, bool $bold, float $top, array $color = [0.12, 0.13, 0.16], bool $span = false): void
    {
        $x = self::M;
        $usable = self::W - 2 * self::M;
        $last = count($this->columns) - 1;
        foreach ($this->columns as $i => $col) {
            if ($span && $i > 0 && $i < $last) {
                continue;
            }
            $w = $span && $i === 0 ? $usable * (1 - $this->columns[$last]['width']) : $usable * $col['width'];
            $text = $this->fit((string) ($cells[$i] ?? ''), $w - 8, self::SIZE, $bold);
            if ($text !== '') {
                $tx = ($col['align'] ?? 'left') === 'right' ? $x + $w - 4 - $this->measure($text, self::SIZE, $bold) : $x + 4;
                $this->text($tx, $top - self::ROW + 5, $text, self::SIZE, $bold, $color);
            }
            $x += $w;
        }
    }

    protected function text(float $x, float $y, string $s, float $size, bool $bold, array $rgb): void
    {
        $this->ops .= sprintf("%.3F %.3F %.3F rg BT /%s %.1F Tf %.2F %.2F Td (%s) Tj ET\n",
            $rgb[0], $rgb[1], $rgb[2], $bold ? 'F2' : 'F1', $size, $x, $y, $this->escape($this->latin($s)));
    }

    protected function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->ops .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $rgb[0], $rgb[1], $rgb[2], $x, $y, $w, $h);
    }

    protected function line(float $x1, float $y1, float $x2, float $y2, array $rgb, float $width): void
    {
        $this->ops .= sprintf("%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n", $rgb[0], $rgb[1], $rgb[2], $width, $x1, $y1, $x2, $y2);
    }

    /** Truncate with an ellipsis so text never spills into the next column. */
    protected function fit(string $s, float $max, float $size, bool $bold): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        if ($this->measure($s, $size, $bold) <= $max) {
            return $s;
        }
        while ($s !== '' && $this->measure($s.'...', $size, $bold) > $max) {
            $s = mb_substr($s, 0, -1);
        }

        return $s === '' ? '' : rtrim($s).'...';
    }

    protected function measure(string $s, float $size, bool $bold): float
    {
        $w = 0;
        foreach (str_split($this->latin($s)) as $ch) {
            $o = ord($ch);
            $w += ($o >= 32 && $o <= 126) ? self::WIDTHS[$o - 32] : 556;
        }

        return $w * $size / 1000 * ($bold ? 1.06 : 1.0);
    }

    /** UTF-8 → Windows-1252 for the standard PDF fonts. */
    protected function latin(string $s): string
    {
        $s = strtr($s, ['₱' => 'PHP ', '→' => '->']); // not in Windows-1252
        $out = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);

        return $out === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $out;
    }

    protected function escape(string $s): string
    {
        return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }

    protected function assemble(): string
    {
        $count = count($this->pages);
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $n = 5;
        foreach ($this->pages as $i => $ops) {
            $footer = '';
            $label = 'Page '.($i + 1).' of '.$count;
            $grey = [0.42, 0.45, 0.5];
            $this->ops = '';
            if ($this->footerNote !== '') {
                $this->text(self::M, self::M - 14, $this->footerNote, 7.5, false, $grey);
            }
            $this->text(self::W - self::M - $this->measure($label, 7.5, false), self::M - 14, $label, 7.5, false, $grey);
            $footer = $this->ops;

            $stream = $ops.$footer;
            $pageId = $n++;
            $contentId = $n++;
            $kids[] = $pageId.' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::W.' '.self::H.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $data = function_exists('gzcompress') ? gzcompress($stream, 6) : $stream;
            $filter = function_exists('gzcompress') ? ' /Filter /FlateDecode' : '';
            $objects[$contentId] = "<< /Length ".strlen($data).$filter." >>\nstream\n".$data."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$count.' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R /Info << /Producer (TaskFlow) /Title (".$this->escape($this->latin($this->title)).") >> >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }
}
