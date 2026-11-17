<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\SettingsRequest;
use App\Models\BusinessSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Berberxana parametrləri — yalnız menecer. */
class SettingsController extends Controller
{
    public function edit(): View
    {
        $settings = BusinessSetting::current();

        return view('panel.settings', [
            'settings' => $settings,
            'hasToken' => filled($settings->telegram_bot_token),
        ]);
    }

    public function update(SettingsRequest $request, AuditLogger $audit): RedirectResponse
    {
        $settings = BusinessSetting::current();
        $settings->update($request->settings());

        // Token jurnala düşmür (AuditLogger gizli sahələri çıxarır); yalnız "dəyişdi" faktı qeyd olunur
        $audit->logChanges('settings.updated', $settings);

        return redirect()->route('panel.settings.edit')->with('status', 'Parametrlər yadda saxlanıldı.');
    }
}
