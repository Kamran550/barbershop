<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\BarberRequest;
use App\Models\Barber;
use App\Services\BarberManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BarberController extends Controller
{
    public function __construct(private readonly BarberManager $barbers) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Barber::class);

        return view('panel.barbers.index', [
            'barbers' => Barber::query()->with('user:id,email,is_active')->withCount('services')->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Barber::class);

        return view('panel.barbers.create', [
            'barber' => new Barber(['is_active' => true, 'sort_order' => 0]),
        ]);
    }

    public function store(BarberRequest $request): RedirectResponse
    {
        $barber = $this->barbers->create($this->payload($request), $request->file('photo'));

        return redirect()->route('panel.barbers.schedule', $barber)
            ->with('status', "{$barber->name} əlavə olundu. İş qrafikini və xidmətlərini ya burada siz, ya da bərbər öz hesabından təyin edə bilər.");
    }

    public function edit(Barber $barber): View
    {
        Gate::authorize('update', $barber);
        $barber->load('user', 'services');

        return view('panel.barbers.edit', ['barber' => $barber]);
    }

    public function update(BarberRequest $request, Barber $barber): RedirectResponse
    {
        $barber->load('user');
        $this->barbers->update($barber, $this->payload($request), $request->file('photo'), $request->boolean('remove_photo'));

        return redirect()->route('panel.barbers.index')->with('status', "{$barber->name} yeniləndi.");
    }

    public function destroy(Barber $barber): RedirectResponse
    {
        Gate::authorize('delete', $barber);
        $barber->load('user');
        $this->barbers->delete($barber);

        return redirect()->route('panel.barbers.index')->with('status', "{$barber->name} silindi.");
    }

    private function payload(BarberRequest $request): array
    {
        return Arr::except($request->validated(), ['photo', 'remove_photo']);
    }
}
