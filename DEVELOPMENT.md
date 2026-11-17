# DEVELOPMENT.md — Barbershop Booking & Management System

Bu fayl layihədə görülən işləri və verilən arxitektura qərarlarını mərhələ-mərhələ qeyd edir.

## Qısa xülasə

- Bir berberxana üçün fərdi sistemdir: SaaS və ya multi-tenant deyil.
- Bir Laravel 12 monolitidir: public sayt, panel (Manager + Barber) və lazım olan JSON endpoint-lər eyni tətbiqdə yerləşir.
- Docker **yalnız MySQL** üçün istifadə olunur. PHP, Composer, Node və Vite host kompüterdə işləyir.

## Lokal mühit

| Alət | Versiya |
|---|---|
| PHP | 8.4.27 (NTS x64, `C:\php-8.4`): bcmath, curl, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, sodium, zip |
| Composer | 2.8 |
| Node / npm | 20.19 / 11.2 |
| Laravel | 12.x |
| MySQL | 8.4 LTS (Docker) |

### İlk quraşdırma

```bash
composer install
npm install
cp .env.example .env        # DB_PASSWORD və DB_ROOT_PASSWORD-u dəyiş
php artisan key:generate
docker compose up -d --wait # MySQL
php artisan migrate
```

### Gündəlik iş

```bash
docker compose up -d --wait   # MySQL
php artisan serve             # http://127.0.0.1:8000
npm run dev                   # Vite (HMR)
```

İstəyə bağlı olaraq phpMyAdmin: `docker compose --profile tools up -d` → http://127.0.0.1:8081

## Qərarlar

### PHASE 1 — Arxitektura
- **MVC + Service Layer**: business logic `app/Services`-də yerləşir, controller-lər nazik saxlanılır. Public sayt və panel eyni `BookingService`-dən istifadə edir.
- **API**: ayrıca `api.php` və Sanctum istifadə olunmur. Boş saatlar üçün `web.php`-da session və CSRF ilə qorunan kiçik bir JSON endpoint olacaq.
- **Authorization**: `users.role` enum (`manager` / `barber`), `role` middleware, Policy-lər və siyahılar üçün `visibleTo()` scope. spatie/permission istifadə olunmur, çünki iki sabit rol üçün lazım deyil.
- **Double booking**: transaction daxilində bərbərin sətri `lockForUpdate()` ilə kilidlənir, sonra overlap yenidən yoxlanılır və insert edilir. MySQL-də exclusion constraint olmadığı üçün unique index bu problemi həll etmir.
- **Zaman**: DB-də UTC saxlanılır. İş qrafiki "divar saatı" kimi saxlanılır və `business_settings.timezone` (Asia/Baku) ilə çevrilir.
- **Pul**: `DECIMAL(10,2)`. Rezervasiya anında xidmətin adı, qiyməti və müddəti appointment-də snapshot kimi saxlanılır.
- **Booking token**: müştəriyə 64 simvolluq təsadüfi token verilir, DB-də onun yalnız SHA-256 hash-i saxlanılır.

### PHASE 2 — Lokal mühit
- `composer create-project laravel/laravel:^12.0`: `laravel new` installer ən son major versiyanı gətirdiyi üçün versiya açıq şəkildə göstərildi. `composer.json`-da `"php": "^8.4"`.
- **docker-compose.yml**:
  - `mysql:8.4`, port yalnız `127.0.0.1`-ə publish olunur (şəbəkəyə açıq deyil).
  - Data `mysql_data` named volume-da saxlanılır.
  - utf8mb4 istifadə olunur, healthcheck var.
  - DB dəyişənləri Laravel-in `.env` faylından oxunur: tək mənbə.
- `docker/mysql/init/01-test-database.sql`: container ilk dəfə yarananda `barbershop_test` database-ini yaradır. `phpunit.xml` testləri bu database-ə yönləndirir, SQLite istifadə olunmur, çünki lock davranışı fərqlidir.
- **Frontend**:
  - Laravel-in default Tailwind v4 (`@tailwindcss/vite`) qurğusu saxlanıldı.
  - Alpine.js və Lucide əlavə edildi. Axios və `bootstrap.js` silindi, çünki `fetch` kifayətdir.
  - Lucide-dən yalnız istifadə olunan ikonlar import edilir. Bütün ikonlar import ediləndə JS bundle 870 KB idi, indi 63 KB-dır.
- `.env`-də DB parolları təsadüfi yaradılıb. `.env` Git-ə düşmür, `.env.example`-də yalnız placeholder dəyərlər var.
- ⚠️ `docker-compose.yml`-də image sonradan `mysql:latest` edilib və hazırda **MySQL 9.1** işləyir. Tələb isə 8.4 LTS-dir. Qərar gözləyir (aşağıdakı "Açıq məsələlər" bölməsinə bax).

