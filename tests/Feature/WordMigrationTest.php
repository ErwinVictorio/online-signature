<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PdfFixture;
use Tests\TestCase;

class WordMigrationTest extends TestCase
{
    use DatabaseMigrations, PdfFixture;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_migration_backfill_preserves_existing_pdf_files_and_versions(): void
    {
        $this->post('/documents', ['file' => UploadedFile::fake()->createWithContent('old.pdf', $this->pdfBytes())])->assertSessionHasNoErrors();
        $document = Document::firstOrFail();
        Storage::disk('private')->put('old-signed.pdf', $this->pdfBytes());
        DB::table('document_versions')->insert(['document_id' => $document->id, 'file_path' => 'old-signed.pdf', 'version' => 1, 'created_at' => now()]);
        $migration = require database_path('migrations/2026_09_18_000005_add_word_conversion_to_documents.php');
        $migration->down();
        $migration->up();
        $document->refresh();
        $this->assertSame($document->file_path, $document->editor_pdf_path);
        $this->assertSame('pdf', $document->source_format);
        $this->get('/documents/'.$document->id.'/file')->assertOk();
        $this->get('/documents/'.$document->id.'/download?version=1')->assertDownload('old-signed-v1.pdf');
        $this->assertSame($this->pdfBytes(), Storage::disk('private')->get($document->file_path));
    }
}
