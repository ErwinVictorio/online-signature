<?php

namespace Tests\Feature;

use App\Jobs\ConvertWordDocument;
use App\Models\Document;
use App\Models\User;
use App\Services\PdfInspector;
use App\Services\WordConverter;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Tests\Support\PdfFixture;
use Tests\Support\WordFixture;
use Tests\TestCase;

class WordConversionTest extends TestCase
{
    use PdfFixture, RefreshDatabase, WordFixture;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Queue::fake();
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    private function upload(): Document
    {
        $this->post('/documents', ['file' => UploadedFile::fake()->createWithContent('agreement.docx', $this->wordBytes())])->assertSessionHasNoErrors()->assertRedirect();

        return Document::latest('id')->firstOrFail();
    }

    private function convert(Document $document, ?callable $during = null, ?string $bytes = null): void
    {
        $converter = \Mockery::mock(WordConverter::class);
        $converter->shouldReceive('available')->andReturn(true);
        $converter->shouldReceive('convert')->once()->andReturnUsing(function ($source, $format, $consume) use ($during, $bytes) {
            $this->assertFileExists($source);
            $this->assertSame('docx', $format);
            if ($during) {
                $during();
            }
            $path = tempnam(sys_get_temp_dir(), 'converted-test-');
            file_put_contents($path, $bytes ?? $this->pdfBytes());
            try {
                $consume($path);
            } finally {
                unlink($path);
            }
        });
        (new ConvertWordDocument($document->id, $document->conversion_attempt))->handle($converter, app(PdfInspector::class));
    }

