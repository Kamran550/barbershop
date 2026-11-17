<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route səviyyəsində "qapı": route-a yalnız göstərilən rollar daxil ola bilər.
 *   ->middleware('role:manager')
 *   ->middleware('role:manager,barber')
 *
 * Konkret obyektə (bu rezervasiya, bu müştəri) icazə Policy-lərdə yoxlanır.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
