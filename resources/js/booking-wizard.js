/**
 * Rezervasiya wizard-ı (Alpine.js komponenti).
 *
 * Addımlar: 1 Usta → 2 Xidmət → 3 Tarix + saat → 4 Məlumat
 *
 * Xidmətlər bərbərə məxsusdur (hər ustanın öz qiyməti). Ona görə əvvəl usta seçilir, sonra onun xidmətləri.
 * "Fərq etməz" seçiləndə eyni adlı xidmətlər qrup kimi göstərilir ("Saç kəsimi · 15–20 ₼");
 * serviceId = qrupun nümayəndəsi, barberId = 'any' — server ilk boş ustanı və onun öz xidmətini seçir.
 *
 * Statik data (xidmətlər, qruplar, bərbərlər, qrafik günləri) server tərəfindən config kimi verilir.
 * Yeganə şəbəkə sorğusu: tarix seçiləndə boş saatlar (GET /rezervasiya/saatlar).
 * Son göndərmə — adi HTML form POST (CSRF + server-side validation), JS yoxdursa belə məntiq serverdədir.
 */

const WEEKDAYS = ['B.', 'B.e.', 'Ç.a.', 'Ç.', 'C.a.', 'C.', 'Ş.']; // JS getUTCDay(): 0 = Bazar
const MONTHS = ['yan', 'fev', 'mar', 'apr', 'may', 'iyn', 'iyl', 'avq', 'sen', 'okt', 'noy', 'dek'];
const MONTHS_FULL = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avqust', 'sentyabr', 'oktyabr', 'noyabr', 'dekabr'];

/** "2030-05-07" ↔ Date (UTC-də işləyirik ki, brauzerin saat qurşağı tarixi sürüşdürməsin) */
const parse = (iso) => new Date(`${iso}T00:00:00Z`);
const iso = (d) => d.toISOString().slice(0, 10);