    public function test_word_upload_is_private_queued_and_not_editable_until_converted(): void
    {
        $document = $this->upload();
        $this->assertSame('docx', $document->source_format);
        $this->assertSame('queued', $document->conversion_status);
        $this->assertNull($document->page_count);
        Queue::assertPushed(ConvertWordDocument::class, fn ($job) => $job->documentId === $document->id && $job->connection === 'conversion' && $job->queue === 'conversions');
        foreach (['/edit', '/file', '/download?version=converted'] as $suffix) {
            $this->get('/documents/'.$document->id.$suffix)->assertConflict();
        }
        $this->putJson('/documents/'.$document->id.'/placements', [])->assertConflict();
        $this->postJson('/documents/'.$document->id.'/sign', [])->assertConflict();
        $this->postJson('/documents/'.$document->id.'/conversion/review')->assertConflict();
        $this->get('/documents/'.$document->id.'/download?version=original')->assertDownload('agreement.docx')->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_conversion_review_signing_downloads_and_deletion(): void
    {
        $document = $this->upload();
        $original = Storage::disk('private')->get($document->file_path);
        $this->convert($document);
        $document->refresh();
        $this->assertSame('ready', $document->conversion_status);
        $this->assertSame(1, $document->page_count);
        $this->get('/documents/'.$document->id.'/file')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/documents/'.$document->id.'/edit')->assertOk();
        $this->postJson('/documents/'.$document->id.'/sign', [])->assertUnprocessable();
        $this->postJson('/documents/'.$document->id.'/conversion/review')->assertOk();
        $placement = ['id' => (string) Str::uuid(), 'type' => 'name', 'text' => 'Signer', 'signature_id' => null, 'page_number' => 1, 'x_ratio' => .1, 'y_ratio' => .1, 'width_ratio' => .2, 'height_ratio' => .05];
        $this->putJson('/documents/'.$document->id.'/placements', ['placements' => [$placement], 'revision' => 0])->assertOk();
        $this->postJson('/documents/'.$document->id.'/sign', ['file' => UploadedFile::fake()->createWithContent('signed.pdf', $this->pdfBytes()), 'placements' => json_encode([$placement]), 'revision' => 1])->assertOk();
        $this->get('/documents/'.$document->id.'/download?version=converted')->assertDownload('agreement-converted.pdf');
        $this->get('/documents/'.$document->id.'/download')->assertDownload('agreement-signed.pdf');
        $this->assertSame($original, Storage::disk('private')->get($document->file_path));
        $this->post('/documents/'.$document->id.'/conversion/retry')->assertConflict();
        foreach (['conversion_queued', 'conversion_started', 'conversion_succeeded', 'converted_layout_reviewed', 'document_signed'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'user_id' => $document->user_id]);
        }
        $this->delete('/documents/'.$document->id)->assertRedirect();
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_corrupt_output_fails_and_manual_retry_retains_original(): void
    {
        $document = $this->upload();
        $this->convert($document, bytes: 'not a PDF');
        $this->assertSame('failed', $document->fresh()->conversion_status);
        Storage::disk('private')->assertExists($document->file_path);
        $this->assertCount(1, Storage::disk('private')->allFiles());
        $this->post('/documents/'.$document->id.'/conversion/retry')->assertRedirect();
        $document->refresh();
        $this->assertSame(2, $document->conversion_attempt);
        $this->assertSame('queued', $document->conversion_status);
        $this->post('/documents/'.$document->id.'/conversion/retry')->assertConflict();
        $this->convert($document);
        $this->assertSame('ready', $document->fresh()->conversion_status);
    }

    public function test_missing_converter_exposes_actionable_error(): void
    {
        $document = $this->upload();
        config(['document_conversion.binary' => '/missing/soffice']);
        (new ConvertWordDocument($document->id, 1))->handle(app(WordConverter::class), app(PdfInspector::class));
        $this->assertSame('failed', $document->fresh()->conversion_status);
        $this->assertStringContainsString('install LibreOffice', $document->fresh()->conversion_error);
        Storage::disk('private')->assertExists($document->file_path);
    }

    public function test_conversion_exceeding_page_limit_is_not_published(): void
    {
        $document = $this->upload();
        $this->convert($document, bytes: $this->pdfBytes(501));
        $this->assertSame('failed', $document->fresh()->conversion_status);
        $this->assertNull($document->fresh()->editor_pdf_path);
        $this->assertCount(1, Storage::disk('private')->allFiles());
    }

    public function test_oversized_word_upload_is_rejected(): void
    {
        $this->post('/documents', ['file' => UploadedFile::fake()->create('oversized.docx', 20481)])->assertSessionHasErrors('file');
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_cleanup_failure_after_publish_keeps_the_ready_pdf(): void
    {
        $document = $this->upload();
        $converter = \Mockery::mock(WordConverter::class);
        $converter->shouldReceive('convert')->once()->andReturnUsing(function ($source, $format, $consume) {
            $path = tempnam(sys_get_temp_dir(), 'cleanup-test-');
            file_put_contents($path, $this->pdfBytes());
            try {
                $consume($path);
            } finally {
                unlink($path);
            }
            throw new \RuntimeException('Simulated cleanup failure after publication');
        });
        (new ConvertWordDocument($document->id, 1))->handle($converter, app(PdfInspector::class));
        $this->assertSame('ready', $document->fresh()->conversion_status);
        Storage::disk('private')->assertExists($document->fresh()->editor_pdf_path);
    }

    public function test_duplicate_jobs_and_stale_attempts_do_not_run_converter(): void
    {
        $document = $this->upload();
        $converter = \Mockery::mock(WordConverter::class);
        $converter->shouldNotReceive('convert');
        (new ConvertWordDocument($document->id, 0))->handle($converter, app(PdfInspector::class));
        $document->update(['conversion_status' => 'converting']);
        (new ConvertWordDocument($document->id, 1))->handle($converter, app(PdfInspector::class));
        $this->assertSame('converting', $document->fresh()->conversion_status);
    }

    public function test_stale_result_is_discarded(): void
    {
        $document = $this->upload();
        $this->convert($document, fn () => $document->fresh()->update(['conversion_attempt' => 2, 'conversion_status' => 'queued']));
        $this->assertSame('queued', $document->fresh()->conversion_status);
        $this->assertNull($document->fresh()->editor_pdf_path);
        $this->assertCount(1, Storage::disk('private')->allFiles());
    }

    public function test_deletion_during_conversion_discards_output(): void
    {
        $document = $this->upload();
        $this->convert($document, fn () => $this->delete('/documents/'.$document->id)->assertRedirect());
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_word_endpoints_enforce_ownership(): void
    {
        $document = $this->upload();
        $this->convert($document);
        $this->actingAs(User::factory()->create());
        foreach (['/edit', '/file', '/download?version=original', '/download?version=converted', '/download'] as $suffix) {
            $this->get('/documents/'.$document->id.$suffix)->assertForbidden();
        }
        foreach (['/conversion/retry', '/conversion/review', '/sign'] as $suffix) {
            $this->postJson('/documents/'.$document->id.$suffix)->assertForbidden();
        }
        $this->putJson('/documents/'.$document->id.'/placements', [])->assertForbidden();
        $this->delete('/documents/'.$document->id)->assertForbidden();
    }

    public function test_invalid_and_mislabeled_word_files_are_rejected(): void
    {
        foreach (['bad.docx' => 'not zip', 'bad.doc' => $this->pdfBytes(), 'macros.docm' => $this->wordBytes(), 'renamed.docx' => $this->pdfBytes(), 'renamed.pdf' => $this->wordBytes()] as $name => $bytes) {
            $this->post('/documents', ['file' => UploadedFile::fake()->createWithContent($name, $bytes)])->assertSessionHasErrors('file');
        }
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_abandoned_conversion_can_be_retried_and_old_failure_cannot_override(): void
    {
        $document = $this->upload();
        $document->update(['conversion_status' => 'converting', 'conversion_started_at' => now()->subMinutes(6)]);
        $this->post('/documents/'.$document->id.'/conversion/retry')->assertRedirect();
        (new ConvertWordDocument($document->id, 1))->failed(new \RuntimeException('old timeout'));
        $this->assertSame('queued', $document->fresh()->conversion_status);
        $this->assertSame(2, $document->fresh()->conversion_attempt);
    }

    public function test_process_timeout_is_requeued_once_then_fails_safely(): void
    {
        $document = $this->upload();
        $process = new Process(['unused']);
        $exception = new ProcessTimedOutException($process, 1);
        $converter = \Mockery::mock(WordConverter::class);
        $converter->shouldReceive('convert')->twice()->andThrow($exception);
        $converter->shouldReceive('available')->andReturn(true);
        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1, 2);
        $job = (new ConvertWordDocument($document->id, 1))->setJob($queueJob);
        try {
            $job->handle($converter, app(PdfInspector::class));
            $this->fail('First timeout should be retried.');
        } catch (ProcessTimedOutException $e) {
            $this->assertSame('queued', $document->fresh()->conversion_status);
        }
        $job->handle($converter, app(PdfInspector::class));
        $this->assertSame('failed', $document->fresh()->conversion_status);
        Storage::disk('private')->assertExists($document->file_path);
    }

    public function test_failed_storage_cannot_publish_ready_document(): void
    {
        $document = $this->upload();
        $disk = Storage::disk('private');
        $mock = \Mockery::mock($disk)->makePartial();
        $mock->shouldReceive('put')->once()->andReturn(false);
        Storage::set('private', $mock);
        $this->convert($document);
        $this->assertSame('failed', $document->fresh()->conversion_status);
        $this->assertNull($document->fresh()->editor_pdf_path);
        $this->assertCount(1, $disk->allFiles());
    }

    public function test_unsafe_docx_packages_are_rejected_before_queuing(): void
    {
        foreach ([
            ['word/vbaProject.bin', 'macro'],
            ['word/embeddings/object.bin', 'embedded'],
            ['../outside.xml', '<test/>'],
            ['word/_rels/document.xml.rels', '<Relationships><Relationship Type="image" TargetMode="External" Target="https://example.test/image.png"/></Relationships>'],
            ['word/document.xml', '<!DOCTYPE document [<!ENTITY x SYSTEM "file:///secret">]><document>&x;</document>'],
            ['word/big.bin', str_repeat('x', 21 * 1024 * 1024)],
            ['_rels/.rels', '<Relationships/>'],
        ] as [$name, $content]) {
            $path = tempnam(sys_get_temp_dir(), 'unsafe-word-');
            file_put_contents($path, $this->wordBytes());
            $zip = new \ZipArchive;
            $zip->open($path);
            $zip->addFromString($name, $content);
            $zip->close();
            try {
                $this->post('/documents', ['file' => new UploadedFile($path, 'unsafe.docx', null, null, true)])->assertSessionHasErrors('file');
            } finally {
                unlink($path);
            }
        }
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('documents', 0);
    }
}
