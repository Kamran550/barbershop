<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BusinessSetting;
use App\Services\Booking\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Müştərinin öz rezervasiyası: login yoxdur, giriş açarı — linkdəki 48 simvolluq token.
 * Token yalnız müştəridədir; DB-də onun hash-i var. Tapılmayan token → 404 (mövcudluq ifşa olunmur).
 */
class ManageBookingController extends Controller
{
    public function __construct(private readonly BookingService $booking) {}

    public function show(string $token): Response
    {
        $appointment = $this->resolve($token);
        $settings = BusinessSetting::current();

        $view = view('public.booking-show', [
            'appointment' => $appointment,
            'settings' => $settings,
            'token' => $token,
            'tz' => $settings->timezone,
            'canCancel' => $appointment->status->occupiesSlot()
                && now()->lessThan($appointment->starts_at->subMinutes($settings->cancel_deadline_minutes)),
        ]);

        return response($view)
            // Token URL-dədir: başqa sayta keçid edəndə Referer başlığı ilə sızmasın, keşlənməsin
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function cancel(string $token): RedirectResponse
    {
        $this->booking->cancel($this->resolve($token)); // actor = null → müştəri, deadline qaydası tətbiq olunur

        return redirect()->route('booking.show', $token)->with('status', 'Rezervasiyanız ləğv edildi.');
    }

    /** Təqvimə əlavə et: standart .ics faylı (Google, Apple, Outlook açır). */
    public function calendar(string $token): Response
    {
        $a = $this->resolve($token);
        $settings = BusinessSetting::current();
        $esc = fn (string $v) => addcslashes($v, ",;\\");

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$esc($settings->name).'//Booking//AZ',
            'BEGIN:VEVENT',
            'UID:appointment-'.$a->id.'@'.request()->getHost(),
            'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z'),
            'DTSTART:'.$a->starts_at->format('Ymd\THis\Z'),
            'DTEND:'.$a->ends_at->format('Ymd\THis\Z'),
            'SUMMARY:'.$esc($a->service_name.' — '.$settings->name),
            'DESCRIPTION:'.$esc('Bərbər: '.$a->barber->name),
            'LOCATION:'.$esc((string) $settings->address),
            'END:VEVENT',
            'END:VCALENDAR',
        ])."\r\n";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="rezervasiya.ics"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function resolve(string $token): Appointment
    {
        $appointment = $this->booking->findByToken($token) ?? abort(404);

        return $appointment->load(['barber:id,name,title,photo_path', 'customer:id,name,phone']);
    }
}
