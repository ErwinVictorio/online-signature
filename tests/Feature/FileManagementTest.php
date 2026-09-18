<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PdfFixture;
use Tests\TestCase;

class FileManagementTest extends TestCase
{
    use PdfFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->withoutVite();
    }

    public function test_pdf_upload_original_download_and_deletion_preserve_audit(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/documents', ['file' => UploadedFile::fake()->createWithContent('contract.pdf', $this->pdfBytes())])->assertSessionHasNoErrors()->assertRedirect();
        $document = Document::firstOrFail();
        $this->assertSame(1, $document->page_count);
        Storage::disk('private')->assertExists($document->file_path);
        $this->get('/documents/'.$document->id.'/download?version=original')->assertOk()->assertDownload('contract.pdf');
        $this->delete('/documents/'.$document->id)->assertRedirect('/documents');
        Storage::disk('private')->assertMissing($document->file_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_deleted', 'document_id' => null, 'document_name' => 'contract.pdf']);
    }

    public function test_corrupt_pdf_is_rejected_without_writing_files(): void
    {
        $this->actingAs(User::factory()->create())->post('/documents', ['file' => UploadedFile::fake()->createWithContent('bad.pdf', "%PDF-1.4\ncorrupt\n%%EOF")])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
        $this->assertCount(0, Storage::disk('private')->allFiles());
    }

    public function test_other_users_cannot_access_any_document_endpoint(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/documents', ['file' => UploadedFile::fake()->createWithContent('private.pdf', $this->pdfBytes())])->assertSessionHasNoErrors();
        $id = Document::firstOrFail()->id;
        $this->actingAs(User::factory()->create());
        foreach (['', '/edit', '/file', '/download', '/download?version=original'] as $suffix) {
            $this->get('/documents/'.$id.$suffix)->assertForbidden();
        }
        $this->delete('/documents/'.$id)->assertForbidden();
        $this->get('/documents')->assertDontSee('private.pdf');
    }

    public function test_signatures_are_private_and_default_is_unique(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        foreach (['First', 'Second'] as $name) {
            $this->post('/signatures', ['name' => $name, 'image' => UploadedFile::fake()->image('signature.png', 200, 60), 'is_default' => true])->assertSessionHasNoErrors();
        }
        $this->assertSame(1, Signature::where('is_default', true)->count());
        $signature = Signature::latest('id')->firstOrFail();
        $this->get('/signatures/'.$signature->id.'/image')->assertOk();
        $this->actingAs(User::factory()->create());
        $this->get('/signatures/'.$signature->id.'/image')->assertForbidden();
        $this->patch('/signatures/'.$signature->id.'/default')->assertForbidden();
        $this->delete('/signatures/'.$signature->id)->assertForbidden();
        $this->actingAs($owner)->delete('/signatures/'.$signature->id)->assertRedirect();
        Storage::disk('private')->assertMissing($signature->image_path);
        $this->assertTrue(Signature::firstOrFail()->is_default);
    }

    public function test_svg_and_oversized_images_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/signatures', ['name' => 'Unsafe', 'image' => UploadedFile::fake()->createWithContent('signature.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')])->assertSessionHasErrors('image');
        $this->post('/signatures', ['name' => 'Large', 'image' => UploadedFile::fake()->image('signature.png')->size(2049)])->assertSessionHasErrors('image');
        $this->assertDatabaseCount('signatures', 0);
    }
}
