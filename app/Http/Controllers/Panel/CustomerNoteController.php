<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Private qeydlər: menecer və qeydin müəllifi görür. Müştəri heç vaxt görmür. */
class CustomerNoteController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('create', [CustomerNote::class, $customer]);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $customer->notes()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        return back()->with('status', 'Qeyd əlavə olundu.');
    }

    /** scopeBindings: qeyd həmin müştəriyə aid deyilsə — 404. */
    public function destroy(Customer $customer, CustomerNote $note): RedirectResponse
    {
        Gate::authorize('delete', $note);
        $note->delete();

        return back()->with('status', 'Qeyd silindi.');
    }
}
