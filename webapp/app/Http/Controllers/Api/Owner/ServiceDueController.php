<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Services\Maintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The owner app's Service due (M50): the web's list — overdue or coming up, with the likely date from each truck's pace. */
class ServiceDueController extends Controller
{
    public function index(Request $request, Maintenance $maintenance): JsonResponse
    {
        $days = (int) ($request->validate(['days' => ['nullable', 'integer', Rule::in([7, 30, 60, 90])]])['days'] ?? Maintenance::SOON_DAYS);
        $trucks = Truck::inBranch()->where('ownership', '!=', 'attached')->where('status', '!=', 'sold')->get(['id', 'reg_no', 'odometer_km', 'status', 'ownership']);

        return response()->json([
            'days' => $days,
            'services' => $maintenance->due($trucks, $days)->map(fn ($r) => [
                'truck' => ['id' => $r['truck']->id, 'reg_no' => $r['truck']->reg_no, 'odometer_km' => $r['truck']->odometer_km],
                'schedule_id' => $r['schedule']->id, 'name' => $r['schedule']->name, 'every_km' => $r['schedule']->every_km, 'every_days' => $r['schedule']->every_days,
                'state' => $r['state'], 'due_km' => $r['due_km'], 'km_left' => $r['km_left'], 'due_on' => $r['due_on']?->toDateString(),
                'expected_on' => $r['expected_on']?->toDateString(), 'expected_by' => $r['by'], 'km_a_day' => $r['pace'],
            ])->values(),
        ]);
    }
}
