<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="utf-8">
    <title>{{ $permit->permit_number }}</title>
    <style>
        @foreach ($fonts as $font)
        @font-face {
            font-family: 'Noto Sans Ethiopic';
            font-style: normal;
            font-weight: 100 900;
            src: url('{{ $font['src'] }}') format('woff2');
            unicode-range: {{ $font['range'] }};
        }
        @endforeach

        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Noto Sans Ethiopic', sans-serif;
            color: #1c201e;
            font-size: 10.5pt;
            line-height: 1.45;
        }
        .page { padding: 16mm 16mm 12mm; min-height: 297mm; position: relative; }
        .band { height: 6mm; background: #1f6b45; }
        header { display: flex; align-items: center; gap: 6mm; border-bottom: 2px solid #1f6b45; padding-bottom: 5mm; }
        .logo {
            width: 22mm; height: 22mm; border: 1.5px dashed #8a948f; border-radius: 3mm;
            display: flex; align-items: center; justify-content: center;
            font-size: 7pt; color: #6b7570; text-align: center;
        }
        .logo img { max-width: 100%; max-height: 100%; }
        .authority-am { font-size: 13pt; font-weight: 700; }
        .authority-en { font-size: 9.5pt; color: #4a524e; }
        h1 { font-size: 17pt; margin: 6mm 0 1mm; }
        .subtitle { color: #4a524e; margin: 0 0 5mm; }
        .number-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm; }
        .number { font-size: 20pt; font-weight: 800; letter-spacing: 0.04em; }
        .label { font-size: 7.5pt; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7570; }
        .qr { width: 42mm; text-align: center; }
        .qr svg { width: 42mm; height: 42mm; display: block; }
        .qr .hint { font-size: 7pt; color: #4a524e; margin-top: 1mm; word-break: break-all; }
        table { width: 100%; border-collapse: collapse; margin-top: 5mm; }
        th, td { text-align: left; vertical-align: top; padding: 2.5mm 2mm; border-bottom: 1px solid #d7dcd9; }
        th { width: 42mm; }
        .secondary { color: #4a524e; font-size: 9pt; }
        .plate {
            display: inline-block; border: 1.5px solid #1c201e; border-radius: 1.5mm;
            padding: 0.5mm 2.5mm; font-weight: 800; letter-spacing: 0.08em; font-size: 11pt;
        }
        .vehicle + .vehicle { margin-top: 2mm; }
        .rules { margin-top: 6mm; border: 1.5px solid #1f6b45; border-radius: 3mm; padding: 4mm 5mm; }
        .rules h2 { font-size: 11pt; margin: 0 0 2mm; }
        .rules p { margin: 0 0 1.5mm; white-space: pre-line; }
        footer { position: absolute; left: 16mm; right: 16mm; bottom: 10mm; font-size: 8pt; color: #4a524e; border-top: 1px solid #d7dcd9; padding-top: 3mm; }
    </style>
</head>
<body>
<div class="band"></div>
<div class="page">
    <header>
        <div class="logo">
            @if (config('permits.document.logo_path') && is_file(config('permits.document.logo_path')))
                <img src="data:{{ mime_content_type(config('permits.document.logo_path')) }};base64,{{ base64_encode(file_get_contents(config('permits.document.logo_path'))) }}" alt="">
            @else
                LOGO<br>(placeholder)
            @endif
        </div>
        <div>
            <div class="authority-am">{{ __('common.authority', [], 'am') }}</div>
            <div class="authority-en">{{ __('common.authority', [], 'en') }}</div>
        </div>
    </header>

    <h1>በሰዓት ገደብ የከባድ ተሽከርካሪዎች መንቀሳቀሻ ፍቃድ</h1>
    <p class="subtitle">Time-restricted movement permit for heavy vehicles</p>

    <div class="number-row">
        <div>
            <div class="label">የፍቃድ ቁጥር · Permit number</div>
            <div class="number">{{ $permit->permit_number }}</div>
        </div>
        <div class="qr">
            {!! $qr !!}
            <div class="hint">{{ $verify_url }}</div>
        </div>
    </div>

    <table>
        <tr>
            <th><span class="label">የፍቃድ ዓይነት · Type</span></th>
            <td><strong>{{ $type_am }}</strong> <span class="secondary">· {{ $type_en }}</span></td>
        </tr>
        <tr>
            <th><span class="label">ተቋም · Organisation</span></th>
            <td><strong>{{ $organization_am }}</strong>@if ($organization_en !== $organization_am) <span class="secondary">· {{ $organization_en }}</span>@endif</td>
        </tr>
        <tr>
            <th><span class="label">ተሽከርካሪዎች · Vehicles</span></th>
            <td>
                @foreach ($vehicles as $vehicle)
                    <div class="vehicle">
                        <span class="plate">{{ $vehicle['plate'] }}</span>
                        <span class="secondary">{{ $vehicle['role_am'] }} · {{ $vehicle['type_am'] }} ({{ $vehicle['type_en'] }}) · {{ $vehicle['owner'] }}</span>
                    </div>
                @endforeach
            </td>
        </tr>
        <tr>
            <th><span class="label">የሚያገለግልበት ጊዜ · Valid</span></th>
            <td>
                <strong>{{ $valid_from['ethiopian_label'] }} – {{ $valid_to['ethiopian_label'] }}</strong>
                <div class="secondary">{{ $valid_from['ethiopian'] }} – {{ $valid_to['ethiopian'] }} ዓ.ም. · {{ $valid_from['gregorian'] }} – {{ $valid_to['gregorian'] }} (Gregorian)</div>
            </td>
        </tr>
        <tr>
            <th><span class="label">ምክንያት · Reason</span></th>
            <td>{{ $permit->reason }}</td>
        </tr>
        <tr>
            <th><span class="label">የተሰጠበት · Issued</span></th>
            <td>
                @if ($issued_at)
                    {{ $issued_at['ethiopian_label'] }} {{ $issued_time }}
                    <span class="secondary">· {{ $issued_at['gregorian'] }}</span>
                @endif
                <div class="secondary">{{ __('common.authority', [], 'en') }}</div>
            </td>
        </tr>
    </table>

    <div class="rules">
        <h2>የሰዓት ገደብ ደንቦች · Time-window rules</h2>
        <p>{{ $rules_am }}</p>
        <p class="secondary">{{ $rules_en }}</p>
    </div>

    <footer>
        ይህ ፍቃድ የሚሰራው በQR ኮዱ ወይም በ{{ $verify_url }} ላይ ሲረጋገጥ ብቻ ነው። የተሰረዘ ፍቃድ ወዲያውኑ ዋጋ ያጣል።<br>
        This permit is valid only as confirmed by scanning its QR code or at {{ $verify_url }}. A revoked permit stops being valid immediately.
    </footer>
</div>
</body>
</html>
