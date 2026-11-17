<?php

namespace App\Http\Controllers\Panel;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Müştərilər (CRM). Statistika ayrıca saxlanmır — appointments-dən aggregate ilə hesablanır.
 * Bərbər yalnız ona yazılmış müştəriləri və YALNIZ özündəki ziyarətləri görür.
 */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);
        $user = $request->user();

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['recent', 'visits', 'spent', 'name'])],
        ]);
        $sort = $data['sort'] ?? 'recent';

        $customers = $this->withStats(Customer::query()->visibleTo($user), $user)
            ->when($data['q'] ?? null, function (Builder $q, string $term) {
                $digits = PhoneNumber::searchFragment($term);
                $q->where(fn ($w) => $w
                    ->where('name', 'like', '%'.addcslashes($term, '%_').'%')
                    ->when($digits, fn ($w) => $w->orWhere('phone', 'like', '%'.$digits.'%')));
            })
            ->when($sort === 'recent', fn ($q) => $q->orderByDesc('last_visit_at')->orderByDesc('id'))
            ->when($sort === 'visits', fn ($q) => $q->orderByDesc('visits_count'))
            ->when($sort === 'spent', fn ($q) => $q->orderByDesc('total_spent'))
            ->when($sort === 'name', fn ($q) => $q->orderBy('name'))
            ->paginate(25)
            ->withQueryString();

        return view('panel.customers.index', ['customers' => $customers, 'filters' => [...$data, 'sort' => $sort]]);
    }

    public function show(Request $request, Customer $customer): View
    {
        Gate::authorize('view', $customer);
        $user = $request->user();

        $customer = $this->withStats(Customer::query()->whereKey($customer->id), $user)->firstOrFail();

        return view('panel.customers.show', [
            'customer' => $customer,
            'appointments' => $customer->appointments()
                ->visibleTo($user)
                ->with('barber:id,name')
                ->orderByDesc('starts_at')
                ->paginate(20),
            'notes' => $customer->notes()->visibleTo($user)->with('author:id,name')->get(),
            'tz' => \App\Models\BusinessSetting::timezone(),
        ]);
    }

    /** Ad / telefon düzəlişi — yalnız menecer. Telefon normalizə olunur və unikal olmalıdır. */
    public function update(Request $request, Customer $customer, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $customer);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'max:30', function (string $attr, mixed $value, Closure $fail) use ($customer) {
                $normalized = PhoneNumber::normalize($value);
                if ($normalized === null) {
                    $fail('Telefon nömrəsi düzgün deyil.');
                } elseif (Customer::where('phone', $normalized)->whereKeyNot($customer->id)->exists()) {
                    $fail('Bu nömrə ilə başqa müştəri artıq var.');
                }
            }],
        ]);

        $customer->update(['name' => $data['name'], 'phone' => PhoneNumber::normalize($data['phone'])]);
        $audit->logChanges('customer.updated', $customer);

        return back()->with('status', 'Müştəri məlumatları yeniləndi.');
    }

    /**
     * Ziyarət sayı, xərclənən məbləğ və son ziyarət — yalnız TAMAMLANMIŞ rezervasiyalar.
     * Bərbər üçün yalnız öz ziyarətləri sayılır (başqa ustanın gəliri ona görünmür).
     */
    private function withStats(Builder $query, User $user): Builder
    {
        $scope = function ($q) use ($user) {
            $q->where('status', AppointmentStatus::Completed);
            if (! $user->isManager()) {
                $q->where('barber_id', $user->barberId());
            }
        };

        return $query
            ->withCount(['appointments as visits_count' => $scope])
            ->withSum(['appointments as total_spent' => $scope], 'service_price')
            ->withMax(['appointments as last_visit_at' => $scope], 'starts_at');
    }
}
