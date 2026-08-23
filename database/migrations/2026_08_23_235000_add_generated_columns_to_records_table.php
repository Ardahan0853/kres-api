<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * records.value icindeki alanlari turetilmis (generated) kolon olarak acar.
 *
 * Amac web panelinin `value->>'amount'` yerine `meal_amount` yazabilmesi:
 * gercek kolon gibi sorgulanir ve indekslenebilir. Yazma yolu DEGISMEZ;
 * kolonlar value'dan turetilir, uygulama onlara yazmaz. Sekil degisirse
 * kolon dusurulup yeniden tanimlanir, veri kaybolmaz.
 *
 * Ifadeler motora gore ayrilir cunku iki motor ayni sonucu vermiyor:
 * PostgreSQL'de ->> bir JSON boolean icin 'true'/'false', SQLite'ta '1'/'0'
 * doner. Kolon adlari ve anlamlari her iki motorda ayni kalsin diye
 * `present` iki tarafta da boolean gibi davranacak sekilde yazilir.
 *
 * Zaman alanlari metin olarak birakildi: PostgreSQL text->timestamptz
 * cast'ini turetilmis kolonda kabul etmiyor (immutable degil). ISO 8601
 * damgalari sozluk sirasinda da kronolojik siralandigi icin karsilastirma
 * ve siralama yine dogru calisir.
 */
return new class extends Migration
{
    /** @return array<string, array{0: string, 1: string}> ad => [tip, ifade] */
    private function columns(): array
    {
        $pgsql = DB::getDriverName() === 'pgsql';

        return [
            'present' => $pgsql
                ? ['boolean', "(value->>'present')::boolean"]
                : ['integer', "cast(value->>'present' as integer)"],
            'meal_kind' => ['text', "value->>'meal'"],
            'meal_amount' => ['text', "value->>'amount'"],
            'nap_started_at' => ['text', "value->>'started_at'"],
            'nap_ended_at' => ['text', "value->>'ended_at'"],
            'toilet_kind' => ['text', "value->>'kind'"],
        ];
    }

    public function up(): void
    {
        foreach ($this->columns() as $ad => [$tip, $ifade]) {
            DB::statement("alter table records add column {$ad} {$tip} generated always as ({$ifade}) stored");
        }
    }

    public function down(): void
    {
        Schema::table('records', function ($table) {
            $table->dropColumn(array_keys($this->columns()));
        });
    }
};