### PHASE 3 — Database arxitekturası

**Komandalar** (FK sırası vacibdir: valideyn cədvəl uşaqdan əvvəl yaradılmalıdır):
```bash
php artisan make:model Barber -mf
php artisan make:model Service -mf
php artisan make:migration create_barber_service_table   # PHASE 7.1-də silindi
php artisan make:model WorkingHour -m
php artisan make:model BreakPeriod            # "Break" PHP reserved sözdür
php artisan make:migration create_breaks_table
php artisan make:model AvailabilityException -m
php artisan make:model BlockedPeriod -m
php artisan make:model Customer -mf
php artisan make:model Appointment -mf
php artisan make:model CustomerNote -m
php artisan make:model NotificationLog -m
php artisan make:model BusinessSetting -m
php artisan make:model AuditLog -m
php artisan make:enum UserRole --string       # qeyd: "Enums/UserRole" yazsan App\Enums\Enums yaranır
php artisan make:seeder BusinessSettingSeeder
php artisan make:seeder DemoSeeder
php artisan migrate:fresh --seed
```

**Cədvəllər**: users (+role, is_active), barbers, services (+barber_id, PHASE 7.1), working_hours, breaks, availability_exceptions, blocked_periods, customers, appointments, customer_notes, notification_logs, business_settings, audit_logs.

**Qərarlar**:
- **Data integrity DB səviyyəsində qorunur**: FK, unique və `CHECK` constraint-lər (`start < end`, `weekday 1–7`, `price >= 0`, status və source üçün icazəli dəyərlər). Laravel Blueprint CHECK dəstəkləmədiyi üçün `DB::statement` istifadə olunur. Tətbiqdə səhv olsa belə, DB yanlış datanı rədd edir.
- **FK siyasəti**:
  - `appointments → barbers/customers`: `restrict`. Tarixçə və gəlir itməməlidir, ona görə bərbər silinmir, deaktiv edilir.
  - `appointments → services`: `nullOnDelete`, çünki snapshot sütunları qalır.
  - `barbers → users`: `nullOnDelete`. Login hesabı silinəndə bərbərin profili qalır.
  - Qrafik cədvəlləri (working_hours, breaks və s.): `cascade`.
- **Snapshot**: `service_name`, `service_price`, `service_duration` appointment-də saxlanılır.
- **Zaman**: `DATETIME` və UTC (TIMESTAMP yox, çünki MySQL session timezone çevirməsi və 2038 limiti var). İş qrafiki `TIME` tipindədir, divar saatı kimi saxlanılır.
- **Customers**: statistika (ziyarət sayı, xərc, son ziyarət) ayrıca saxlanılmır, `appointments`-dən hesablanır.
- **İndekslər**:
  - `appointments(barber_id, starts_at)`: availability və calendar.
  - `(status, starts_at)`: dashboard.
  - `(customer_id, starts_at)`: müştəri tarixçəsi.
  - `blocked_periods(barber_id, starts_at)`.
- **business_settings**: tək sətirdir, `BusinessSetting::current()` ilə oxunur (`once()` sayəsində bir request-də bir sorğu). `telegram_bot_token` `encrypted` cast ilə şifrələnir.
- **Enum-lar**:
  - `UserRole`.
  - `AppointmentStatus`: `canTransitionTo()` status maşınıdır, `active()` isə vaxtı tutan statusları qaytarır.
  - `AppointmentSource`.
  - Label-lər Azərbaycan dilindədir.
- **Scope-lar**: `Appointment::occupying()` və `overlapping($from, $to)`. Overlap şərti: `a.start < to AND a.end > from`. Bitişik intervallar kəsişmə sayılmır.
- **Seeder-lər**:
  - `BusinessSettingSeeder` hər mühitdə işləyir.
  - `DemoSeeder` yalnız `local/testing` mühitində işləyir. Demo login-lər bu faylın başında qeyd olunub.
- **Testlər**: PHPUnit (Laravel default) istifadə olunur. PHASE 1-də Pest planlaşdırılmışdı, amma əlavə dependency-yə ehtiyac görmədim. Testlər `barbershop_test` MySQL database-ində işləyir.
  - `tests/Feature/DatabaseSchemaTest.php`: 16 test.
  - `tests/Unit/AppointmentStatusTest.php`: 3 test.

### PHASE 4 — Authentication & Authorization

**Faylların artisan ekvivalentləri:**
```bash
php artisan make:request Panel/LoginRequest
php artisan make:controller Panel/Auth/LoginController
php artisan make:controller Panel/DashboardController --invokable
php artisan make:middleware EnsureUserHasRole
php artisan make:middleware EnsureUserIsActive
php artisan make:policy AppointmentPolicy --model=Appointment
php artisan make:policy CustomerPolicy --model=Customer
php artisan make:policy CustomerNotePolicy --model=CustomerNote
php artisan make:policy BarberPolicy --model=Barber
```

