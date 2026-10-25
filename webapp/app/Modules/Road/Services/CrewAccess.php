<?php

namespace App\Modules\Road\Services;

use App\Modules\People\Models\Person;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Trips\Models\Trip;
use App\Platform\Authorization\PermissionResolver;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Validation\ValidationException;

/**
 * Who is asking, on the crew app: the Person linked to the signed-in
 * membership (a login without a person record is an office user, who uses
 * the owner app), and what they may do on the road.
 */
class CrewAccess
{
    private ?Person $person = null;

    public function __construct(
        private CompanyContext $context,
        private PermissionResolver $permissions,
        private CompanySettings $settings,
    ) {}

    public function person(): ?Person
    {
        $member = $this->context->member();
        if (! $member) {
            return null;
        }
        if ($this->person?->company_user_id !== $member->id) {
            $this->person = Person::where('company_user_id', $member->id)->first();
        }

        return $this->person;
    }

    public function can(string $code): bool
    {
        $member = $this->context->member();

        return $member !== null && $this->permissions->has($member, $code);
    }

    /** Drivers record road events; helpers too when the owner allows it (Settings → Rules). */
    public function canRecord(): bool
    {
        return $this->can('event.record')
            || ($this->settings->get('helpers_record_events') && $this->person()?->type !== 'office' && $this->can('trip.view_own'));
    }

    /** The trip, if this person is on its crew. */
    public function trip(Person $person, ?string $uuid): Trip
    {
        $trip = $uuid ? Trip::with(['crew', 'truck'])->where('uuid', $uuid)->first() : null;
        if (! $trip || ! $trip->crew->contains('person_id', $person->id)) {
            throw ValidationException::withMessages(['trip' => __('You are not on this trip.')]);
        }

        return $trip;
    }

    /** driver | co_driver | helper, or null when not on the crew. */
    public function roleOn(Trip $trip, Person $person): ?string
    {
        $trip->loadMissing('crew');

        return $trip->crew->firstWhere('person_id', $person->id)?->role;
    }

    public function assertAtTheWheel(Trip $trip, Person $person): void
    {
        if (! in_array($this->roleOn($trip, $person), ['driver', 'co_driver'], true)) {
            throw ValidationException::withMessages(['trip' => __('Only the driver or co-driver can do this.')]);
        }
    }
}
