<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\BlockedPeriodRequest;
use App\Models\Barber;
use App\Models\BlockedPeriod;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Birdəfəlik bloklanmış vaxtlar.
 *  - Menecer: istənilən bərbər və ya bütün berberxana üçün
 *  - Bərbər: yalnız özü üçün (formada bərbər seçimi yoxdur); berberxana bloklarını görür, silə bilməz
 */
class BlockedPeriodController extends Controller
{
    public function __construct(private readonly ScheduleService $schedule) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', BlockedPeriod::class);
        $user = $request->user();

        return view('panel.blocked-periods.index', [
            'blocks' => BlockedPeriod::query()
                ->visibleTo($user)
                ->with('barber:id,name')
                ->where('ends_at', '>', now('UTC'))
                ->orderBy('starts_at')
                ->get(),
            'barbers' => $user->isManager() ? Barber::query()->active()->ordered()->get(['id', 'name']) : collect(),
            'isManager' => $user->isManager(),
        ]);
    }

    public function store(BlockedPeriodRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Bərbər üçün hədəf həmişə özüdür — formadan gələn barber_id nəzərə alınmır
        $barber = $user->isManager()
            ? ($data['barber_id'] ? Barber::findOrFail($data['barber_id']) : null)
            : $user->barber;

        Gate::authorize('create', [BlockedPeriod::class, $barber]);

        [, $conflicts] = $this->schedule->createBlock($barber, $data['starts_at'], $data['ends_at'], $data['reason'] ?? null);

        $redirect = redirect()->route('panel.blocked-periods.index')->with('status', 'Vaxt bloklandı.');

        return $conflicts > 0
            ? $redirect->with('warning', "Diqqət: bu intervalda {$conflicts} aktiv rezervasiya var. Blok yeni rezervasiyaların qarşısını alır, mövcudları isə ayrıca həll edin.")
            : $redirect;
    }

    public function destroy(BlockedPeriod $blockedPeriod): RedirectResponse
    {
        Gate::authorize('delete', $blockedPeriod);
        $this->schedule->deleteBlock($blockedPeriod);

        return redirect()->route('panel.blocked-periods.index')->with('status', 'Blok silindi.');
    }
}
