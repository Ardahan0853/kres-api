<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\Classroom;
use App\Models\Guardian;
use Illuminate\Database\Seeder;

/**
 * Gelistirme icin tek bir gercek veli olusturur.
 *
 * Numara koda YAZILMAZ, `.env` icindeki DEV_PARENT_PHONE'dan okunur. Depo bir
 * gun public olursa gercek bir telefon numarasi git gecmisinde kalmasin diye
 * boyle; env bos ise veli hic olusturulmaz ve seeder sessizce gecer.
 *
 * Idempotenttir, tek basina da calistirilabilir:
 *   php artisan db:seed --class=DevParentSeeder
 */
class DevParentSeeder extends Seeder
{
    public function run(): void
    {
        $raw = (string) config('kres.dev_parent_phone');

        if ($raw === '') {
            $this->command?->info('DEV_PARENT_PHONE bos, test velisi olusturulmadi.');

            return;
        }

        $phone = Guardian::normalizePhone($raw);

        if ($phone === null) {
            $this->command?->warn("DEV_PARENT_PHONE cozulemedi ({$raw}), test velisi olusturulmadi.");

            return;
        }

        // Fotografli akisin da denenebilmesi icin izni olan bir cocuk secilir.
        // Isimler her seed'de rastgele uretildigi icin isme gore aranmaz.
        $classroom = Classroom::withoutGlobalScopes()
            ->whereHas('institution', fn ($q) => $q->where('name', 'Papatya Anaokulu'))
            ->where('name', 'Papatyalar')
            ->first();

        if ($classroom === null) {
            $this->command?->warn('Papatyalar sinifi bulunamadi, test velisi olusturulmadi.');

            return;
        }

        $child = Child::withoutGlobalScopes()
            ->where('classroom_id', $classroom->id)
            ->where('photo_consent', Child::CONSENT_GRANTED)
            ->orderBy('first_name')
            ->first();

        if ($child === null) {
            $this->command?->warn('Izinli cocuk bulunamadi, test velisi olusturulmadi.');

            return;
        }

        $guardian = Guardian::withoutGlobalScopes()->firstOrNew([
            'institution_id' => $classroom->institution_id,
            'phone' => $phone,
        ]);

        $guardian->institution_id = $classroom->institution_id;
        $guardian->name = 'Test Veli';
        $guardian->phone = $phone;
        $guardian->phone_raw = $raw;
        $guardian->save();

        $guardian->children()->syncWithoutDetaching([
            $child->id => ['relation' => 'veli'],
        ]);

        $this->command?->info(sprintf(
            'Test velisi hazir: %s -> %s %s (%s)',
            $guardian->maskedPhone(),
            $child->first_name,
            $child->last_name,
            $classroom->name
        ));
    }
}