**Authentication:**
- Login səhifəsi `/panel/login`-dir. Breeze kimi paket istifadə olunmur, çünki bizə yalnız login və logout lazımdır, qeydiyyat və şifrə bərpası yoxdur.
- **Brute-force qoruması:**
  - Email+IP üzrə 5 uğursuz cəhddən sonra 60 saniyə blok (`LoginRequest`).
  - IP üzrə dəqiqədə 20 cəhd (`throttle:login`). Bu, çox hesabı bir-bir yoxlamağa ("password spraying") qarşıdır.
- **Deaktiv işçi:**
  - Login zamanı `is_active = true` şərti tələb olunur. Xəta mesajı yanlış şifrə ilə eynidir, beləliklə hesabın mövcud olub-olmadığı bilinmir.
  - Session açıq olarkən deaktiv edilərsə, `active` middleware onu növbəti request-də sistemdən çıxarır.
- **Session:** login-dən sonra `regenerate()` (session fixation-a qarşı). Logout-da `invalidate()` və `regenerateToken()`.
- `Model::shouldBeStrict()` (production xaric): lazy loading (N+1) və icazəsiz mass assignment xəta verir.

**Authorization (üç qat):**
1. **Middleware**: `auth`, `active`, `role:manager` (route qrupları üçün "qapı").
2. **Policy** (tək obyekt, IDOR qoruması): Laravel naming convention ilə avtomatik tapılır.
   - `AppointmentPolicy`:
     - view və updateStatus: menecer və ya rezervasiyanın öz bərbəri.
     - create və reschedule: yalnız menecer.
     - `delete` metodu yoxdur, deməli heç kim silə bilməz.
   - `CustomerPolicy`: view üçün menecer və ya həmin müştərinin bu bərbərdə ən azı bir rezervasiyası olmalıdır. Update yalnız menecerdir.
   - `CustomerNotePolicy`: qeydi menecer və müəllif görür. Həmkar bərbər eyni müştəriyə xidmət etsə belə, qeydi görmür.
   - `BarberPolicy`: ~~bərbər öz profilini və qrafikini görür, amma dəyişə bilmir~~. PHASE 7.1-dən sonra bərbər öz qrafikini və profilini özü idarə edir.
3. **Scope** (siyahılar): `Appointment::visibleTo($user)`, `Customer::visibleTo($user)`, `CustomerNote::visibleTo($user)`. Profilə bağlanmamış bərbər heç nə görmür (`1 = 0`).

**UI:**
- `lang/az`: auth, validation və pagination mesajları Azərbaycan dilindədir.
- Dizayn token-ləri `resources/css/app.css`-dədir: ink / paper / brass palitrası, Inter şrifti.
- Panel layout: sidebar və mobil menyu (Alpine).
- Mobil sidebar `data-[open=true]:translate-x-0` ilə açılır. Bu attribute selektorun specificity-si yüksəkdir, ona görə `-translate-x-full`-u üstələyir.

**Testlər:** cəmi 49 test (150 assertion):
- `tests/Feature/Auth/LoginTest.php`: 12 test.
- `RoleMiddlewareTest.php`: 4 test.
- `AuthorizationTest.php`: 12 test.

Dashboard testi `travelTo()` ilə vaxtı dondurur, ona görə gecə yarısı işə salınsa belə nəticə dəyişmir.

### PHASE 5 — Core Business Logic (menecerin idarəetmə hissəsi)

**Service qatı** (`app/Services`), business logic yalnız buradadır, controller-lər nazikdir:
- `ServiceCatalog`: xidmət yaratma, redaktə, silmə, şəkil, slug, audit. Rezervasiyası olan xidmət silinmir, deaktiv edilir.
- `BarberManager`: profil, login hesabı, şəkil, aktiv/deaktiv. Xidmət sync-i PHASE 7.1-də çıxarıldı, çünki xidmətlər artıq bərbərə məxsusdur.
  - Hamısı bir transaction-da yazılır. DB yazılmazsa, yüklənmiş şəkil silinir və yetim fayl qalmır.
  - Bərbər deaktiv edildikdə hesabı da deaktiv olur və `active` middleware onu dərhal sistemdən çıxarır.
- `ScheduleService`: həftəlik saatlar və fasilələr (köhnəsi silinib yenisi yazılır, bir transaction-da), tarix istisnaları (`updateOrCreate`), bloklar (local vaxt UTC-yə çevrilir).
  - **Qrafik dəyişəndə mövcud rezervasiyalar avtomatik ləğv edilmir.** Menecerə neçə rezervasiyanın təsirləndiyi barədə xəbərdarlıq göstərilir.
