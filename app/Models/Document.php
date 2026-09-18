<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['file_path', 'signed_file_path', 'editor_pdf_path'];

    protected $appends = ['editor_ready'];

    public function getEditorReadyAttribute(): bool
    {
        return $this->source_format === 'pdf' || ($this->conversion_status === 'ready' && $this->editor_pdf_path && $this->page_count > 0);
    }

    public function editorPath(): string
    {
        abort_unless($this->editor_ready, 409, 'Wait for Word conversion to finish before opening the editor.');
        return $this->editor_pdf_path ?: $this->file_path;
    }

    public function requireConversionReview(): void
    {
        abort_unless($this->editor_ready, 409, 'This document is not ready for signing.');
        abort_if($this->source_format !== 'pdf' && ! $this->conversion_reviewed_at, 422, 'Review and confirm the converted PDF layout before signing.');
    }

    protected function casts(): array
    {
        return ['placements' => 'array', 'signed_at' => 'datetime', 'converted_at' => 'datetime', 'conversion_started_at' => 'datetime', 'conversion_reviewed_at' => 'datetime'];
    }
}
