<?php

namespace App\Platform\Notifications;

use App\Platform\Devices\Device;
use App\Platform\Notifications\Push\PushGateway;
use App\Platform\Notifications\Push\PushUnavailable;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Pushes one notification to every signed-in phone of its person (M20).
 * Carries the company and restores it, as every queued job must. A token
 * Firebase no longer knows is forgotten; a busy service is tried again
 * later (up to three times); nothing here ever fails the request that made
 * the notification.
 */
class SendPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $companyId, public int $notificationId) {}

    public function handle(PushGateway $gateway, CompanyContext $context): void
    {
        $company = Company::find($this->companyId);
        if (! $company) {
            return;
        }
        $context->runAs($company, function () use ($gateway, $company) {
            $note = UserNotification::find($this->notificationId);
            if (! $note || $note->read_at) {
                return;
            }
            $devices = Device::where('account_id', $note->account_id)->whereNull('revoked_at')->whereNotNull('push_token')->get();
            if ($devices->isEmpty()) {
                $note->forceFill(['push_result' => 'no_device'])->save();

                return;
            }

            $message = [
                'title' => $note->title, 'body' => $note->body, 'collapse' => $note->collapse_key ? $note->kind . ':' . $note->collapse_key : null, 'priority' => $note->priority ?? 'medium',
                'badge' => UserNotification::for($note->account_id)->whereNull('read_at')->count(),
                // The phone opens this: the company first (a person may work for two), then what the notice is about.
                'data' => array_map('strval', ['notification' => $note->id, 'kind' => $note->kind, 'priority' => $note->priority ?? 'medium', 'company' => $company->uuid] + ($note->data ?? [])),
            ];
            $results = [];
            foreach ($devices as $device) {
                try {
                    $result = $gateway->send($device->push_token, $message);
                } catch (PushUnavailable $e) {
                    if ($this->job && $this->job->getConnectionName() !== 'sync' && $this->attempts() < $this->tries) {
                        $note->forceFill(['push_result' => 'waiting'])->save();
                        $this->release(60 * $this->attempts());

                        return;
                    }
                    $result = PushGateway::FAILED;
                } catch (\Throwable $e) {
                    Log::warning('Push failed', ['notification' => $note->id, 'error' => $e->getMessage()]);
                    $result = PushGateway::FAILED;
                }
                if ($result === PushGateway::INVALID_TOKEN) {
                    $device->forceFill(['push_token' => null])->save();
                }
                $results[] = $result;
            }
            $note->forceFill(['pushed_at' => now(), 'push_result' => in_array(PushGateway::SENT, $results, true) ? 'sent' : (in_array(PushGateway::FAILED, $results, true) ? 'failed' : 'no_device')])->save();
        });
    }
}
