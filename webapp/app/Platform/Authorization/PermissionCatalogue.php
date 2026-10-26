<?php

namespace App\Platform\Authorization;

/**
 * The permission catalogue and the standard role ladder (blueprint 02 §3).
 *
 * Product content, not company data, so it lives in code. A permission code
 * is a contract: screens and routes ask for codes, never for role names,
 * because a company may rename a role or change what it may do.
 *
 * Two rules shape the list:
 *  · Deny by default. A missing permission is a 403, never a 200.
 *  · Money and legal-document settings are separate from day-to-day work,
 *    so a dispatcher can run trips without seeing bank details.
 */
final class PermissionCatalogue
{
    /** code => [description, elevated?] — "elevated" grants need role.grant_elevated. */
    public const PERMISSIONS = [
        // ---- Everyday ---------------------------------------------------------
        'dashboard.view'        => ['See the dashboard', false],
        'map.view'              => ['See the live map and route replay', false],

        // ---- Trips ------------------------------------------------------------
        'trip.view'             => ['See every trip', false],
        'trip.view_own'         => ['See the trips you are crew on', false],
        'trip.create'           => ['Create and plan trips', false],
        'trip.assign'           => ['Send trips to the crew', false],
        'trip.cancel'           => ['Cancel a trip that has not started', false],
        'trip.freight_view'     => ['See the freight amount on trips', false],
        'trip.settle'           => ['Settle crew cash and close trips', false],
        'trip.office_entries'   => ['Add office income and expenses to trips, also after close', false],
        'trip.reopen'           => ['Reopen a closed trip', true],
        'event.record'          => ['Record road events from the crew app', false],
        'event.verify'          => ['Approve, query or reject crew entries', false],
        'event.second_approve'  => ['Give the second approval on payments without receipt', false],

        // ---- Fleet ------------------------------------------------------------
        'truck.view'            => ['See trucks', false],
        'truck.manage'          => ['Add and change trucks', false],
        'document.view'         => ['See documents', false],
        'document.manage'       => ['Add, renew and share documents', false],
        'complaint.raise'       => ['Raise a truck complaint', false],
        'complaint.manage'      => ['Work on complaints, service jobs and tyres', false],

        // ---- People -----------------------------------------------------------
        'people.view'           => ['See crew and staff', false],
        'people.manage'         => ['Add and change crew and staff', false],
        'people.id_documents'   => ['See crew ID documents (licence, Aadhaar, Emirates ID)', true],
        'leave.apply'           => ['Apply for leave', false],
        'leave.approve'         => ['Approve leave requests', false],

        // ---- Parties ------------------------------------------------------------
        'party.view'            => ['See clients, suppliers and vendors', false],
        'party.manage'          => ['Add and change clients, suppliers and vendors', false],

        // ---- Invoices & LR --------------------------------------------------------
        'lr.view'               => ['See LRs (consignment notes)', false],
        'lr.manage'             => ['Create and change LRs', false],
        'invoice.view'          => ['See invoices', false],
        'invoice.create'        => ['Draft invoices', false],
        'invoice.finalise'      => ['Finalise and number invoices', false],
        'invoice.send'          => ['Send invoices by email and WhatsApp', false],
        'invoice.void'          => ['Void a finalised invoice', true],
        'hire.manage'           => ['Create hire challans for hired trucks', false],

        // ---- Money ------------------------------------------------------------------
        'payment.view'          => ['See receipts and payments', false],
        'payment.record'        => ['Record receipts and payments', false],
        'payment.void'          => ['Void a receipt or payment', true],
        'ledger.view'           => ['See all ledgers and balances', false],
        'ledger.own'            => ['See your own ledger (My Khata)', false],
        'statement.export'      => ['Export and share statements', false],
        'accounts.view'         => ['See the books (vouchers, statements)', false],
        'accounts.post'         => ['Post journal and other vouchers', false],
        'accounts.lock'         => ['Lock and unlock accounting periods', true],

        // ---- Payroll -----------------------------------------------------------------
        'payroll.view'          => ['See salaries and pay runs', false],
        'payroll.run'           => ['Run and pay salaries', false],
        'payroll.own'           => ['See your own salary and payslips', false],

        // ---- Reports -------------------------------------------------------------------
        'report.view'           => ['See reports', false],
        'report.export'         => ['Export reports', false],
        'report.tax'            => ['See and export tax reports', false],

        // ---- Settings ------------------------------------------------------------------
        'settings.company'      => ['Change the company identity and address', false],
        'settings.rules'        => ['Change trip rules (dual approval, auto-approve, bata…)', false],
        'settings.masters'      => ['Change places, categories and other lists', false],
        'settings.numbering'    => ['Change number formats', false],
        'settings.templates'    => ['Change document templates', false],
        'settings.tax'          => ['Change tax registrations, codes and rules', false],
        'settings.bank'         => ['See and change bank accounts', true],

        // ---- Team & security ------------------------------------------------------------
        'user.invite'           => ['Invite a team member', false],
        'user.edit'             => ['Change a team member', false],
        'user.remove'           => ['Remove a team member', false],
        'role.grant'            => ['Give roles to team members', false],
        'role.grant_elevated'   => ['Give the Owner role', true],
        'role.define'           => ['Create and change roles', true],
        'security.configure'    => ['Change sign-in rules and two-factor policy', true],
        'security.unlock'       => ['Unlock a locked team member', false],
        'audit.view'            => ['Read the audit log and sign-in activity', false],
        'device.manage'         => ['See and remove your own signed-in devices', false],

        // ---- Account ------------------------------------------------------------------
        'billing.view'          => ['See the subscription', false],
        'billing.manage'        => ['Change the plan and payment method', true],
        'data.export'           => ['Download an export of all company data', true],
        'company.delete'        => ['Delete the company and all its data', true],
    ];

