<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite must disable foreign keys outside a transaction before rebuilding
    // documents; otherwise ON DELETE cascades remove versions and audit links.
    public $withinTransaction = false;

    public function up(): void
    {
        $this->changeSchema(function () {
            Schema::table('documents', function (Blueprint $table) {
                // file_path remains the immutable original for backwards compatibility.
                $table->string('source_format', 10)->default('pdf');
                $table->string('editor_pdf_path')->nullable();
                $table->string('conversion_status', 20)->default('not_required');
                $table->string('conversion_error', 500)->nullable();
                $table->unsignedInteger('conversion_attempt')->default(0);
                $table->timestamp('converted_at')->nullable();
                $table->timestamp('conversion_started_at')->nullable();
                $table->timestamp('conversion_reviewed_at')->nullable();
                $table->unsignedInteger('page_count')->nullable()->change();
            });
            DB::table('documents')->update(['editor_pdf_path' => DB::raw('file_path')]);
        });
    }

    public function down(): void
    {
        // Refuse rollback while Word records exist; losing their metadata is unsafe.
        if (DB::table('documents')->where('source_format', '!=', 'pdf')->exists()) {
            throw new RuntimeException('Remove or export Word documents before rolling back Word support.');
        }
        $this->changeSchema(function () {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn(['source_format', 'editor_pdf_path', 'conversion_status', 'conversion_error', 'conversion_attempt', 'converted_at', 'conversion_started_at', 'conversion_reviewed_at']);
                $table->unsignedInteger('page_count')->nullable(false)->change();
            });
        });
    }

    private function changeSchema(callable $change): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints(fn () => DB::transaction($change));
        } else {
            $change();
        }
    }
};
