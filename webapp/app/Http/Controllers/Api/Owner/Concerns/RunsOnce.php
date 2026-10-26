<?php

namespace App\Http\Controllers\Api\Owner\Concerns;

use App\Platform\Devices\ApiCommand;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owner-app writes run once (M23): every one carries an `Idempotency-Key`
 * header (a UUID the app makes for this tap). The answer is kept, like the
 * crew app's commands, so a retry after a lost answer gets the same answer
 * and makes no second trip, bill or receipt. A refused request (422) is not
 * kept: the person corrects it and sends it again with the same key.
 */
trait RunsOnce
{
    protected function once(Request $request, string $name, Closure $work): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key');
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            return response()->json(['message' => __('Send an Idempotency-Key header (a new UUID for each action) so a retry never runs it twice.')], 428);
        }
        if ($kept = $this->keptAnswer($key, $name)) {
            return $kept;
        }

        try {
            return DB::transaction(function () use ($key, $name, $work) {
                // Written first: a copy arriving at the same moment waits on the unique key, then finds this answer.
                $row = ApiCommand::create(['uuid' => $key, 'company_id' => $this->context->id(), 'account_id' => (int) auth()->id(),
                    'name' => mb_substr('owner.' . $name, 0, 30), 'http_status' => 0, 'result' => []]);
                $body = $work();
                $row->forceFill(['http_status' => 201, 'result' => $body])->save();

                return response()->json($body, 201);
            });
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (UniqueConstraintViolationException) {
            return $this->keptAnswer($key, $name) ?? response()->json(['message' => __('This action is already being handled. Send it again in a moment.')], 409);
        }
    }

    private function keptAnswer(string $key, string $name): ?JsonResponse
    {
        $row = ApiCommand::where('uuid', $key)->first();
        if (! $row) {
            return null;
        }
        if ($row->company_id !== $this->context->id() || $row->account_id !== (int) auth()->id() || $row->name !== mb_substr('owner.' . $name, 0, 30)) {
            return response()->json(['message' => __('This Idempotency-Key was used for something else. Make a new one for each action.')], 409);
        }

        return response()->json($row->result + ['replayed' => true], $row->http_status);
    }
}