- `AuditLogger`: `log()` və `logChanges()` (yalnız həqiqətən dəyişən sahələr, cast-lı formatda). Parol və token heç vaxt yazılmır.
- `ImageStorage`: təsadüfi fayl adı, uzantı faylın məzmunundan təyin olunur, köhnə fayl silinir.

**BusinessRuleException** biznes qaydası pozulanda atılır. `bootstrap/app.php`-də mərkəzləşdirilmiş şəkildə tutulur: istifadəçi əvvəlki səhifəyə qaytarılır və `error` flash göstərilir (JSON request-də 422). Bunun sayəsində controller-lərdə try/catch yoxdur.

**Validation (Form Request):**
- `ServiceRequest`: qiymət `decimal:0,2`, müddət 5–480 və 5-ə bölünən.
- `BarberRequest`:
  - Yeni hesab üçün şifrə mütləqdir (min 8, hərf+rəqəm), email unikal olmalıdır.
  - Hesabı olan bərbərin email-i boşaldıla bilməz.
- `WeeklyScheduleRequest`: format yoxlaması `rules()`-da, məntiq (start < end, fasilə iş saatına sığır, fasilələr kəsişmir) `after()`-də.
- `AvailabilityExceptionRequest`: keçmiş tarix qadağandır ("bu gün" berberxananın timezone-unda hesablanır).
- `BlockedPeriodRequest`: tamamilə keçmişdə qalan blok qadağandır.
- **Şəkillər:** `image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:max_width=4000,max_height=4000`. SVG rədd edilir (XSS), `.php` rədd edilir, 2 MB-dan böyük fayl rədd edilir.

**Route-lar:**
- ~~`role:manager` qrupu: services, barbers, schedule, exceptions, blocked-periods~~ → PHASE 7.1-də dəyişdi (aşağıda).
- ~~`role:barber`: `my-schedule` (yalnız baxış)~~ → PHASE 7.1-də dəyişdi.
- **IDOR:** `barbers/{barber}/exceptions/{availabilityException}` → `scopeBindings()`. Başqa bərbərin istisnasının ID-si 404 verir. Laravel child-ı parent-in `availabilityExceptions()` relation-ı vasitəsilə axtarır, ona görə parametr adı relation adına uyğun olmalıdır.

**UI:**
- Komponentlər: `x-panel.page-header`, `x-panel.flash`, `x-panel.status-badge`.
- Həftəlik qrafik formu Alpine ilə işləyir: gün switch-i, fasilə əlavə et/sil. Validation xətasından sonra `old()` dəyərləri və günə aid xətalar formaya qaytarılır.
- Menyu rola görə qurulur.
- Lucide-dən yalnız istifadə olunan ikonlar import olunur.
- Silmə təsdiqi `confirm({{ Js::from(...) }})` ilə edilir, çünki ad dırnaq işarəsi ehtiva etsə JS sınmamalıdır.
- `storage:link` yaradılıb.

**Testlər:** cəmi 90 test (298 assertion):
- `ServiceManagementTest`: 10 test.
- `BarberManagementTest`: 10 test.
- `ScheduleManagementTest`: 21 test.

Brauzerdə yoxlanılıb: qrafik formunun saxlanması və validation xətasının göstərilməsi.

⚠️ **Windows qeydi:** PowerShell 5.1-də `Get-Content`/`Set-Content` UTF-8 faylları ANSI (Windows-1252) kimi oxuyur. Bu səbəbdən Azərbaycan hərfləri pozulur ("ə" → "É™"). Blade, PHP və `.env` fayllarını PowerShell ilə redaktə etməyin, ya da `-Encoding utf8` istifadə edin.

### PHASE 6 — Booking & Availability Engine

**Fayllar:** `app/Services/Booking/` (`TimeRange`, `AvailabilityService`, `BookingService`, `BookingResult`), `app/Support/PhoneNumber.php`, `app/Exceptions/SlotUnavailableException.php`.

**TimeRange**: yarımaçıq interval `[start, end)` deməkdir, bitişik intervallar kəsişmir. `subtract()` və `subtractAll()` engine-in bütün riyaziyyatıdır. DB-dən asılı deyil və unit testlə yoxlanır.

**AvailabilityService::slots()** (interval çıxma üsulu):
1. Keçmiş tarix və ya üfüqdən (30 gün) uzaq tarix → boş. Səhv tarix formatı (URL-dən) da sadəcə "boş" qaytarır.
2. İş pəncərəsi: tarix istisnası varsa o, yoxsa həftəlik qrafik götürülür.
3. Fasilələr çıxılır (xüsusi saatlı istisna günündə də).
4. Bloklar çıxılır: bərbərin özününkülər və bütün berberxana üzrə olanlar (`barber_id NULL`).
5. Aktiv rezervasiyalar çıxılır (pending+confirmed). Hər biri **hər iki tərəfdən** buffer qədər genişləndirilir. İş gününün sonunda buffer tələb olunmur.
6. Namizədlər iş başlanğıcından `slot_interval` addımı ilə yoxlanır. `[t, t+müddət)` tam olaraq boş intervala sığmalıdır.
7. `now + min_notice`-dən tez olan namizədlər atılır.

