<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Platform\Audit\AuditLog;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settings → Activity: the company's audit log — who did what, when, from
 * where — newest first, filtered by date, person and kind of change. It can
 * be read, never changed (AuditLog refuses updates and deletes).
 */
class ActivityController extends Controller
{
    /** filter => [label, action prefixes] */
    public const AREAS = [
        'company'   => ['Company', ['company.', 'branch.']],
        'rules'     => ['Rules', ['settings.']],
        'team'      => ['Team', ['user.', 'company_user.']],
        'roles'     => ['Roles', ['role.', 'role_permission.']],
        'signin'    => ['Sign-in', ['account.', 'login.']],
        'masters'   => ['Places and lists', ['place.', 'master_item.']],
        'numbering' => ['Numbering', ['document_sequence.']],
    ];

    public function __construct(private CompanyContext $context) {}

    public function index(Request $request): View
    {
        $data = $request->validate([
            'from'  => ['nullable', 'date_format:Y-m-d'],
            'to'    => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'who'   => ['nullable', 'string', 'max:100'],
            'area'  => ['nullable', 'string', 'max:30'],
        ]);

        $tz   = $this->context->company()->timezone ?: 'UTC';
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'], $tz)->startOfDay()->utc() : null;
        $to   = isset($data['to']) ? CarbonImmutable::parse($data['to'], $tz)->endOfDay()->utc() : null;
        $area = isset(self::AREAS[$data['area'] ?? '']) ? $data['area'] : null;

        $entries = AuditLog::where('company_id', $this->context->id())
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->when(filled($data['who'] ?? null), fn ($q) => $q->where('actor_label', 'like', '%' . addcslashes($data['who'], '%_\\') . '%'))
            ->when($area, fn ($q) => $q->where(function ($w) use ($area) {
                foreach (self::AREAS[$area][1] as $prefix) {
                    $w->orWhere('action', 'like', $prefix . '%');
                }
            }))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('settings.activity', [
            'entries' => $entries,
            'filters' => ['from' => $data['from'] ?? '', 'to' => $data['to'] ?? '', 'who' => $data['who'] ?? '', 'area' => $area ?? ''],
            'areas'   => self::AREAS,
            'tz'      => $tz,
        ]);
    }
}
