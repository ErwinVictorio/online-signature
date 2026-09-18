<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

class PdfInspector
{
    public function inspect(UploadedFile $file, string $field = 'file'): int
    {
        try {
            $contents = file_get_contents($file->getRealPath());
            if (! str_starts_with($contents, '%PDF-') || ! str_contains(substr($contents, -2048), '%%EOF')) {
                throw new \RuntimeException('Invalid PDF envelope');
            }
            $config = new Config;
            $config->setRetainImageContent(false);
            $config->setDecodeMemoryLimit(64 * 1024 * 1024);
            $pdf = (new Parser([], $config))->parseContent($contents);
            $pages = count($pdf->getPages());
            if ($pages < 1 || $pages > 500) {
                throw new \RuntimeException('Unsupported page count');
            }

            return $pages;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([$field => 'Upload a readable, unencrypted PDF with 1 to 500 pages. This file could not be opened.']);
        }
    }
}