Nəticə UTC-dədir. Bütün müqayisələr mütləq zaman anları ilə aparılır: iş saatları Bakı vaxtında qurulur, DB-dəki UTC dəyərlərlə müqayisə olunur.

- `isBookableSlot()`: onlayn rezervasiya üçün. Vaxt `slots()` siyahısında olmalıdır (grid, notice və üfüq tələb olunur).
- `isFreeForManualBooking()`: menecer üçün. Grid, notice və üfüq tələb olunmur, amma keçmiş vaxt, iş saatından kənar, fasilə, blok və digər rezervasiyalar yenə də qadağandır.
- `groupServices()`: "fərq etməz" seçimi üçün (PHASE 7.1-də `eligibleBarbers()`-i əvəz etdi). Eyni adlı xidmətlər qrup kimi götürülür, həmin gün ən az yüklü bərbər əvvəl gəlir.

**BookingService::book()**:
- Telefon normalizə olunur (`+994XXXXXXXXX`), sonra hər namizəd bərbər üçün ayrıca transaction açılır:
  1. `SELECT … FROM barbers WHERE id = ? FOR UPDATE`. Bu, **transaction-ın ilk sorğusudur**.
  2. Lock altında availability yenidən yoxlanır.
  3. Eyni telefon qaydaları yoxlanır: vaxtı üst-üstə düşən aktiv rezervasiya qadağandır (həm onlayn, həm əl ilə). Onlayn rezervasiyada limit `max_active_bookings_per_phone`-dur.
  4. Müştəri `createOrFirst` ilə tapılır və ya yaradılır (unique phone sayəsində yarış vəziyyətində də dublikat yaranmır). Ad dəyişibsə yenilənir.
  5. Rezervasiya snapshot ilə yaradılır. Token 48 simvoldur, DB-də yalnız SHA-256 hash-i saxlanılır.
- **Status:** onlayn rezervasiyada `auto_confirm`-a görə confirmed və ya pending olur. Əl ilə yaradılan həmişə confirmed-dir.
- **Niyə lock ilk sorğu olmalıdır?** InnoDB REPEATABLE READ rejimində transaction-ın oxuduğu "snapshot" **ilk adi SELECT**-də götürülür. Əgər lock-dan əvvəl adi SELECT olsa, növbədə gözləyən ikinci request birincinin commit etdiyi rezervasiyanı görməz. Ona görə `BusinessSetting::current()` və digər hazırlıq oxunuşları transaction-dan **əvvəl** edilir.
- Əlavə metodlar:
  - `cancel()`: müştəri üçün deadline var (2 saat), işçi istənilən vaxt ləğv edə bilər.
  - `changeStatus()`: status maşını ilə işləyir. Completed və no_show yalnız rezervasiyanın vaxtı çatandan sonra mümkündür.
  - `reschedule()`: hədəf bərbər lock olunur, rezervasiyanın öz köhnə vaxtı yoxlamada nəzərə alınmır. Snapshot dəyişmir.
  - `findByToken()`: token ilə rezervasiyanı tapır.

**Testlər:** cəmi 149 test (435 assertion):
- `tests/Unit/TimeRangeTest`: 6 test.
- `tests/Unit/PhoneNumberTest`: 3 test.
- `tests/Feature/Booking/AvailabilityServiceTest`: 18 test.
- `tests/Feature/Booking/BookingServiceTest`: 31 test. Master prompt-dakı bütün ssenariləri əhatə edir.
- `tests/Feature/Booking/ConcurrentBookingTest`: **real paralellik testi**.
  - 8 ayrı PHP prosesi (`tests/Concurrency/book_slot.php`) eyni anda eyni slotu tutmağa çalışır. Nəticə: 1 OK və 7 TAKEN.
  - **Eksperiment:** `lockForUpdate()` sətri söndürüləndə nəticə `["OK" × 8]` oldu, yəni 8 double booking. Bu, testin race condition-u həqiqətən tutduğunu sübut edir.
  - Test `DatabaseTruncation` istifadə edir, çünki data commit olunmalıdır ki, digər proseslər onu görsün. `tearDown`-da cədvəllər təmizlənir.

### PHASE 7 — Public Website

**Route-lar** (bütün URL-lər Azərbaycan dilindədir):

