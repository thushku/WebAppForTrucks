<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\ServiceJob;
use App\Modules\Fleet\Models\ServiceSchedule;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Services\FleetService;
use App\Modules\Parties\Models\Party;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Service schedules and service jobs of a truck. A job of another truck in
 * the URL is a 404.
 */
class ServiceController extends Controller
{
    public function __construct(private CompanyContext $context, private FleetService $fleet) {}

    // ---- Schedules -------------------------------------------------------------------

    public function storeSchedule(Request $request, Truck $truck): RedirectResponse
    {
        $truck->serviceSchedules()->create($this->scheduleData($request));

        return redirect()->route('trucks.show', [$truck, 'tab' => 'service'])->with('status', __('Service schedule added.'));
    }

    public function updateSchedule(Request $request, Truck $truck, ServiceSchedule $schedule): RedirectResponse
    {
        abort_unless($schedule->truck_id === $truck->id, 404);
        $schedule->update($this->scheduleData($request) + ['active' => $request->boolean('active', true)]);

        return redirect()->route('trucks.show', [$truck, 'tab' => 'service'])->with('status', __('Saved.'));
    }

    // ---- Jobs ------------------------------------------------------------------------

    public function create(Request $request, Truck $truck): View
    {
        $job = new ServiceJob(['job_date' => now()->toDateString(), 'odometer_km' => $truck->odometer_km, 'kind' => 'scheduled',
            'service_schedule_id' => $request->integer('schedule') ?: null]);

        return view('service-jobs.form', $this->formData($truck, $job));
    }

    public function store(Request $request, Truck $truck): RedirectResponse
    {
        [$fields, $items] = $this->validated($request, $truck);
        $job = $this->fleet->saveJob(null, $truck, $fields, $items);

        return redirect()->route('trucks.service.edit', [$truck, $job])->with('status', __('Service job :no saved.', ['no' => $job->number]));
    }

    public function edit(Truck $truck, ServiceJob $job): View
    {
        $this->own($truck, $job);

        return view('service-jobs.form', $this->formData($truck, $job->load('items')));
    }

    public function update(Request $request, Truck $truck, ServiceJob $job): RedirectResponse
    {
        $this->own($truck, $job);
        [$fields, $items] = $this->validated($request, $truck);
        $this->fleet->saveJob($job, $truck, $fields, $items);

        return back()->with('status', __('Saved.'));
    }

    public function complete(Truck $truck, ServiceJob $job): RedirectResponse
    {
        $this->own($truck, $job);
        $this->fleet->completeJob($job);

        return redirect()->route('trucks.show', [$truck, 'tab' => 'service'])->with('status', __('Job :no marked done.', ['no' => $job->number]));
    }

    public function cancel(Truck $truck, ServiceJob $job): RedirectResponse
    {
        $this->own($truck, $job);
        if ($job->status !== 'open') {
            throw ValidationException::withMessages(['job' => __('Only an open job can be cancelled.')]);
        }
        $job->forceFill(['status' => 'cancelled'])->save();

        return redirect()->route('trucks.show', [$truck, 'tab' => 'service'])->with('status', __('Job :no cancelled; its number stays used.', ['no' => $job->number]));
    }

    private function own(Truck $truck, ServiceJob $job): void
    {
        abort_unless($job->truck_id === $truck->id, 404);
    }

    private function formData(Truck $truck, ServiceJob $job): array
    {
        return [
            'truck'     => $truck,
            'job'       => $job,
            'vendors'   => Party::active()->withRole('vendor')->orderBy('name')->pluck('name', 'id'),
            'schedules' => $truck->serviceSchedules()->where('active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function scheduleData(Request $request): array
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'min:2', 'max:80'],
            'every_km'     => ['nullable', 'integer', 'min:100', 'max:1000000', 'required_without:every_days'],
            'every_days'   => ['nullable', 'integer', 'min:1', 'max:3650'],
            'last_done_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'last_done_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], ['every_km.required_without' => __('Give the km, the days, or both.')]);

        return $data;
    }

    /** @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>} */
    private function validated(Request $request, Truck $truck): array
    {
        $companyId = $this->context->id();
        $data = $request->validate([
            'job_date'            => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'odometer_km'         => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'vendor_party_id'     => ['nullable', 'integer', Rule::exists('parties', 'id')->where('company_id', $companyId)],
            'kind'                => ['required', Rule::in(array_keys(ServiceJob::KINDS))],
            'service_schedule_id' => ['nullable', 'integer', Rule::exists('service_schedules', 'id')->where('truck_id', $truck->id)],
            'description'         => ['nullable', 'string', 'max:3000'],
            'bill_no'             => ['nullable', 'string', 'max:40'],
            'items'               => ['nullable', 'array', 'max:200'],
            'items.*.kind'        => ['nullable', Rule::in(['part', 'labour'])],
            'items.*.description' => ['nullable', 'string', 'max:200'],
            'items.*.qty'         => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'items.*.rate'        => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $items = [];
        foreach ($data['items'] ?? [] as $i => $item) {
            if (! filled($item['description'] ?? null) && ! filled($item['rate'] ?? null)) {
                continue;
            }
            if (! filled($item['description'] ?? null)) {
                throw ValidationException::withMessages(["items.{$i}.description" => __('Row :n: say what the part or work is.', ['n' => $i + 1])]);
            }
            $items[] = ['kind' => $item['kind'] ?? 'part', 'description' => trim($item['description']), 'qty' => (float) ($item['qty'] ?? 1) ?: 1, 'rate' => (float) ($item['rate'] ?? 0)];
        }

        return [collect($data)->except('items')->all(), $items];
    }
}
