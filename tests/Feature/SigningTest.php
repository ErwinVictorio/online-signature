<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\PdfFixture;
use Tests\TestCase;

class SigningTest extends TestCase
{
    use PdfFixture, RefreshDatabase;

    private Document $document;

    private array $placement;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        $this->post('/documents', ['file' => UploadedFile::fake()->createWithContent('contract.pdf', $this->pdfBytes())])->assertSessionHasNoErrors();
        $this->document = Document::firstOrFail();
        $this->placement = ['id' => (string) Str::uuid(), 'type' => 'name', 'text' => 'Test Signer', 'signature_id' => null, 'page_number' => 1, 'x_ratio' => 0.2, 'y_ratio' => 0.7, 'width_ratio' => 0.2, 'height_ratio' => 0.05];
    }

    public function test_draft_round_trip_and_stale_revision_protection(): void
    {
        $url = '/documents/'.$this->document->id.'/placements';
        $this->putJson($url, ['placements' => [$this->placement], 'revision' => 0])->assertOk()->assertJsonPath('revision', 1);
        $this->assertSame([$this->placement], $this->document->fresh()->placements);
        $this->putJson($url, ['placements' => [], 'revision' => 0])->assertConflict();
        $this->assertCount(1, $this->document->fresh()->placements);
    }

    public function test_out_of_bounds_and_nonexistent_pages_are_rejected(): void
    {
        foreach ([['x_ratio' => 0.95], ['page_number' => 2], ['width_ratio' => 0], ['type' => 'unknown']] as $change) {
            $this->putJson('/documents/'.$this->document->id.'/placements', ['placements' => [array_merge($this->placement, $change)], 'revision' => 0])->assertUnprocessable();
        }
        $this->assertSame(0, $this->document->fresh()->revision);
    }

    public function test_signature_ownership_is_validated_inside_placements(): void
    {
        $signature = Signature::create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign', 'image_path' => 'foreign.png', 'mime_type' => 'image/png', 'width' => 200, 'height' => 60]);
        $placement = array_merge($this->placement, ['type' => 'signature', 'text' => null, 'signature_id' => $signature->id]);
        $this->putJson('/documents/'.$this->document->id.'/placements', ['placements' => [$placement], 'revision' => 0])->assertUnprocessable();
    }

    public function test_signed_versions_preserve_original_and_previous_signed_copy(): void
    {
        $original = Storage::disk('private')->get($this->document->file_path);
        foreach ([0, 1] as $revision) {
            $this->postJson('/documents/'.$this->document->id.'/sign', ['file' => UploadedFile::fake()->createWithContent('signed.pdf', $this->pdfBytes()), 'placements' => json_encode([$this->placement]), 'revision' => $revision])->assertOk()->assertJsonPath('revision', $revision + 1);
        }
        $document = $this->document->fresh();
        $this->assertSame('signed', $document->status);
        $this->assertNotNull($document->signed_at);
        $this->assertSame($original, Storage::disk('private')->get($document->file_path));
        $this->assertDatabaseCount('document_versions', 2);
        $this->get('/documents/'.$document->id.'/download?version=1')->assertOk()->assertDownload('contract-signed-v1.pdf');
        $this->get('/documents/'.$document->id.'/download')->assertOk()->assertDownload('contract-signed.pdf');
        $this->delete('/documents/'.$document->id)->assertRedirect();
        $this->assertCount(0, Storage::disk('private')->allFiles());
    }

    public function test_failed_signed_upload_does_not_replace_existing_data_or_leave_a_file(): void
    {
        $this->postJson('/documents/'.$this->document->id.'/sign', ['file' => UploadedFile::fake()->createWithContent('signed.pdf', $this->pdfBytes()), 'placements' => json_encode([$this->placement]), 'revision' => 99])->assertConflict();
        $this->assertNull($this->document->fresh()->signed_file_path);
        $this->assertCount(1, Storage::disk('private')->allFiles());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'document_signed']);
    }

    public function test_other_users_cannot_save_drafts_or_signed_copies(): void
    {
        $this->actingAs(User::factory()->create());
        $this->putJson('/documents/'.$this->document->id.'/placements', [])->assertForbidden();
        $this->postJson('/documents/'.$this->document->id.'/sign', [])->assertForbidden();
    }
}
