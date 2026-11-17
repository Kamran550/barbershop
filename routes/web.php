<?php

use App\Http\Controllers\Panel\AppointmentController;
use App\Http\Controllers\Panel\Auth\LoginController;
use App\Http\Controllers\Panel\CalendarController;
use App\Http\Controllers\Panel\CustomerController;
use App\Http\Controllers\Panel\CustomerNoteController;
use App\Http\Controllers\Panel\ReportController;
use App\Http\Controllers\Panel\SettingsController;
use App\Http\Controllers\Panel\BarberController;
use App\Http\Controllers\Panel\BarberScheduleController;
use App\Http\Controllers\Panel\BlockedPeriodController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\MyScheduleController;
use App\Http\Controllers\Panel\ProfileController;
use App\Http\Controllers\Panel\ServiceController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ManageBookingController;
use App\Http\Controllers\Public\SlotController;
use Illuminate\Support\Facades\Route;

// ── Public sayt ───────────────────────────────────────────────────────
Route::get('/', HomeController::class)->name('home');

Route::prefix('rezervasiya')->name('booking.')->group(function () {
    Route::get('/', [BookingController::class, 'create'])->name('create');
    Route::post('/', [BookingController::class, 'store'])->middleware('throttle:booking')->name('store');
    Route::get('saatlar', SlotController::class)->middleware('throttle:slots')->name('slots');

    // Token: düz 48 hərf/rəqəm. Başqa formatlı URL route-a heç uyğun gəlmir → 404
    Route::middleware('throttle:booking-manage')->where(['token' => '[A-Za-z0-9]{48}'])->group(function () {
        Route::get('{token}', [ManageBookingController::class, 'show'])->name('show');
        Route::post('{token}/legv', [ManageBookingController::class, 'cancel'])->name('cancel');
        Route::get('{token}/teqvim.ics', [ManageBookingController::class, 'calendar'])->name('calendar');
    });
});

// ── Panel: Manager + Barber ───────────────────────────────────────────
Route::prefix('panel')->name('panel.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])
            ->middleware('throttle:login')
            ->name('login.store');
    });

    // auth: login olmalıdır
    // auth.session: şifrə dəyişəndə həmin hesabın digər cihazlardakı sessiyaları etibarsız olur
    // active: deaktiv edilmiş işçini dərhal çıxarır
    Route::middleware(['auth', 'auth.session', 'active'])->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        // ── Menecer + bərbər. Kimin nəyə toxuna biləcəyini Policy-lər həll edir:
        //    bərbər yalnız ÖZ xidmətlərini, qrafikini, bloklarını, rezervasiyalarını, müştərilərini görür; başqasınınkına → 403.
        Route::get('calendar', CalendarController::class)->name('calendar');

        // Rezervasiyalar. create/edit (əl ilə + başqa vaxta keçirmə) AppointmentPolicy ilə yalnız menecerə açıqdır.
        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
        Route::get('appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit');
        Route::put('appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.status');
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');

        // Müştərilər (CRM) və private qeydlər
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('customers/{customer}/notes', [CustomerNoteController::class, 'store'])->name('customers.notes.store');
        Route::delete('customers/{customer}/notes/{note}', [CustomerNoteController::class, 'destroy'])
            ->scopeBindings() // qeyd həmin müştəriyə aid deyilsə — 404
            ->name('customers.notes.destroy');

        Route::resource('services', ServiceController::class)->except('show');

        Route::get('barbers/{barber}/schedule', [BarberScheduleController::class, 'edit'])->name('barbers.schedule');
        Route::put('barbers/{barber}/schedule', [BarberScheduleController::class, 'update'])->name('barbers.schedule.update');
        Route::post('barbers/{barber}/exceptions', [BarberScheduleController::class, 'storeException'])->name('barbers.exceptions.store');
        // scopeBindings: istisna həmin bərbərə aid deyilsə — 404
        Route::delete('barbers/{barber}/exceptions/{availabilityException}', [BarberScheduleController::class, 'destroyException'])
            ->scopeBindings()
            ->name('barbers.exceptions.destroy');

        Route::get('blocked-periods', [BlockedPeriodController::class, 'index'])->name('blocked-periods.index');
        Route::post('blocked-periods', [BlockedPeriodController::class, 'store'])->name('blocked-periods.store');
        Route::delete('blocked-periods/{blockedPeriod}', [BlockedPeriodController::class, 'destroy'])->name('blocked-periods.destroy');

        // ── Yalnız bərbər: öz qrafiki (qısa yol) və öz profili
        Route::middleware('role:barber')->group(function () {
            Route::get('my-schedule', MyScheduleController::class)->name('my-schedule');
            Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        });

        // ── Yalnız menecer: komandanın idarəsi (bərbər əlavə/silmək, aktivlik, login hesabları)
        Route::middleware('role:manager')->group(function () {
            Route::resource('barbers', BarberController::class)->except('show');
            Route::get('reports', ReportController::class)->name('reports');
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        });
    });
});
