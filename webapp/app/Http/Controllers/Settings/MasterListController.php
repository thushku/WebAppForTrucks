<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\MasterItem;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings → Lists: the small lists people pick from (materials, expense
 * categories, truck types, document types, leave types, complaint
 * categories). Items from the country preset can be renamed or hidden but
 * not deleted, because older records point at them.
 */
class MasterListController extends Controller
{
    public function __construct(private CompanyContext $context) {}

    public function index(?string $list = null): View
    {
        $list ??= array_key_first(MasterItem::LISTS);
        abort_unless(isset(MasterItem::LISTS[$list]), 404);

        return view('settings.lists', [
            'list'   => $list,
            'lists'  => MasterItem::LISTS,
            'fields' => MasterItem::LISTS[$list][1],
            'items'  => MasterItem::ofList($list)->get(),
        ]);
    }

    public function store(Request $request, string $list): RedirectResponse
    {
        abort_unless(isset(MasterItem::LISTS[$list]), 404);
        $data = $this->validated($request, $list);

        $base = Str::slug($data['name'], '_') ?: 'item';
        $code = Str::limit($base, 36, '');
        for ($i = 2; MasterItem::where('list', $list)->where('code', $code)->exists(); $i++) {
            $code = Str::limit($base, 33, '') . '_' . $i;
        }

        MasterItem::create([
            'list' => $list,
            'code' => $code,
            'name' => $data['name'],
            'meta' => $data['meta'] ?: null,
            'sort' => (int) MasterItem::where('list', $list)->max('sort') + 10,
        ]);

        return back()->with('status', __('Added to the list.'));
    }

    public function update(Request $request, MasterItem $item): RedirectResponse
    {
        $data = $this->validated($request, $item->list);

        $item->update([
            'name'   => $data['name'],
            'meta'   => $data['meta'] ?: null,
            'active' => $request->boolean('active'),
        ]);

        return back()->with('status', __('Saved.'));
    }

    private function validated(Request $request, string $list): array
    {
        $fields = MasterItem::LISTS[$list][1];

        $rules = ['name' => ['required', 'string', 'min:2', 'max:120']];
        foreach ($fields as $field => $label) {
            $rules["meta.{$field}"] = match ($field) {
                'group'         => ['nullable', Rule::in(['running', 'maintenance', 'office'])],
                'owner'         => ['nullable', Rule::in(['truck', 'person', 'company', 'party'])],
                'needs_receipt', 'paid' => ['nullable', 'boolean'],
                'wheels', 'remind_days', 'days_per_year' => ['nullable', 'integer', 'min:0', 'max:400'],
                default         => ['nullable', 'numeric', 'min:0', 'max:1000'],
            };
        }

        $data = $request->validate($rules);
        $meta = [];
        foreach ($fields as $field => $label) {
            $meta[$field] = in_array($field, ['needs_receipt', 'paid'], true)
                ? $request->boolean("meta.{$field}")
                : ($data['meta'][$field] ?? null);
        }

        return ['name' => trim($data['name']), 'meta' => array_filter($meta, fn ($v) => $v !== null)];
    }
}
