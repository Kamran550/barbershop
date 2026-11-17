<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Barber;
use App\Models\User;
use App\Support\UniqueSlug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bərbərlərin idarəsi: profil, login hesabı, xidmətlər, şəkil, aktiv/deaktiv.
 * Profil + hesab + xidmətlər bir transaction-da yazılır: ya hamısı, ya heç biri.
 */
class BarberManager
{
    private const PHOTO_DIR = 'barbers';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ImageStorage $images,
    ) {}

    public function create(array $data, ?UploadedFile $photo = null): Barber
    {
        $photoPath = $photo ? $this->images->store($photo, self::PHOTO_DIR) : null;

        try {
            return DB::transaction(function () use ($data, $photoPath) {
                $barber = new Barber($this->profileAttributes($data));
                $barber->slug = UniqueSlug::for(Barber::class, $barber->name);
                $barber->photo_path = $photoPath;

                if (filled($data['email'] ?? null)) {
                    $barber->user_id = $this->createAccount($barber->name, $data['email'], $data['password'], $barber->is_active)->id;
                }

                $barber->save();

                $this->audit->log('barber.created', $barber, new: [
                    ...$barber->only(['name', 'is_active']),
                    'has_account' => $barber->user_id !== null,
                ]);

                return $barber;
            });
        } catch (\Throwable $e) {
            $this->images->delete($photoPath); // DB yazılmadısa, yetim fayl qalmasın

            throw $e;
        }
    }

    /** Menecerin tam redaktəsi: profil + aktivlik + sıra + login hesabı. */
    public function update(Barber $barber, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): Barber
    {
        return DB::transaction(function () use ($barber, $data, $photo, $removePhoto) {
            $barber->fill($this->profileAttributes($data));
            $this->applyNameAndPhoto($barber, $photo, $removePhoto);

            $this->syncAccount($barber, $data);
            $barber->save();
            $this->audit->logChanges('barber.updated', $barber);

            return $barber;
        });
    }

    /**
     * Bərbərin ÖZ profilini redaktəsi: yalnız şəxsi sahələr.
     * Aktivlik, sıra, email və login hesabı burada dəyişə bilməz (bunlar menecerindir).
     */
    public function updateOwnProfile(Barber $barber, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): Barber
    {
        return DB::transaction(function () use ($barber, $data, $photo, $removePhoto) {
            $barber->fill(Arr::only($data, ['name', 'title', 'bio', 'phone', 'telegram_chat_id']));
            $this->applyNameAndPhoto($barber, $photo, $removePhoto);
            $barber->save();

            if ($barber->user && $barber->wasChanged('name')) {
                $barber->user->update(['name' => $barber->name]);
            }

            $this->audit->logChanges('barber.profile_updated', $barber);

            return $barber;
        });
    }

    private function applyNameAndPhoto(Barber $barber, ?UploadedFile $photo, bool $removePhoto): void
    {
        if ($barber->isDirty('name')) {
            $barber->slug = UniqueSlug::for(Barber::class, $barber->name, $barber->id);
        }

        if ($photo) {
            $barber->photo_path = $this->images->replace($barber->photo_path, $photo, self::PHOTO_DIR);
        } elseif ($removePhoto && $barber->photo_path) {
            $this->images->delete($barber->photo_path);
            $barber->photo_path = null;
        }
    }

    /**
     * Rezervasiyası olan bərbər silinmir (DB-də də restrict FK var) — deaktiv edilir.
     * Rezervasiyası olmayan bərbər login hesabı ilə birlikdə silinir.
     */
    public function delete(Barber $barber): void
    {
        if ($barber->appointments()->exists()) {
            throw new BusinessRuleException('Bu bərbərin rezervasiya tarixçəsi var. Silmək əvəzinə deaktiv edin.');
        }

        DB::transaction(function () use ($barber) {
            $this->audit->log('barber.deleted', $barber, old: $barber->only(['name']));
            $user = $barber->user;
            $barber->delete(); // qrafik, fasilə, istisnalar cascade ilə silinir
            $user?->delete();
        });

        $this->images->delete($barber->photo_path);
    }

    private function profileAttributes(array $data): array
    {
        return Arr::only($data, ['name', 'title', 'bio', 'phone', 'telegram_chat_id', 'is_active', 'sort_order']);
    }

    private function createAccount(string $name, string $email, string $password, bool $active): User
    {
        return User::create([
            'name' => $name,
            'email' => Str::lower($email),
            'password' => $password, // 'hashed' cast avtomatik bcrypt edir
            'role' => UserRole::Barber,
            'is_active' => $active,
        ]);
    }

    /**
     * Hesab qaydaları:
     *  - hesab yoxdur + email verilib  → yeni hesab
     *  - hesab var → ad/email/aktivlik profil ilə sinxron; şifrə yalnız verilibsə dəyişir
     *  - bərbər deaktiv edilir → hesab da deaktiv (active middleware dərhal sistemdən çıxarır)
     */
    private function syncAccount(Barber $barber, array $data): void
    {
        $email = $data['email'] ?? null;

        if (! $barber->user_id) {
            if (filled($email)) {
                $barber->user_id = $this->createAccount($barber->name, $email, $data['password'], $barber->is_active)->id;
                $this->audit->log('barber.account_created', $barber, new: ['email' => Str::lower($email)]);
            }

            return;
        }

        $user = $barber->user;
        $user->fill(['name' => $barber->name, 'is_active' => $barber->is_active]);

        if (filled($email)) {
            $user->email = Str::lower($email);
        }
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();
        $this->audit->logChanges('user.updated', $user);
    }
}
