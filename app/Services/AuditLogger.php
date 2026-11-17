<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * "Kim, nə vaxt, nəyi dəyişdi" jurnalı. Service-lər hər mühüm dəyişiklikdən sonra çağırır.
 */
class AuditLogger
{
    /** Heç vaxt jurnala düşməməli sahələr. */
    private const SECRET_FIELDS = ['password', 'remember_token', 'telegram_bot_token', 'token_hash'];

    public function log(string $action, ?Model $subject = null, array $old = [], array $new = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $this->clean($old) ?: null,
            'new_values' => $this->clean($new) ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * save() çağırışından sonra: yalnız həqiqətən dəyişən sahələri köhnə/yeni cütü kimi yazır.
     * Heç nə dəyişməyibsə, jurnal yazılmır.
     */
    public function logChanges(string $action, Model $model): void
    {
        $changed = array_keys(Arr::except($model->getChanges(), ['updated_at']));

        if ($changed === []) {
            return;
        }

        // Yeni dəyərlər cast-larla (məs. decimal:2 → "20.00"), köhnələr DB-dəki formatda — ikisi eyni formatda olsun
        $this->log($action, $model, Arr::only($model->getPrevious(), $changed), $model->only($changed));
    }

    private function clean(array $values): array
    {
        return Arr::except($values, self::SECRET_FIELDS);
    }
}