| Route | Nə edir | Limit |
|---|---|---|
| `GET /` | Ana səhifə | — |
| `GET /rezervasiya` | Wizard (`?service=slug&barber=slug` ilə əvvəlcədən seçim) | — |
| `POST /rezervasiya` | Rezervasiya | IP üzrə 5/dəq, 20/saat |
| `GET /rezervasiya/saatlar` | Boş saatlar (JSON). Layihədəki yeganə JSON endpoint | 60/dəq |
| `GET /rezervasiya/{token}` | Bilet səhifəsi | 30/dəq |
| `POST /rezervasiya/{token}/legv` | Müştərinin ləğvi | 30/dəq |
| `GET /rezervasiya/{token}/teqvim.ics` | Təqvimə əlavə et | 30/dəq |

`{token}` route-u yalnız `[A-Za-z0-9]{48}` formatını qəbul edir, başqa format 404 verir.

**Wizard** (`resources/js/booking-wizard.js`, Alpine komponenti):
- Addımlar (PHASE 7.1-dən sonra): **usta** ("Fərq etməz" seçimi də var) → **xidmət** (ustanın öz xidmətləri və ya qiymət aralığı olan qruplar) → tarix zolağı və saatlar → ad və telefon.
- Statik data (xidmətlər, bərbərlər, iş günləri) bir dəfə JSON config kimi verilir. Şəbəkə sorğusu yalnız tarix seçiləndə göndərilir.
- Tarix zolağında bərbərin işləmədiyi günlər sönük göstərilir (həftəlik qrafik və istisnalara görə). Dəqiq boş saatları isə server deyir.
- Wizard tarixi özü seçəndə həmin gün boşdursa (məsələn axşamdır), avtomatik növbəti iş gününə keçir.
- **Race condition qoruması:** hər sorğunun öz `AbortController`-i var və vəziyyəti yalnız hələ aktual olan sorğu yeniləyir. Brauzer testində ləğv edilmiş sorğunun `finally` blokunun yeni sorğunun vəziyyətini pozduğu aşkarlandı və bu düzəldildi.
- **Son göndərmə adi HTML form POST-dur** (CSRF və server validation ilə).
- Server xətasından sonra wizard düzgün addıma qayıdır: vaxt tutulubsa saat seçiminə (3), validation xətasında 4-cü addıma. Daxil edilən dəyərlər `old()` ilə bərpa olunur.
- **Honeypot:** gizli `website` sahəsi var. Bot onu doldursa, `prohibited` qaydası rədd edir.

**Bilet səhifəsi:**
- Telefon maskalanır (`+994 50 *** ** 67`).
- **Başlıqlar:**
  - `Referrer-Policy: no-referrer`: token URL-dədir, başqa sayta keçid edəndə Referer başlığı ilə sızmamalıdır.
  - `Cache-Control: no-store`.
  - `X-Robots-Tag: noindex`.
- Ləğv düyməsi yalnız deadline-dan (2 saat) əvvəl göstərilir. Deadline keçibsə, berberxananın telefonu göstərilir.
- **.ics faylı:** Google, Apple və Outlook təqvimləri ilə uyğundur.

**Dizayn:**
- **Şriftlər:** Fraunces (başlıqlar, Azərbaycan hərfləri yoxlanılıb) və Inter. Bunny Fonts-dan yüklənir.
- **Palitra:** ink / paper / brass.
- **Motivlər:** hero-da animasiyalı bərbər dirəyi (`prefers-reduced-motion` nəzərə alınır). Xidmətlər köhnə bərbərxana qiymət lövhəsi üslubundadır (nöqtəli xəttlə). Şəkli olmayan bərbər üçün monoqram kartı göstərilir.
- **Wizard:** tünd fon, mis rəngli seçim; xülasə "bilet" kimi.

Mobil versiya 375px-də yoxlanılıb (üfüqi overflow yoxdur). Desktop versiya da yoxlanılıb.

**Testlər:** cəmi 169 test (593 assertion):
- `tests/Feature/Public/PublicSiteTest`: 7 test.
- `PublicBookingFlowTest`: 16 test.
  - Tam axın, tutulmuş vaxt, honeypot, rate limit.
  - CSRF (419). Laravel testlərdə CSRF-i söndürür, ona görə test bunu müvəqqəti `env = local` ilə yoxlayır.
  - XSS escape, private qeydlərin sızmaması, .ics, ləğv, deadline.
- Laravel-in default `ExampleTest` faylları silindi.
- `DemoSeeder` demo ünvan və telefon əlavə edir (yalnız local mühitdə).

### PHASE 7.1 — Bərbər öz işini özü idarə edir (sifarişçinin tələbi)

**Tələb:** menecer əvvəlki kimi hər şeyi idarə edə bilir. Bundan əlavə, hər bərbər öz xidmətlərini (öz qiyməti ilə), iş saatlarını, fasilələrini, istisnalarını və bloklarını özü idarə edir.