    /** Held by the Owner only when a company is set up (the Owner may then share them). */
    public const OWNER_ONLY_BY_DEFAULT = [
        'settings.bank', 'role.define', 'role.grant_elevated', 'security.configure',
        'billing.manage', 'data.export', 'company.delete', 'invoice.void', 'payment.void',
        'trip.reopen', 'accounts.lock',
    ];

    /**
     * The standard roles (blueprint 02 §2). code => [name, level, permissions|'*']
     */
    public const ROLES = [
        'owner' => ['Owner', 0, '*'],

        'manager' => ['Manager', 1, [
            'dashboard.view', 'map.view', 'trip.*', 'event.verify', 'event.second_approve',
            'truck.*', 'document.*', 'complaint.*', 'people.view', 'people.manage', 'leave.approve',
            'party.*', 'lr.*', 'invoice.view', 'invoice.create', 'invoice.send', 'hire.manage',
            'payment.view', 'ledger.view', 'statement.export', 'report.view', 'report.export',
            'settings.masters', 'security.unlock', 'device.manage', 'billing.view',
        ]],

        'accountant' => ['Accountant', 2, [
            'dashboard.view', 'trip.view', 'trip.freight_view', 'trip.office_entries', 'trip.settle',
            'party.*', 'lr.view', 'invoice.*', 'hire.manage', 'payment.*', 'ledger.view',
            'statement.export', 'accounts.*', 'payroll.view', 'payroll.run', 'report.*',
            'settings.numbering', 'settings.templates', 'settings.tax', 'device.manage',
        ]],

        'workshop' => ['Workshop', 4, [
            'dashboard.view', 'truck.view', 'document.view', 'complaint.*', 'device.manage',
        ]],

        'viewer' => ['Viewer', 5, [
            'dashboard.view', 'map.view', 'trip.view', 'trip.freight_view', 'truck.view',
            'document.view', 'people.view', 'party.view', 'lr.view', 'invoice.view',
            'payment.view', 'ledger.view', 'report.view', 'device.manage',
        ]],

        'driver' => ['Driver', 6, [
            'trip.view_own', 'event.record', 'event.second_approve', 'complaint.raise',
            'leave.apply', 'ledger.own', 'payroll.own', 'device.manage',
        ]],

        'helper' => ['Helper', 7, [
            'trip.view_own', 'complaint.raise', 'leave.apply', 'ledger.own', 'payroll.own',
            'device.manage',
        ]],
    ];

    /** A standard role's privileges when a company is set up. */
    public static function defaultsFor(string $roleCode): array
    {
        $codes = self::expand(self::ROLES[$roleCode][2] ?? []);

        return $roleCode === 'owner' ? $codes : array_values(array_diff($codes, self::OWNER_ONLY_BY_DEFAULT));
    }

    /** @return string[] */
    public static function codes(): array
    {
        return array_keys(self::PERMISSIONS);
    }

    /** Expand 'trip.*' into the codes it covers; pass exact codes through. */
    public static function expand(array|string $patterns): array
    {
        if ($patterns === '*') {
            return self::codes();
        }

        $out = [];
        foreach ((array) $patterns as $pattern) {
            if (! str_ends_with($pattern, '.*')) {
                $out[] = $pattern;
                continue;
            }
            $prefix = substr($pattern, 0, -1);
            foreach (self::codes() as $code) {
                if (str_starts_with($code, $prefix)) {
                    $out[] = $code;
                }
            }
        }

        return array_values(array_unique($out));
    }

    public static function exists(string $code): bool
    {
        return isset(self::PERMISSIONS[$code]);
    }

    public static function isElevated(string $code): bool
    {
        return self::PERMISSIONS[$code][1] ?? false;
    }
}
