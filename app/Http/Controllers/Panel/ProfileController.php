<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ProfileRequest;
use App\Services\AuditLogger;
use App\Services\BarberManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Bərbərin öz profili və şifrəsi. Aktivlik, email və hesabın silinməsi menecerdə qalır. */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $barber = $request->user()->barber ?? abort(403, 'Hesabınız bərbər profilinə bağlanmayıb.');

        return view('panel.profile', ['barber' => $barber, 'user' => $request->user()]);
    }

    public function update(ProfileRequest $request, BarberManager $barbers): RedirectResponse
    {
        $barber = $request->user()->barber->load('user');

        $barbers->updateOwnProfile(
            $barber,
            Arr::except($request->validated(), ['photo', 'remove_photo']),
            $request->file('photo'),
            $request->boolean('remove_photo'),
        );

        return redirect()->route('panel.profile.edit')->with('status', 'Profiliniz yeniləndi.');
    }

    public function updatePassword(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'], // köhnə şifrəni bilməyən dəyişə bilməz
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], [
            'current_password' => 'Hazırkı şifrə',
            'password' => 'Yeni şifrə',
        ]);

        $user = $request->user();
        $user->update(['password' => $data['password']]);

        // Digər cihazlardakı açıq sessiyalar bağlansın (şifrə oğurlanıbsa, oğru çıxarılır)
        Auth::logoutOtherDevices($data['password']);
        $audit->log('user.password_changed', $user);

        return redirect()->route('panel.profile.edit')->with('status', 'Şifrəniz dəyişdirildi.');
    }
}