**DB dəyişikliyi:**
- `services.barber_id` əlavə olundu (FK, `cascadeOnDelete`). `barber_service` pivot cədvəli silindi.
- Xidmət bir bərbərə məxsusdur, qiyməti və müddəti də onundur. Məsələn: Raufun saç kəsimi 20 ₼, Elvininki 15 ₼.
- Layihə hələ deploy və commit olunmadığı üçün orijinal migration-lar dəyişdirildi, ayrıca "alter" migration yaradılmadı.
- `barber_id` **fillable-da deyil**: bərbər formadan `barber_id` göndərərək xidməti başqasının adına keçirə bilməz. Sahib yalnız service qatında açıq şəkildə təyin olunur. Strict mode belə cəhdi xəta ilə dayandırır (test var).

**İcazə matrisi:**

| Əməliyyat | Menecer | Bərbər |
|---|---|---|
| Xidmət yarat / redaktə / sil | istənilən bərbərin adına | yalnız özününkü |
| Həftəlik qrafik, fasilələr, istisnalar | hamı | yalnız özü |
| Blok | istənilən bərbər və ya bütün berberxana | yalnız özü (berberxana bloklarını görür, silə bilməz) |
| Profil (ad, vəzifə, bio, telefon, şəkil, Telegram) | `barbers.edit` ilə | `/panel/profile` |
| Şifrə | `barbers.edit` ilə | `/panel/profile` (hazırkı şifrə tələb olunur) |
| Aktivlik, sıra, email, login hesabı, bərbər əlavə/sil | ✅ | ❌ |

**Policy-lər:**
- Yeni `ServicePolicy` və `BlockedPeriodPolicy`.
- `BarberPolicy`: `manageSchedule` və `updateProfile` artıq bərbərin özünə də icazə verir.
- Yeni scope-lar: `Service::visibleTo()`, `Service::bookable()` (aktiv xidmət + aktiv bərbər), `BlockedPeriod::visibleTo()`.

**Route-lar:**
- `services`, `barbers/{barber}/schedule`, `exceptions` və `blocked-periods` artıq menecer və bərbər üçün ortaqdır. Hər əməliyyatı Policy yoxlayır, başqasının obyektinə müraciət 403 qaytarır.
- `barbers` resource yalnız menecer üçündür.
- Bərbər üçün `my-schedule` (öz qrafik səhifəsinə redirect) və `profile`.
- **`auth.session` middleware** əlavə olundu: şifrə dəyişəndə həmin hesabın digər cihazlardakı sessiyaları etibarsız olur.

**Booking engine:**
- **"Fərq etməz":** eyni adlı xidmətlər bir qrupdur (`Service::groupKey()` = adın slug-ı). `AvailabilityService::groupServices()` qrupun bookable xidmətlərini ən az yüklü bərbərdən başlayaraq sıralayır. `BookingService` hər namizədi onun ÖZ xidməti ilə yoxlayır, snapshot-a da seçilən ustanın qiyməti yazılır.
- **Konkret bərbər:** xidmət ona məxsus olmalıdır, əks halda "Seçilmiş bərbər bu xidməti göstərmir" xətası verilir.
- **Başqa bərbərə keçirmə (reschedule):** hədəf bərbərin eyni adlı aktiv xidməti olmalıdır. Qiymət snapshot-dakı kimi qalır.

**Public sayt:**
- Qiymət siyahısı qruplanır və qiymət aralığı göstərir ("Saç kəsimi 15–20 ₼"). Bunu `App\Support\ServiceGroups` qurur.
- Komanda kartlarında hər ustanın öz qiymətləri göstərilir.
- Wizard: usta → xidmət → vaxt → məlumat.
- **Linklər:** `?barber=slug`, `?service=slug` (konkret xidmət), `?xidmet=qrup` ("Fərq etməz" + həmin qrup).
- Xidməti olmayan bərbər wizard-da göstərilmir.

**Testlər:** cəmi 193 test (691 assertion).
- Yeni test faylı: `ProfileTest`.
- `ServiceManagementTest` yenidən yazıldı.
- İcazə, "Fərq etməz" qiyməti, başqa bərbərin xidmətinin rezervasiyası, privilege escalation və reschedule qaydaları üçün testlər əlavə olundu.

### PHASE 8 — Admin Panel

**ReportService** (`app/Services/ReportService.php`): dashboard və hesabatların bütün rəqəmləri buradan gəlir, controller-lərdə sorğu yazılmır.
- **Gəlir** yalnız `completed` statuslu rezervasiyaların snapshot qiymətidir (`service_price`).
- Aktiv, amma hələ baş tutmamış rezervasiyalar ayrıca **"gözlənilən"** kimi göstərilir.
- Dövrlər Bakı gününə görə hesablanır, sorğular isə UTC ilə aparılır.
- `Appointment::startingWithin([from, to))` yarımaçıq intervaldır. `whereBetween` sonu da daxil etdiyi üçün növbəti günün 00:00-ı bu günə düşərdi.

