<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M17: deleting a company (blueprint 09 §8). The owner asks; 72
 * hours later the company's data is removed, except one encrypted copy kept
 * for the years tax law requires, then removed too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('deletion_requested_by')->nullable()->after('deletion_requested_at')->constrained('accounts')->nullOnDelete();
            $table->string('deletion_reason', 255)->nullable()->after('deletion_requested_by');
            $table->timestamp('deleted_at')->nullable()->after('deletion_reason')->comment('Data removed; the row stays for the console and the law copy');
            $table->string('retention_path', 255)->nullable()->after('deleted_at')->comment('The encrypted copy kept for the law');
            $table->date('retention_until')->nullable()->after('retention_path');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deletion_requested_by');
            $table->dropColumn(['deletion_reason', 'deleted_at', 'retention_path', 'retention_until']);
        });
    }
};
