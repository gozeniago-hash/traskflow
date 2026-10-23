<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\Audit;
use App\Support\Totp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Checks a sign-in code from the authenticator app, or a recovery code, which is then
 * used up. The person's row stays locked while the code is checked and recorded, so the
 * same code sent twice at once, or two recovery codes used at once, count only as used.
 */
class VerifyTwoFactorCode
{
    public function handle(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $current = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $current->hasTwoFactor()) {
                return false;
            }

            $step = Totp::verify($current->two_factor_secret, $code, $current->two_factor_last_step);
            if ($step !== null) {
                $current->forceFill(['two_factor_last_step' => $step])->save();

                return true;
            }

            $code = Str::lower(trim($code));
            $codes = $current->two_factor_recovery_codes ?? [];
            foreach ($codes as $i => $recovery) {
                if (hash_equals($recovery, $code)) {
                    unset($codes[$i]);
                    $current->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                    Audit::log('auth.recovery_code_used', $current->name.' signed in with a recovery code ('.count($codes).' left)', $current, [], $current);

                    return true;
                }
            }

            return false;
        });
    }
}
