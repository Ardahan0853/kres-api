<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** @var list<string> */
    private const FIRST_NAMES = [
        'Deniz', 'Elif', 'Kerem', 'Zeynep', 'Ali', 'Defne', 'Mert', 'Ada',
        'Yusuf', 'Nehir', 'Ege', 'Asya', 'Poyraz', 'Duru', 'Alp', 'Masal',
        'Kaan', 'Lina', 'Ömer', 'Nisan', 'Bora', 'İpek', 'Çınar', 'Ceren',
        'Arda', 'Şevval', 'Umut', 'Güneş', 'Toprak', 'Meryem',
    ];

    /** @var list<string> */
    private const LAST_NAMES = [
        'Yılmaz', 'Kaya', 'Demir', 'Şahin', 'Çelik', 'Yıldız', 'Yıldırım',
        'Öztürk', 'Aydın', 'Özdemir', 'Arslan', 'Doğan', 'Kılıç', 'Aslan',
        'Çetin', 'Kara', 'Koç', 'Kurt', 'Özkan', 'Şimşek',
    ];

    public function run(): void
    {
        $papatya = Institution::create(['name' => 'Papatya Anaokulu']);

        User::create([
            'institution_id' => $papatya->id,
            'name' => 'Papatya Yönetici',
            'email' => 'admin@papatya.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);

        $ayse = User::create([
            'institution_id' => $papatya->id,
            'name' => 'Ayşe Öğretmen',
            'email' => 'ayse@papatya.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
        ]);

        $mehmet = User::create([
            'institution_id' => $papatya->id,
            'name' => 'Mehmet Öğretmen',
            'email' => 'mehmet@papatya.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
        ]);

        $papatyalar = $this->classroom($papatya, 'Papatyalar');
        $laleler = $this->classroom($papatya, 'Laleler');
        $menekseler = $this->classroom($papatya, 'Menekşeler');

        $ayse->classrooms()->attach([$papatyalar->id, $laleler->id]);
        $mehmet->classrooms()->attach($menekseler->id);

        // Izolasyonu dogrulamak icin ikinci kurum. Hicbir uc noktada gorunmemeli.
        $ikinci = Institution::create(['name' => 'Test Kurum 2']);
        $digerSinif = $this->classroom($ikinci, 'Kelebekler');
        $this->children($digerSinif, 5);

        $this->children($papatyalar, random_int(8, 12));
        $this->children($laleler, random_int(8, 12));
        $this->children($menekseler, random_int(8, 12));
    }

    private function classroom(Institution $institution, string $name): Classroom
    {
        return Classroom::create([
            'institution_id' => $institution->id,
            'name' => $name,
        ]);
    }

    private function children(Classroom $classroom, int $count): void
    {
        $names = collect(self::FIRST_NAMES)->shuffle()->take($count);

        foreach ($names as $index => $firstName) {
            Child::create([
                'institution_id' => $classroom->institution_id,
                'classroom_id' => $classroom->id,
                'first_name' => $firstName,
                'last_name' => self::LAST_NAMES[array_rand(self::LAST_NAMES)],
                // Her sinifta en az bir 'denied' ve bir 'pending' olur ki
                // izin akisi gercekten denenebilsin.
                'photo_consent' => Child::consentForIndex($index),
                // Kres yasi: 2-6 yas arasi bir dogum tarihi
                'birth_date' => now()
                    ->subYears(random_int(2, 6))
                    ->subDays(random_int(0, 364))
                    ->toDateString(),
            ]);
        }
    }
}
