<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class WordUploadInspector
{
    public function inspect(UploadedFile $file): string
    {
        $format = strtolower($file->getClientOriginalExtension());
        try {
            $allowed = $format === 'docx'
                ? ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream']
                : ['application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream'];
            if (! in_array($file->getMimeType(), $allowed, true)) {
                throw new \RuntimeException('The file contents do not match a supported Word document.');
            }
            if ($format === 'docx') {
                $this->docx($file->getRealPath());
            } elseif ($format === 'doc') {
                app(LegacyWordInspector::class)->inspect(file_get_contents($file->getRealPath()));
            } else {
                throw new \RuntimeException('Choose a PDF, DOCX, or DOC file.');
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['file' => $e instanceof \RuntimeException ? $e->getMessage() : 'This Word file could not be read. Export an unencrypted DOCX and try again.']);
        }

        return $format;
    }

    private function docx(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('This DOCX is damaged, encrypted, or is not a Word document.');
        }
        try {
            $total = 0;
            $names = [];
            if ($zip->numFiles > 5000) {
                throw new \RuntimeException('This Word archive contains too many files.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                $total += $entry['size'];
                if (isset($names[$name]) || str_contains($name, '..') || str_contains($name, '\\') || str_starts_with($name, '/') || ($entry['encryption_method'] ?? 0) !== 0 || $entry['size'] > 20 * 1024 * 1024 || $total > 100 * 1024 * 1024) {
                    throw new \RuntimeException('This Word archive is encrypted, unsafe, or exceeds its expanded size limit.');
                }
                $names[$name] = true;
                if (preg_match('/vbaProject|embeddings\//i', $name)) {
                    throw new \RuntimeException('Remove macros and embedded objects before uploading this Word file.');
                }
                if (str_ends_with(strtolower($name), '.xml') || str_ends_with(strtolower($name), '.rels')) {
                    $xml = $zip->getFromIndex($i);
                    $dom = $this->xml($xml);
                    foreach ($dom->getElementsByTagName('Relationship') as $relationship) {
                        if (strcasecmp($relationship->getAttribute('TargetMode'), 'External') === 0 && ! str_ends_with($relationship->getAttribute('Type'), '/hyperlink')) {
                            throw new \RuntimeException('This Word file references external content. Embed its images and remove linked templates before uploading.');
                        }
                    }
                    if (preg_match('/\b(DDEAUTO|DDE|INCLUDETEXT|INCLUDEPICTURE)\b/i', $dom->textContent)) {
                        throw new \RuntimeException('Remove external-content fields before uploading this Word file.');
                    }
                }
            }
            foreach (['[Content_Types].xml', '_rels/.rels', 'word/document.xml'] as $required) {
                if (! isset($names[$required])) {
                    throw new \RuntimeException('This file is not a valid DOCX Word document.');
                }
            }
            $types = $zip->getFromName('[Content_Types].xml');
            $main = false;
            foreach ($this->xml($types)->getElementsByTagName('Override') as $override) {
                if ($override->getAttribute('PartName') === '/word/document.xml' && $override->getAttribute('ContentType') === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml') {
                    $main = true;
                }
            }
            if (stripos($types, 'macroEnabled') !== false || ! $main) {
                throw new \RuntimeException('Only standard DOCX documents without macros are supported.');
            }
            $office = false;
            foreach ($this->xml($zip->getFromName('_rels/.rels'))->getElementsByTagName('Relationship') as $relationship) {
                if (in_array($relationship->getAttribute('Type'), ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument', 'http://purl.oclc.org/ooxml/officeDocument/relationships/officeDocument'], true)
                    && in_array($relationship->getAttribute('Target'), ['word/document.xml', '/word/document.xml'], true)
                    && strcasecmp($relationship->getAttribute('TargetMode'), 'External') !== 0) {
                    $office = true;
                }
            }
            if (! $office) {
                throw new \RuntimeException('This file does not link to a valid Word document.');
            }
            $document = $this->xml($zip->getFromName('word/document.xml'));
            if ($document->documentElement->localName !== 'document' || ! in_array($document->documentElement->namespaceURI, ['http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'http://purl.oclc.org/ooxml/wordprocessingml/main'], true)) {
                throw new \RuntimeException('This file does not contain a valid Word document.');
            }
        } finally {
            $zip->close();
        }
    }

    private function xml(string $xml): DOMDocument
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new \RuntimeException('XML entities are not allowed in uploaded Word documents.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument;
            if (! $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new \RuntimeException('This Word file contains damaged XML.');
            }

            return $dom;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
