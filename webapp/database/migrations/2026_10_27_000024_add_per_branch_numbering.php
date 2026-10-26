<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M30: a document type can be numbered in a separate series per
 * branch (LRs, quotations, hire challans: each branch its own printed run).
 * The switch is on the company-wide series; the branch series are its
 * scoped copies (scope_type "branch"), like the per-GSTIN invoice series
 * (scope_type "registration").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_sequences', function (Blueprint $table) {
            $table->boolean('per_branch')->default(false)->after('max_length')->comment('Each branch numbers in its own series (company-wide row only)');
        });
    }

    public function down(): void
    {
        Schema::table('document_sequences', fn (Blueprint $table) => $table->dropColumn('per_branch'));
    }
};
