<?php
if (!defined('ABSPATH')) { exit; }

/** Small dependency-free PDF writer for text summaries. */
class Clever_Forms_PDF {
    private array $pages = [];
    private array $current = [];
    private int $y = 760;

    private function esc(string $s): string {
        $s = wp_strip_all_tags($s);
        $s = str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", " ", " "], $s);
        return preg_replace('/[^\x20-\x7E]/', '?', $s) ?? '';
    }
    private function page(): void { if ($this->current) $this->pages[] = implode("\n", $this->current); $this->current=[]; $this->y=760; }
    private function line(string $text, int $size=10, bool $bold=false, int $x=54, int $gap=18): void {
        if ($this->y < 72) $this->page();
        $font = $bold ? 'F2' : 'F1';
        $this->current[] = "BT /{$font} {$size} Tf {$x} {$this->y} Td (".$this->esc($text).") Tj ET";
        $this->y -= $gap;
    }
    private function wrap(string $label, string $value): void {
        $prefix = $label !== '' ? $label . ': ' : '';
        $text = trim($prefix . $value);
        $max=88;
        $words=preg_split('/\s+/', $text) ?: [];
        $buf='';
        foreach ($words as $w) {
            if (strlen($buf.' '.$w) > $max) { $this->line($buf, 9, false, 64, 14); $buf=$w; }
            else $buf = trim($buf.' '.$w);
        }
        if ($buf !== '') $this->line($buf, 9, false, 64, 14);
    }
    public function render(string $title, string $reference, string $submitted, array $items, string $org=''): string {
        $this->pages=[]; $this->current=[]; $this->y=760;
        if ($org !== '') $this->line(strtoupper($org), 14, true, 54, 22);
        $this->line($title, 18, true, 54, 26);
        $this->line('Reference: '.$reference, 9, false, 54, 14);
        $this->line('Submitted: '.$submitted, 9, false, 54, 24);
        foreach ($items as $item) {
            $type = $item['type'] ?? 'field';
            if ($type === 'section') { $this->line(strtoupper((string)$item['label']), 12, true, 54, 20); continue; }
            $this->wrap((string)($item['label'] ?? ''), (string)($item['value'] ?? ''));
        }
        $this->page();
        return $this->build();
    }
    private function build(): string {
        $objects=[];
        $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
        $kids=[]; $next=5;
        foreach ($this->pages as $i=>$stream) { $kids[]=$next.' 0 R'; $next+=2; }
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        $objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $n=5;
        foreach ($this->pages as $stream) {
            $content=$n+1;
            $objects[$n] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$content.' 0 R >>';
            $objects[$content] = '<< /Length '.strlen($stream).' >>' . "\nstream\n" . $stream . "\nendstream";
            $n+=2;
        }
        ksort($objects);
        $pdf="%PDF-1.4\n"; $offsets=[0];
        foreach ($objects as $id=>$obj) { $offsets[$id]=strlen($pdf); $pdf.=$id." 0 obj\n".$obj."\nendobj\n"; }
        $xref=strlen($pdf); $count=max(array_keys($objects))+1;
        $pdf.="xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i=1;$i<$count;$i++) $pdf.=sprintf('%010d 00000 n ', $offsets[$i] ?? 0)."\n";
        $pdf.="trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        return $pdf;
    }
}
