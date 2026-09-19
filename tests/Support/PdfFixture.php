<?php

namespace Tests\Support;

trait PdfFixture
{
    private function pdfBytes(int $pageCount = 1): string
    {
        $pdf = "%PDF-1.4\n";
        $kids = implode(' ', array_map(fn ($id) => $id.' 0 R', range(4, $pageCount + 3)));
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids ['.$kids.'] /Count '.$pageCount.' >>', "<< /Length 0 >>\nstream\n\nendstream"];
        for ($page = 0; $page < $pageCount; $page++) {
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << >> /Contents 3 0 R >>';
        }
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