export default (config) => ({
    step: 1,
    services: config.services,
    groups: config.groups,
    barbers: config.barbers,
    today: config.today,

    barberId: null, // number | 'any'
    serviceId: null, // konkret xidmət və ya "Fərq etməz" halında qrupun nümayəndəsi
    date: null,
    time: null,
    name: config.old.name ?? '',
    phone: config.old.phone ?? '',

    slots: [],
    loading: false,
    slotError: null,
    submitting: false,
    controller: null,

    init() {
        const old = config.old;
        const pre = config.preselect;

        if (old.service_id) {
            // Server xətasından sonra qayıdış: əvvəlki seçimləri bərpa et
            this.serviceId = Number(old.service_id);
            this.barberId = old.barber_id === 'any' ? 'any' : Number(old.barber_id);
            this.date = old.date;
            this.time = config.errorStep === 3 ? null : old.time;
            this.step = config.errorStep ?? 4;
            if (this.date) this.loadSlots();
        } else {
            // Linklərdən əvvəlcədən seçim: ?service=… (konkret), ?xidmet=… (qrup), ?barber=…
            const service = this.services.find((s) => s.slug === pre.service);
            const group = this.groups.find((g) => g.key === pre.group);
            const barber = this.barbers.find((b) => b.slug === pre.barber);

            if (service) {
                this.barberId = service.barberId;
                this.serviceId = service.id;
                this.step = 3;
            } else if (group) {
                this.barberId = 'any';
                this.serviceId = group.representativeId;
                this.step = 3;
            } else if (barber) {
                this.barberId = barber.id;
                this.step = 2;
            }
        }

        if (this.step === 3 && !this.date) this.pickDate(this.firstOpenDate(), true);
        this.$watch('step', () => this.$nextTick(() => window.renderIcons?.()));
    },

    // ── Seçimlər ─────────────────────────────────────────────────
    get service() {
        return this.services.find((s) => s.id === this.serviceId) ?? null;
    },
    get barber() {
        return this.barbers.find((b) => b.id === this.barberId) ?? null;
    },
    /** "Fərq etməz" halında seçilmiş qrup */
    get group() {
        return this.service ? this.groups.find((g) => g.key === this.service.groupKey) ?? null : null;
    },
    /** 2-ci addımda göstəriləcək seçimlər: ustanın öz xidmətləri və ya qruplar */
    get barberServices() {
        return this.services.filter((s) => s.barberId === this.barberId);
    },

    chooseBarber(id) {
        if (this.barberId !== id) this.serviceId = null; // başqa ustanın xidməti ona aid deyil
        this.barberId = id;
        this.resetTime();
        this.step = 2;
    },

    chooseService(id) {
        this.serviceId = id;
        this.resetTime();
        this.step = 3;
        this.pickDate(this.firstOpenDate(), true);
    },

    chooseGroup(group) {
        this.chooseService(group.representativeId);
    },

    /** Xülasədə göstəriləcək qiymət və müddət: usta seçilibsə onunku, "Fərq etməz"-də aralıq */
    get priceLabel() {
        return this.barberId === 'any' ? this.group?.price : this.service?.price;
    },
    get durationLabel() {
        return this.barberId === 'any' ? this.group?.duration : this.service ? `${this.service.duration} dəq` : '';
    },

    resetTime() {
        this.date = null;
        this.time = null;
        this.slots = [];
    },

    // ── Tarix zolağı ─────────────────────────────────────────────
    get days() {
        const start = parse(this.today);
        return Array.from({ length: config.horizonDays + 1 }, (_, i) => {
            const d = new Date(start);
            d.setUTCDate(d.getUTCDate() + i);
            const value = iso(d);
            return {
                value,
                weekday: i === 0 ? 'Bu gün' : i === 1 ? 'Sabah' : WEEKDAYS[d.getUTCDay()],
                day: d.getUTCDate(),
                month: MONTHS[d.getUTCMonth()],
                open: this.isOpen(value, d.getUTCDay() || 7),
            };
        });
    },

    /** Bərbər həmin gün ümumiyyətlə işləyirmi (qrafikə görə)? Dəqiq boş saatları server deyir. */
    isOpen(date, isoWeekday) {
        const pool = this.barberId === 'any'
            ? this.barbers.filter((b) => this.group?.barberIds.includes(b.id))
            : this.barber ? [this.barber] : [];
        return pool.some((b) => {
            if (b.offDates.includes(date)) return false;
            return b.extraDates.includes(date) || b.weekdays.includes(isoWeekday);
        });
    },

    firstOpenDate() {
        return this.days.find((d) => d.open)?.value ?? null;
    },

    /**
     * @param auto true = istifadəçi yox, wizard özü seçir. Belə halda seçilən gündə boş saat yoxdursa
     *             (məs. axşamdır, bu gün bitib) avtomatik növbəti iş gününə keçirik — boş ekran göstərmirik.
     */
    pickDate(value, auto = false, attempt = 0) {
        if (!value) return;
        this.date = value;
        this.time = null;
        this.loadSlots().then((current) => {
            // current = false → bu sorğunu daha yeni sorğu əvəz edib (istifadəçi başqa tarix seçib) — toxunmuruq
            if (!auto || !current || this.slots.length || this.slotError || attempt >= 7) return;
            const next = this.days.find((d) => d.open && d.value > value)?.value;
            if (next) this.pickDate(next, true, attempt + 1);
        });
    },

    /**
     * Hər sorğunun öz AbortController-i var. Yeni sorğu köhnəni ləğv edir və vəziyyəti
     * (slots, loading, error) YALNIZ hələ aktual olan sorğu yeniləyir — gecikmiş cavab yenisinin üstünə yazılmır.
     *
     * @returns {Promise<boolean>} bu sorğu sonuna qədər aktual qaldımı
     */
    async loadSlots() {
        this.controller?.abort();
        const controller = (this.controller = new AbortController());
        const isCurrent = () => this.controller === controller;

        this.loading = true;
        this.slotError = null;
        this.slots = [];

        const params = new URLSearchParams({ service: this.serviceId, barber: this.barberId, date: this.date });

        try {
            const res = await fetch(`${config.slotsUrl}?${params}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            if (res.status === 429) throw new Error('Çox sayda sorğu. Bir dəqiqə sonra yenidən cəhd edin.');
            if (!res.ok) throw new Error('Boş saatları yükləmək mümkün olmadı.');
            const data = await res.json();
            if (isCurrent()) this.slots = data.slots;
        } catch (e) {
            if (e.name !== 'AbortError' && isCurrent()) this.slotError = e.message;
        } finally {
            if (isCurrent()) this.loading = false;
        }

        return isCurrent();
    },

    /** Saatları günün hissələrinə böl: Səhər / Gündüz / Axşam */
    get slotGroups() {
        const groups = [
            { label: 'Səhər', items: this.slots.filter((t) => t < '12:00') },
            { label: 'Gündüz', items: this.slots.filter((t) => t >= '12:00' && t < '17:00') },
            { label: 'Axşam', items: this.slots.filter((t) => t >= '17:00') },
        ];
        return groups.filter((g) => g.items.length);
    },

    chooseTime(t) {
        this.time = t;
        this.step = 4;
        // x-show elementi növbəti render-də göstərir — fokusu ondan sonra veririk
        requestAnimationFrame(() => requestAnimationFrame(() => document.getElementById('name')?.focus()));
    },

    // ── Naviqasiya və xülasə ─────────────────────────────────────
    goTo(step) {
        if (step < this.step) this.step = step;
    },

    get dateLabel() {
        if (!this.date) return '';
        const d = parse(this.date);
        return `${d.getUTCDate()} ${MONTHS_FULL[d.getUTCMonth()]}`;
    },

    get barberLabel() {
        return this.barberId === 'any' ? 'İlk boş usta' : this.barber?.name ?? '';
    },
});
