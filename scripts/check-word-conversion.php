<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$fixture = new class { use Tests\Support\WordFixture; };
$directory = storage_path('framework/word-fixtures');
Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
$source = $directory.'/agreement.docx';
file_put_contents($source, $fixture->wordBytes());
app(App\Services\WordConverter::class)->convert($source, 'docx', function ($pdf) use ($directory) {
    $pages = app(App\Services\PdfInspector::class)->inspect(new Illuminate\Http\UploadedFile($pdf, 'converted.pdf', 'application/pdf', null, true));
    copy($pdf, $directory.'/agreement.pdf');
    echo "DOCX converted successfully: {$pages} pages.\n";
});
