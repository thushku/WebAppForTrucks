<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;

/**
 * "Settings" in the main menu opens the first settings page the person may
 * use. The pages and the permission each one needs are listed once, in
 * PAGES, for the menu and the side list alike.
 */
class SettingsController extends Controller
{
    /** route => [label, icon, permissions (any of)] */
    public const PAGES = [
        'settings.company'   => ['Company', 'building', ['settings.company']],
        'settings.branches'  => ['Branches', 'map-pin', ['settings.company']],
        'settings.rules'     => ['Trip rules', 'route', ['settings.rules']],
        'settings.team'      => ['Team', 'users', ['user.invite', 'user.edit', 'user.remove', 'role.grant', 'security.unlock']],
        'settings.roles'     => ['Roles', 'shield', ['role.define', 'role.grant']],
        'settings.places'    => ['Places', 'map', ['settings.masters']],
        'settings.lists'     => ['Lists', 'tag', ['settings.masters']],
        'settings.numbering' => ['Numbering', 'file-text', ['settings.numbering']],
        'settings.tax'       => ['Tax', 'receipt', ['settings.tax']],
        'settings.banks'     => ['Bank accounts', 'wallet', ['settings.bank']],
        'settings.templates' => ['Templates', 'palette', ['settings.templates']],
        'settings.security'  => ['Sign-in security', 'lock', ['security.configure']],
        'settings.activity'  => ['Activity', 'clock', ['audit.view']],
        'billing.index'      => ['Plan and billing', 'credit-card', ['billing.view']],
    ];

    /** Every permission that opens some settings page. */
    public static function anyPermission(): array
    {
        return array_values(array_unique(array_merge(...array_column(self::PAGES, 2))));
    }

    public function __invoke(CompanyContext $context): RedirectResponse
    {
        foreach (self::PAGES as $route => [, , $permissions]) {
            if (auth()->user()->canAny($permissions)) {
                return redirect()->route($route);
            }
        }

        abort(403);
    }
}
