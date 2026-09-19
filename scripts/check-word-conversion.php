<?php

use App\Services\PdfInspector;
use App\Services\WordConverter;
use App\Services\WordUploadInspector;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\Support\WordFixture;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($argc > 1) {
    foreach (array_slice($argv, 1) as $input) {
        $file = new UploadedFile($input, basename($input), null, null, true);
        $format = app(WordUploadInspector::class)->inspect($file);
        app(WordConverter::class)->convert($input, $format, function ($pdf) use ($input) {
            $pages = app(PdfInspector::class)->inspect(new UploadedFile($pdf, 'converted.pdf', 'application/pdf', null, true));
            echo basename($input).": {$pages} validated PDF pages.\n";
        });
    }
    exit(0);
}
$fixture = new class
{
    use WordFixture;
};
$directory = storage_path('app/word-fixtures');
File::ensureDirectoryExists($directory);
$source = $directory.'/agreement.docx';
file_put_contents($source, $fixture->wordBytes());
$layout = $directory.'/layout.docx';
file_put_contents($layout, $fixture->layoutWordBytes());
app(WordUploadInspector::class)->inspect(new UploadedFile($layout, 'layout.docx', null, null, true));
app(WordConverter::class)->convert($layout, 'docx', function ($pdf) use ($directory) {
    $pages = app(PdfInspector::class)->inspect(new UploadedFile($pdf, 'layout.pdf', 'application/pdf', null, true));
    copy($pdf, $directory.'/layout.pdf');
    echo "Layout DOCX converted successfully: {$pages} pages (tables, image, header/footer, mixed orientation, Unicode, font substitution).\n";
});
app(WordConverter::class)->convert($source, 'docx', function ($pdf) use ($directory) {
    $pages = app(PdfInspector::class)->inspect(new UploadedFile($pdf, 'converted.pdf', 'application/pdf', null, true));
    copy($pdf, $directory.'/agreement.pdf');
    echo "DOCX converted successfully: {$pages} pages.\n";
});

// Generate a genuine binary Word fixture, then run the same upload inspection
// and PDF conversion used by the application. No customer files are involved.
$profile = $directory.'/doc-profile';
File::ensureDirectoryExists($profile.'/user');
file_put_contents($profile.'/user/registrymodifications.xcu', app(WordConverter::class)->profile());
$uri = 'file:///'.str_replace('%3A', ':', implode('/', array_map('rawurlencode', explode('/', ltrim(str_replace('\\', '/', realpath($profile)), '/')))));
try {
    $process = new Process([config('document_conversion.binary'), '-env:UserInstallation='.$uri, '--headless', '--norestore', '--convert-to', 'doc:MS Word 97', '--outdir', $directory, $source]);
    $process->setTimeout(60)->mustRun();
    $legacy = $directory.'/agreement.doc';
    app(WordUploadInspector::class)->inspect(new UploadedFile($legacy, 'agreement.doc', null, null, true));
    $hash = hash_file('sha256', $legacy);
    app(WordConverter::class)->convert($legacy, 'doc', function ($pdf) use ($directory) {
        $pages = app(PdfInspector::class)->inspect(new UploadedFile($pdf, 'converted.pdf', 'application/pdf', null, true));
        copy($pdf, $directory.'/agreement-doc.pdf');
        echo "DOC converted successfully: {$pages} pages.\n";
    });
    if ($hash !== hash_file('sha256', $legacy)) {
        throw new RuntimeException('Original was modified.');
    }
} finally {
    File::deleteDirectory($profile);
}