**Səhifələr:**

| Səhifə | Menecer | Bərbər |
|---|---|---|
| Dashboard: bu gün, gələcək, ay üzrə tamamlanmış/ləğv, gəlir, müştərilər, gündəlik, təsdiq gözləyənlər | hamısı + bərbərlər üzrə statistika | yalnız öz rəqəmləri |
| Calendar: gün (sütun = bərbər), həftə (bir bərbər), ay (say + gəlir) | hamısı, bərbər filteri ilə | yalnız özü |
| Rezervasiyalar: filterlər (dövr, status, bərbər, ad/telefon axtarışı), detal | hamısı | yalnız öz |
| Status: təsdiqlə / tamamlandı / gəlmədi / ləğv (səbəb ilə) | ✅ | öz rezervasiyaları üçün |
| Əl ilə rezervasiya, başqa vaxta/bərbərə keçirmə | ✅ | ❌ |
| Müştərilər (CRM): ziyarət sayı, xərc, son ziyarət, tarixçə, private qeydlər | hamısı | yalnız ona yazılanlar, statistika da yalnız onun ziyarətlərinə görə |
| Müştərinin ad/telefon redaktəsi | ✅ | ❌ |
| Hesabatlar: dövr, hazır intervallar, bərbər və xidmət üzrə, status bölgüsü, orta çek | ✅ | ❌ |
| Parametrlər: məlumatlar, iş saatları, booking qaydaları, Telegram | ✅ | ❌ |

**Qərarlar:**
- **Calendar kitabxanasızdır:** server-rendered Blade istifadə olunur.
  - Elementlər mütləq mövqe ilə yerləşdirilir: `top = (dəqiqə − başlanğıc) × 1.2px`.
  - İş saatları, fasilələr (ştrix), bloklar (qırmızı ştrix) və "indiki an" xətti göstərilir.
  - İş pəncərəsi `AvailabilityService::workingWindow()` ilə hesablanır (indi public-dir), beləliklə məntiq təkrarlanmır.
- **Statistika saxlanmır:** `withCount`, `withSum` və `withMax` ilə hesablanır (normalizasiya).
- **Xidmət üzrə hesabat** snapshot adına (`service_name`) görə qruplanır: xidmət silinsə və ya adı dəyişsə belə tarixçə düzgün qalır.
- **Telefon axtarışı:** "050 123" yazılanda baştakı 0 atılır və axtarış "50123" ilə aparılır (`PhoneNumber::searchFragment`). Bu boşluq testdə aşkarlandı.
- **Telegram token heç vaxt formaya geri yazılmır**, yalnız "təyin edilib" göstərilir. Boş göndərilsə, mövcud token qalır. Token şifrəli saxlanılır və audit jurnalına düşmür. Bildirişi aktiv etmək üçün token və chat ID tələb olunur.
- **Rezervasiyanın tarixçəsi** (audit) detal səhifəsində göstərilir (yalnız menecer). Əməliyyat adlarının tərcüməsi `lang/az/audit.php`-dədir.
- Sifarişçi menecer menyusundakı "Xidmətlər" bəndini şərhə alıb (`layouts/panel.blade.php`). Səhifə işləyir, ona bərbərin redaktə səhifəsindən də keçmək olur.

**Testlər:** cəmi 229 test (831 assertion).
- `AppointmentManagementTest`: 16 test.
- `DashboardCalendarTest`: 8 test.
- `CustomerReportSettingsTest`: 15 test.
- `PhoneNumberTest`: +1 test.

## Biznes qaydaları (PHASE 1 default-ları)
- Onlayn rezervasiya: `auto_confirm = true` (parametrdən dəyişdirilə bilər).
- Bir telefon nömrəsi ilə maksimum 3 aktiv gələcək rezervasiya ola bilər, vaxtları üst-üstə düşən rezervasiyalar qadağandır.
- Müştəri rezervasiyanı başlanğıcdan 2 saat əvvələ qədər ləğv edə bilər.
- Slot addımı 15 dəq, buffer 5 dəq, rezervasiya üfüqü 30 gün, minimum xəbərdarlıq müddəti 60 dəq.

## Açıq məsələlər
- MySQL image: `mysql:latest` (9.1) əvəzinə `mysql:8.4` (LTS) olmalıdır. 9.x data volume-u 8.4-ə downgrade oluna bilmir. Keçid üçün `docker compose down -v` (bütün data silinir) və `up -d` lazımdır. İndi data yalnız seed-dən ibarət olduğu üçün keçid təhlükəsizdir.
