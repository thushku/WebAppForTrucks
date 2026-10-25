<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M10: document templates and the template editor (blueprint 10
 * §9, ported from Clinic Ledger's design system).
 *
 *  · document_templates: named designs per document family (bills, LR,
 *    receipt) — a theme, colours, font, paper, what prints, titles, label
 *    renames, the copy set — a default for the company, and defaults per
 *    client or branch;
 *  · document_template_versions: every save, so a template rolls back and an
 *    issued document keeps the version it was printed with (the design is
 *    also frozen on the document itself);
 *  · invoices.document_template_id: a draft bill may print with a template
 *    other than its client's default (chosen in the builder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('family', 10)->comment('invoice | lr | receipt');
            $table->string('name', 80);
            $table->json('design');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->json('party_ids')->nullable()->comment('Clients that print with this template');
            $table->json('branch_ids')->nullable()->comment('Branches that print with this template');
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'family']);
        });

        Schema::create('document_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('design');
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_template_id', 'version']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('document_template_id')->nullable()->after('design_snapshot')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_template_id');
        });
        Schema::dropIfExists('document_template_versions');
        Schema::dropIfExists('document_templates');
    }
};
