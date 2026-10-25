<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Document Manager (blueprint 09 §6, 06 §11.4, 13 §D).
 *
 * A document is the current paper of one owner — "insurance of
 * KL-07-AB-1234", "Ravi's driving licence", "our GST certificate". Renewing
 * it copies the current details into document_versions (read-only history)
 * and the document takes the new details, so every list and expiry check
 * reads one row. Files belong to a version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('owner_type', 12)->comment('truck | person | company | party');
            $table->unsignedBigInteger('owner_id')->default(0)->comment('0 for the company itself');
            $table->string('document_type', 40)->comment('Master list code (document_type)');
            $table->string('number', 60)->nullable();
            $table->string('authority', 120)->nullable()->comment('Issued by: RTO, insurer, RTA…');
            $table->date('issued_on')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('expires_on')->nullable()->comment('Empty for papers that do not expire (PAN)');
            $table->decimal('amount', 14, 2)->nullable()->comment('What it cost');
            $table->foreignId('vendor_party_id')->nullable()->constrained('parties')->nullOnDelete()->comment('Insurer or agent');
            $table->string('original_location', 120)->nullable()->comment('Where the original paper is kept');
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->smallInteger('last_reminder_days')->nullable()->comment('The reminder step last sent (30, 15, 7, 1, 0, -7…)');
            $table->timestamp('reminded_at')->nullable();
            $table->date('snoozed_until')->nullable();
            $table->string('snooze_reason', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'owner_type', 'owner_id']);
            $table->index(['company_id', 'expires_on']);
            $table->index(['company_id', 'document_type']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('number', 60)->nullable();
            $table->string('authority', 120)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('expires_on')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->unsignedBigInteger('vendor_party_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('renewed_by')->nullable()->constrained('accounts')->nullOnDelete()->comment('Who replaced this version');
            $table->timestamp('archived_at');

            $table->unique(['document_id', 'version']);
        });

        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('path');
            $table->string('original_name', 200)->nullable();
            $table->string('mime', 60);
            $table->unsignedInteger('bytes');
            $table->char('sha256', 64);
            $table->string('label', 40)->nullable()->comment('Front, back, page 2…');
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'version']);
        });

        // "Every 12-wheeler needs a national permit"; "every driver needs a licence" (13 §D #17).
        Schema::create('document_required_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('applies_to', 12)->comment('truck | person | company');
            $table->string('subtype', 40)->nullable()->comment('Truck type or person type; empty = all');
            $table->string('document_type', 40);
            $table->timestamps();

            $table->unique(['company_id', 'applies_to', 'subtype', 'document_type'], 'document_required_unique');
        });

        // A pack of documents shared by an expiring link, with a watermark.
        Schema::create('document_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('purpose', 150);
            $table->string('watermark', 200);
            $table->json('document_ids');
            $table->timestamp('expires_at');
            $table->unsignedInteger('opened_count')->default(0);
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_shares');
        Schema::dropIfExists('document_required_types');
        Schema::dropIfExists('document_files');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
