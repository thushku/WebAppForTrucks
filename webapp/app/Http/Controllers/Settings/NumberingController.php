<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\DocumentSequence;
use App\Modules\Settings\Services\DocumentNumbering;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Settings → Numbering: the format of every document number, with a live
 * preview of the next one. In India a tax invoice number may have at most
 * 16 characters, only letters, digits, "-" and "/" (GST Rule 46), so the
 * screen refuses a format that could break that.
 */
class NumberingController extends Controller
{
    public function __construct(private DocumentNumbering $numbering, private CompanyContext $context) {}

    public function index(): View
    {
        $sequences = collect(array_keys(DocumentSequence::DOC_TYPES))
            ->map(fn ($type) => $this->numbering->sequence($type));

        return view('settings.numbering', [
            'sequences' => $sequences,
            'previews'  => $sequences->mapWithKeys(fn ($s) => [$s->id => $this->numbering->preview($s)]),
            'nexts'     => $sequences->mapWithKeys(fn ($s) => [$s->id => $this->numbering->peekNumber($s)]),
            'periods'   => $sequences->mapWithKeys(fn ($s) => [$s->id => $this->numbering->periodKey($s, $this->today())]),
        ]);
    }

    public function update(Request $request, DocumentSequence $sequence): RedirectResponse
    {
        $data = $this->validated($request, $sequence);

        DB::transaction(function () use ($sequence, $data) {
            $sequence->update([
                'prefix'       => $data['prefix'] ?? '',
                'format'       => $data['format'],
                'reset_policy' => $data['reset_policy'],
                'start_number' => $data['start_number'],
            ]);
            $this->numbering->setNext($sequence, (int) $data['next_number']);
        });

        return back()->with('status', __(':document numbering saved. Next: :number', ['document' => $sequence->displayName(), 'number' => $this->numbering->preview($sequence->fresh())]));
    }

    /** The live preview while the format is being typed. */
    public function preview(Request $request, DocumentSequence $sequence): JsonResponse
    {
        try {
            $data = $this->validated($request, $sequence);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'error' => collect($e->errors())->flatten()->first()]);
        }

        $draft = $sequence->replicate()->fill([
            'prefix'       => $data['prefix'] ?? '',
            'format'       => $data['format'],
            'reset_policy' => $data['reset_policy'],
        ]);

        return response()->json(['ok' => true, 'number' => $this->numbering->render($draft, (int) $data['next_number'], $this->today(), DocumentNumbering::SAMPLE_PARTS)]);
    }

    /** Now, on the company's own clock. */
    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC');
    }

    private function validated(Request $request, DocumentSequence $sequence): array
    {
        $chars = DocumentNumbering::allowedCharacters($sequence->doc_type);

        $data = $request->validate([
            'prefix'       => ['nullable', 'string', 'max:20', 'regex:/^[' . $chars . ']*$/'],
            'format'       => ['required', 'string', 'max:60'],
            'reset_policy' => ['required', Rule::in(array_keys(DocumentSequence::RESET_POLICIES))],
            'start_number' => ['required', 'integer', 'min:1', 'max:99999999'],
            'next_number'  => ['required', 'integer', 'min:1', 'max:99999999'],
        ], ['prefix.regex' => __('Use only letters, digits and the characters allowed for this document.')]);

        if (! DocumentNumbering::isValidFormat($data['format'], $chars)) {
            throw ValidationException::withMessages(['format' => __('The format needs exactly one counter such as {0001}, and only the tokens listed below.')]);
        }

        // Numbers that restart must say which period they belong to, or two would be the same.
        $needs = ['fiscal_year' => ['{FY}', '{FY2}', '{YYYY}', '{YY}'], 'monthly' => ['{MM}'], 'daily' => ['{DD}']][$data['reset_policy']] ?? [];
        if ($needs && ! collect($needs)->contains(fn ($t) => str_contains($data['format'], $t))) {
            throw ValidationException::withMessages(['format' => __('A number that restarts must include its period in the format: :tokens.', ['tokens' => implode(' ', $needs)])]);
        }

        if ($sequence->max_length) {
            // Check the widest number this format makes in the next eight years.
            $draft  = $sequence->replicate()->fill(['prefix' => $data['prefix'] ?? '', 'format' => $data['format']]);
            $widest = $this->numbering->render($draft, max((int) $data['next_number'], 99999), now()->addYears(8), DocumentNumbering::SAMPLE_PARTS);
            if (mb_strlen($widest) > $sequence->max_length) {
                throw ValidationException::withMessages(['format' => __('Numbers could reach :length characters (:example); the limit is :max.', ['length' => mb_strlen($widest), 'example' => $widest, 'max' => $sequence->max_length])]);
            }
        }

        return $data;
    }
}
