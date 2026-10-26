<?php

namespace App\Platform\Auth;

use App\Models\Account;
use App\Platform\Audit\AuditRecorder;
use App\Support\Images;
use App\Support\Locales;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * A person's own profile (M28, 13-parity #14, #28), the same on the web and
 * in both apps: their name, screen language, date of birth (optional) and a
 * photo — the middle square of the picture, kept small and private.
 *
 * The email and mobile are how they sign in, so they are shown here but not
 * changed here (a new one would need to be confirmed first).
 */
class Profile
{
    public function __construct(private AuditRecorder $audit) {}

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'locale' => ['nullable', Rule::in(Locales::enabled())],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'after:1920-01-01', 'before:' . CarbonImmutable::today()->subYears(14)->toDateString()],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['date_of_birth.before' => __('Check the date of birth.'), 'date_of_birth.after' => __('Check the date of birth.'), 'photo.max' => __('The photo may be at most 8 MB.')];
    }

    /** @param array{name: string, locale?: ?string, date_of_birth?: ?string, remove_photo?: mixed} $data */
    public function save(Account $account, array $data, ?UploadedFile $photo = null): Account
    {
        $fields = ['name' => trim($data['name']), 'date_of_birth' => ($data['date_of_birth'] ?? null) ?: null];
        if (! empty($data['locale'])) {
            $fields['locale'] = $data['locale'];
        }
        $old = $account->photo_path;
        if ($photo) {
            // Re-saved as a small JPEG: the camera's hidden data (location, phone) is dropped.
            $path = 'accounts/' . $account->id . '/photo-' . now()->format('YmdHis') . '.jpg';
            Storage::disk('local')->put($path, Images::square($photo->getRealPath()));
            $fields['photo_path'] = $path;
        } elseif (filter_var($data['remove_photo'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $fields['photo_path'] = null;
        }

        $account->fill($fields);
        $changed = array_keys($account->getDirty());
        $account->save();
        if ($old && array_key_exists('photo_path', $fields) && $old !== $fields['photo_path']) {
            Storage::disk('local')->delete($old);
        }
        if ($changed) {
            $this->audit->record('account.profile_updated', "{$account->name} changed their profile (" . implode(', ', $changed) . ')', 'account', (string) $account->id, $account->name);
        }

        return $account;
    }

    /** The profile as the apps read it; the photo URL changes when the photo does. */
    public function toApi(Account $account): array
    {
        return [
            'name' => $account->name, 'initials' => $account->initials(),
            'email' => $account->email, 'email_verified' => $account->email_verified_at !== null,
            'mobile' => $account->mobile, 'mobile_verified' => $account->mobile_verified_at !== null,
            'locale' => $account->locale, 'languages' => collect(Locales::enabled())->mapWithKeys(fn ($c) => [$c => Locales::NATIVE[$c]]),
            'date_of_birth' => $account->date_of_birth?->toDateString(),
            'photo_url' => $account->photo_path ? route('api.profile.photo', ['v' => substr(md5($account->photo_path), 0, 8)]) : null,
        ];
    }
}
