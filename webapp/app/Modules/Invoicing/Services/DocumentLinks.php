<?php

namespace App\Modules\Invoicing\Services;

use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Secure public links to documents, /d/{token} (blueprint 10 §8): a random
 * 40-character token, kept with the company it belongs to so the link opens
 * without a sign-in and without guessing a company. Drafts never get one.
 */
class DocumentLinks
{
    public function __construct(private CompanyContext $context) {}

    public function create(string $type, int $id): string
    {
        $token = Str::random(40);
        DB::table('document_links')->insert([
            'token' => $token, 'company_id' => $this->context->id(), 'document_type' => $type, 'document_id' => $id, 'created_at' => now(),
        ]);

        return $token;
    }

    /** @return ?object{company_id: int, document_type: string, document_id: int} */
    public function find(string $token): ?object
    {
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }

        return DB::table('document_links')->where('token', $token)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->first();
    }
}
