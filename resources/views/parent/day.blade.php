@php
    $amountLabels = ['all' => 'Yedi', 'some' => 'Az yedi', 'none' => 'Yemedi'];
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Baglantinin kendisi bir anahtar; adres cubugundaki token'in disari
         sizmamasi icin referrer gonderilmez ve sayfa dizine eklenmez. --}}
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ $child->first_name }} — {{ $day->locale('tr')->translatedFormat('j F Y') }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 20px 16px 48px;
            font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f6f7f9; color: #1b1c1e;
        }
        .wrap { max-width: 520px; margin: 0 auto; }
        header { margin-bottom: 20px; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        .sub { color: #5c6066; font-size: 14px; }
        .card {
            background: #fff; border: 1px solid #e4e6ea; border-radius: 12px;
            padding: 14px 16px; margin-bottom: 12px;
        }
        .row { display: flex; justify-content: space-between; gap: 12px; align-items: baseline; }
        .row + .row { margin-top: 10px; padding-top: 10px; border-top: 1px solid #f0f1f3; }
        .label { color: #5c6066; }
        .value { font-weight: 600; text-align: right; }
        .muted { color: #8a8f96; font-weight: 400; }
        h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .04em;
             color: #5c6066; margin: 24px 0 8px; }
        .photos { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .photos img { width: 100%; height: 100%; aspect-ratio: 1; object-fit: cover;
                      border-radius: 10px; display: block; background: #e9ebee; }
        .empty { color: #5c6066; }
        footer { margin-top: 28px; color: #8a8f96; font-size: 13px; text-align: center; }
        @media (prefers-color-scheme: dark) {
            body { background: #16181b; color: #e9ebee; }
            .card { background: #1e2125; border-color: #2c3036; }
            .row + .row { border-top-color: #2c3036; }
            .sub, .label, h2, .empty { color: #a2a8b0; }
            .photos img { background: #2c3036; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>{{ $child->first_name }} {{ $child->last_name }}</h1>
        <div class="sub">{{ $classroom->name }} · {{ $day->locale('tr')->translatedFormat('j F Y, l') }}</div>
    </header>

    @if ($ozet['bos'])
        <div class="card empty">Bugün için henüz kayıt girilmemiş.</div>
    @else
        <div class="card">
            <div class="row">
                <span class="label">Yoklama</span>
                <span class="value">
                    @if ($ozet['geldi'] === null)
                        <span class="muted">İşaretlenmemiş</span>
                    @elseif ($ozet['geldi'])
                        Geldi
                        @if ($ozet['geldi_saat'])
                            <span class="muted">· {{ $ozet['geldi_saat']->format('H:i') }}</span>
                        @endif
                    @else
                        Gelmedi
                    @endif
                </span>
            </div>
            {{-- Cocuk gelmediyse verisi olmayan satirlar gizlenir; verisi olan
                 satir celiskili de olsa gosterilir, gercek kayit saklanmaz. --}}
            @if ($ozet['ogun_goster'])
                <div class="row">
                    <span class="label">Kahvaltı</span>
                    <span class="value">
                        @if (isset($amountLabels[$ozet['kahvalti']]))
                            {{ $amountLabels[$ozet['kahvalti']] }}
                        @else
                            <span class="muted">İşaretlenmemiş</span>
                        @endif
                    </span>
                </div>

                <div class="row">
                    <span class="label">Öğle yemeği</span>
                    <span class="value">
                        @if (isset($amountLabels[$ozet['ogle']]))
                            {{ $amountLabels[$ozet['ogle']] }}
                        @else
                            <span class="muted">İşaretlenmemiş</span>
                        @endif
                    </span>
                </div>
            @endif

            @if ($ozet['uyku_goster'])
                <div class="row">
                    <span class="label">Uyku</span>
                    <span class="value">
                        @if ($ozet['uyku_basi'] === null)
                            <span class="muted">Uyumadı</span>
                        @elseif ($ozet['uyku_sonu'] === null)
                            {{ $ozet['uyku_basi']->format('H:i') }}'de uyudu
                            <span class="muted">· uyanma saati girilmemiş</span>
                        @else
                            {{ $ozet['uyku_basi']->format('H:i') }}–{{ $ozet['uyku_sonu']->format('H:i') }}
                            <span class="muted">· {{ $ozet['uyku_dakika'] }} dk</span>
                        @endif
                    </span>
                </div>
            @endif

            @if ($ozet['tuvalet_goster'])
                <div class="row">
                    <span class="label">Tuvalet / bez</span>
                    <span class="value">
                        @if ($ozet['tuvalet'] === 0 && $ozet['bez'] === 0)
                            <span class="muted">Kayıt yok</span>
                        @else
                            {{ $ozet['tuvalet'] }} tuvalet · {{ $ozet['bez'] }} bez
                        @endif
                    </span>
                </div>
            @endif
        </div>
    @endif

    @if (count($fotograflar) > 0)
        <h2>Fotoğraflar</h2>
        <div class="photos">
            @foreach ($fotograflar as $fotograf)
                <img src="{{ $fotograf['url'] }}"
                     alt="{{ $child->first_name }} · {{ $fotograf['taken_at']->format('H:i') }}"
                     loading="lazy">
            @endforeach
        </div>
    @endif

    <footer>Bu sayfa yalnızca {{ $child->first_name }} içindir.</footer>
</div>
</body>
</html>
