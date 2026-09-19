<?php

namespace Tests\Support;

trait WordFixture
{
    public function layoutWordBytes(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'word-layout-');
        file_put_contents($path, $this->wordBytes());
        $zip = new \ZipArchive;
        $zip->open($path);
        $types = $zip->getFromName('[Content_Types].xml');
        $zip->addFromString('[Content_Types].xml', str_replace('</Types>', '<Default Extension="png" ContentType="image/png"/><Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/></Types>', $types));
        $zip->addFromString('word/_rels/document.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="header" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/><Relationship Id="footer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/><Relationship Id="image" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/signature.png"/></Relationships>');
        $zip->addFromString('word/header1.xml', '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Layout fixture header</w:t></w:r></w:p></w:hdr>');
        $zip->addFromString('word/footer1.xml', '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Page </w:t></w:r><w:fldSimple w:instr="PAGE"/></w:p></w:ftr>');
        $image = imagecreatetruecolor(240, 70);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 10, 25, 'Source signature', imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        $zip->addFromString('word/media/signature.png', $png);
        $rows = '';
        for ($i = 1; $i <= 90; $i++) {
            $rows .= '<w:tr><w:tc><w:p><w:r><w:t>Item '.$i.'</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Table spanning multiple pages</w:t></w:r></w:p></w:tc></w:tr>';
        }
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:v="urn:schemas-microsoft-com:vml"><w:body><w:p><w:r><w:rPr><w:rFonts w:ascii="Deliberately Missing Fixture Font" w:hAnsi="Deliberately Missing Fixture Font"/></w:rPr><w:t>Unicode and missing font: José Santos — Καλημέρα — 日本語</w:t></w:r></w:p><w:p><w:r><w:pict><v:shape style="width:180pt;height:52pt" type="#_x0000_t75"><v:imagedata r:id="image"/></v:shape></w:pict></w:r></w:p><w:tbl><w:tblPr/><w:tblGrid><w:gridCol w:w="3500"/><w:gridCol w:w="5500"/></w:tblGrid>'.$rows.'</w:tbl><w:tbl><w:tblPr/><w:tblGrid><w:gridCol w:w="9000"/></w:tblGrid><w:tr><w:tc><w:tbl><w:tblPr/><w:tblGrid><w:gridCol w:w="8000"/></w:tblGrid><w:tr><w:tc><w:p><w:r><w:t>Nested table content</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p/></w:tc></w:tr></w:tbl><w:p><w:pPr><w:sectPr><w:headerReference w:type="default" r:id="header"/><w:footerReference w:type="default" r:id="footer"/><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr></w:pPr></w:p><w:p><w:r><w:t>Landscape section after portrait tables</w:t></w:r></w:p><w:sectPr><w:type w:val="nextPage"/><w:pgSz w:w="15840" w:h="12240" w:orient="landscape"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720"/></w:sectPr></w:body></w:document>');
        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    public function wordBytes(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'word-fixture-');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Word conversion fixture - Agreement</w:t></w:r></w:p><w:tbl><w:tblPr/><w:tblGrid><w:gridCol w:w="4000"/><w:gridCol w:w="4000"/></w:tblGrid><w:tr><w:tc><w:p><w:r><w:t>Supplier</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Amount</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:r><w:br w:type="page"/></w:r></w:p><w:p><w:r><w:t>Second page - Signature: José Santos</w:t></w:r></w:p><w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr></w:body></w:document>');
        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }
}
