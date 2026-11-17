<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ServiceRequest;
use App\Models\Barber;
use App\Models\Service;
use App\Services\ServiceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Menecer və bərbər eyni səhifələrdən istifadə edir; fərq policy və scope-dadır:
 *  - menecer bütün xidmətləri görür, yenisini istənilən bərbərin adına yaradır
 *  - bərbər yalnız öz xidmətlərini görür və idarə edir
 */
class ServiceController extends Controller
{
    public function __construct(private readonly ServiceCatalog $catalog) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Service::class);
        $user = $request->user();

        return view('panel.services.index', [
            'services' => Service::query()
                ->visibleTo($user)
                ->with('barber:id,name')
                ->orderBy('barber_id')
                ->ordered()
                ->get(),
            'isManager' => $user->isManager(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Service::class);

        return view('panel.services.create', [
            'service' => new Service(['is_active' => true, 'sort_order' => 0]),
            'barbers' => $request->user()->isManager() ? Barber::query()->ordered()->get(['id', 'name']) : collect(),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $user = $request->user();
        $owner = $user->isManager()
            ? Barber::findOrFail($request->validated('barber_id'))
            : $user->barber; // bərbər həmişə özünə yaradır — formadan barber_id qəbul edilmir

        $service = $this->catalog->create($owner, Arr::except($request->validated(), ['barber_id']), $request->file('image'));

        return redirect()->route('panel.services.index')->with('status', "«{$service->name}» əlavə olundu.");
    }

    public function edit(Service $service): View
    {
        Gate::authorize('update', $service);
        $service->load('barber:id,name');

        return view('panel.services.edit', ['service' => $service, 'barbers' => collect()]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->load('barber:id,name');
        $this->catalog->update($service, $request->validated(), $request->file('image'), $request->boolean('remove_image'));

        return redirect()->route('panel.services.index')->with('status', "«{$service->name}» yeniləndi.");
    }

    public function destroy(Service $service): RedirectResponse
    {
        Gate::authorize('delete', $service);
        $this->catalog->delete($service);

        return redirect()->route('panel.services.index')->with('status', "«{$service->name}» silindi.");
    }
}
