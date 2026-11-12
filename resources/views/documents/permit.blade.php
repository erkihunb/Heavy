<!DOCTYPE html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <title>{{ $permit->permit_number }}</title>
    <style>
        @foreach ($fonts as $font)
        @font-face {
            font-family: 'Abyssinica SIL';
            font-style: normal;
            font-weight: 400;
            src: url('{{ $font['src'] }}') format('woff2');
            unicode-range: {{ $font['range'] }};
        }
        @endforeach

        /*
         * An official certificate, not a screen: black ink on white, one
         * restrained blue for the title, fill-in lines instead of boxes.
         */
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; }
        /* Brand colours, taken from the Authority's logo: its green, and a deeper shade of it for text. */
        :root { --brand: #06aa07; --brand-deep: #0a6b0b; }
        /* Abyssinica SIL ships one weight; the renderer thickens it for bold text. */
        body {
            font-family: 'Abyssinica SIL', serif;
            color: #111;
            font-size: 12pt;
            line-height: 1.35;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* One A4 landscape sheet, 297 × 210 mm, with 1 mm spare against rounding. */
        .sheet { width: 297mm; height: 209mm; position: relative; overflow: hidden; }

        /* A heavy rule with a hairline inside it, as on printed certificates. */
        .border { position: absolute; inset: 6mm; border: 0.7mm solid var(--brand-deep); }
        .border::after { content: ''; position: absolute; inset: 1.3mm; border: 0.2mm solid var(--brand); }
        /* A band of the logo's green along the top and bottom, inside the frame. */
        .band { position: absolute; left: 7.3mm; right: 7.3mm; height: 2mm; background: var(--brand); }
        .band.top { top: 7.3mm; }
        .band.bottom { bottom: 7.3mm; }

        /* The truck drawing, faint behind the entries, as on the paper permit. */
        /* The security background, inside the frame and behind everything else. */
        .security { position: absolute; inset: 9.5mm 7.5mm; overflow: hidden; }
        .security .guilloche { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0.2; }
        .security .microtext {
            position: absolute; left: -40%; top: -40%; width: 180%; height: 180%;
            transform: rotate(-24deg); opacity: 0.14; color: var(--brand-deep);
            font-size: 5.2pt; font-weight: 700; line-height: 2.5; letter-spacing: 0.06em; white-space: nowrap;
        }

        .watermark {
            position: absolute; left: 40mm; right: 70mm; top: 58mm; bottom: 38mm;
            background-position: center; background-repeat: no-repeat; background-size: contain;
            opacity: 0.1;
        }

        .content { position: absolute; inset: 11mm 15mm 10mm; display: flex; flex-direction: column; }

        /* Header: emblem at the left, the Authority beside it, number and date at
           the right; the title centred underneath. */
        .masthead { display: flex; align-items: center; gap: 5mm; }
        .logo {
            width: 24mm; height: 24mm; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 6.5pt; color: #666; text-align: center;
        }
        /* The dashed box only marks where a logo goes while none is set. */
        .logo.placeholder { border: 1.5px dashed #888; border-radius: 3mm; }
        .logo img { width: 100%; height: 100%; object-fit: contain; }
        /* The Authority's name, Amharic over English, centred between the emblem and the number. */
        .masthead { position: relative; justify-content: space-between; }
        /* Centred on the page, in line with the title, whatever the widths of the emblem and the number. */
        .authority { position: absolute; left: 0; right: 0; top: 50%; transform: translateY(-50%); text-align: center; line-height: 1.25; }
        .authority .am { font-size: 22pt; font-weight: 700; color: var(--brand-deep); }
        .authority .en { font-size: 15pt; font-weight: 700; letter-spacing: 0.02em; margin-top: 0.5mm; }
        .title { text-align: center; margin-top: 4mm; }
        h1 {
            display: inline-block; margin: 0; padding: 0 8mm 1.6mm;
            font-size: 20pt; color: var(--brand-deep); border-bottom: 1mm solid var(--brand);
        }
        .ref { flex-shrink: 0; margin-left: auto; text-align: left; font-size: 11pt; line-height: 1.9; }
        .ref .fill { display: inline-block; min-width: 42mm; border-bottom: 0.4mm solid var(--brand); font-weight: 700; padding: 0 1mm; }
        .ref .fill.code { font-family: 'Abyssinica SIL', serif; letter-spacing: 0.08em; }

        .body { flex: 1; min-height: 0; display: flex; gap: 8mm; margin-top: 5mm; }

        /* The nine numbered entries, each written on a dotted fill-in line. */
        .entries { flex: 1; min-width: 0; margin: 0; padding: 0; list-style: none; }
        /* Labels in one column, values aligned in the next. */
        .entry { display: grid; grid-template-columns: 7mm 90mm minmax(0, 1fr); align-items: baseline; padding: 1.7mm 0; }
        .entry .n { font-weight: 700; color: var(--brand-deep); }
        .entry .what { padding-right: 3mm; }
        /* Each value written on a light dotted fill-in line; an empty one is left blank, as on a form. */
        .entry .value {
            min-width: 0; min-height: 5.5mm; font-weight: 700; padding: 0 1.5mm 0.6mm;
            border-bottom: 0.3mm dotted #8a8a8a; overflow-wrap: anywhere;
        }
        .entry .value .alt { font-weight: 400; font-size: 9.5pt; color: #333; }
        .entry .value .alt.below { display: block; }
        .entry .value .hours { display: block; }
        /* Entry 9: the issuer's name and the signature, each on its own part of the line. */
        .entry .value.issuer { display: flex; align-items: flex-end; gap: 4mm; padding: 0; border-bottom: 0; }
        .entry .value.issuer .name,
        .entry .value.issuer .sign { padding: 0 1.5mm 0.6mm; border-bottom: 0.3mm dotted #8a8a8a; }
        .entry .value.issuer .name { flex: 1.2; min-width: 0; }
        .entry .value.issuer .sign { flex: 1; font-weight: 400; font-size: 10pt; color: #333; }
        .entry .value.issuer .sign .line { display: none; }

        /* The QR code, beside the entries. */
        .side { width: 74mm; flex-shrink: 0; display: flex; flex-direction: column; align-items: center; gap: 2.5mm; }
        .qr { background: #fff; padding: 1.5mm; border: 0.5mm solid var(--brand-deep); }
        .qr svg { width: 70mm; height: 70mm; display: block; }
        .caption { font-size: 9.5pt; text-align: center; line-height: 1.3; }
        .caption .url { font-size: 8pt; color: #333; word-break: break-all; }
        /* The blank area under the QR code, beside the notes, where the round stamp is pressed. */
        .stamp-spot {
            position: absolute; right: 0; bottom: 9mm; width: 74mm; height: 44mm;
            display: flex; align-items: center; justify-content: center; font-size: 9pt; color: #555;
        }
        /* Where the QR code goes on a permit printed again after revocation. */
        .qr-revoked {
            width: 73mm; height: 73mm; display: flex; align-items: center; justify-content: center;
            border: 0.8mm solid #b3121b; color: #b3121b; font-size: 22pt; font-weight: 700; text-align: center;
        }

        /* A revoked permit: a large red stamp across the page and a line under the title. */
        .revoked-stamp {
            position: absolute; left: 38%; top: 52%; z-index: 5;
            transform: translate(-50%, -50%) rotate(-16deg);
            padding: 3mm 14mm; border: 2mm double #b3121b; border-radius: 3mm;
            color: #b3121b; font-size: 66pt; font-weight: 700; letter-spacing: 0.08em; line-height: 1.1;
            white-space: nowrap; opacity: 0.82; background: rgba(255, 255, 255, 0.35);
        }
        .revoked-line {
            margin: 2mm auto 0; padding: 1mm 4mm; display: inline-block;
            background: #b3121b; color: #fff; font-size: 12pt; font-weight: 700;
        }

        /* ማሳሰቢያ: the Authority's notes, as on the paper permit. */
        .notes { margin-top: 4mm; }
        .notes h2 { display: inline-block; margin: 0 0 1.2mm; font-size: 12pt; padding-bottom: 0.4mm; color: var(--brand-deep); border-bottom: 0.7mm solid var(--brand); }
        .notes ul { margin: 0; padding-left: 6mm; list-style: none; }
        .notes li { position: relative; font-size: 9.5pt; line-height: 1.4; }
        .notes li::before { content: '➤'; position: absolute; left: -5mm; font-size: 7.5pt; top: 0.4mm; }

        /* The verification line, set between two rules. */
        footer { margin-top: 2.5mm; display: flex; align-items: center; gap: 4mm; font-size: 9pt; font-weight: 700; color: #222; text-align: center; }
        footer::before, footer::after { content: ''; flex: 1; min-width: 10mm; border-top: 0.35mm solid var(--brand); }
        /* An expired permit: the same stamp in grey. */
        .revoked-stamp.is-expired { color: #4b4b4b; border-color: #4b4b4b; }
        .revoked-line.is-expired { background: #4b4b4b; }
        .qr-revoked.is-expired { color: #4b4b4b; border-color: #4b4b4b; }

        /* Long values: one size smaller ("compact") or two ("tight"), so the page still fits one sheet. */
        .density-compact .entry { padding: 1.1mm 0; font-size: 11pt; }
        .density-compact .entry .value { min-height: 5mm; }
        .density-compact .notes { margin-top: 2.5mm; }
        .density-compact .notes li { font-size: 8.8pt; line-height: 1.32; }
        .density-tight .entry { padding: 0.6mm 0; font-size: 10pt; line-height: 1.25; }
        .density-tight .entry .value { min-height: 4.5mm; }
        .density-tight .entry .value .alt { font-size: 8.5pt; }
        .density-tight h1 { font-size: 17pt; }
        .density-tight .body { margin-top: 3mm; }
        .density-tight .notes { margin-top: 2mm; }
        .density-tight .notes h2 { font-size: 10.5pt; }
        .density-tight .notes li { font-size: 8pt; line-height: 1.28; }
    </style>
</head>
@php
    $text = [
        'am' => [
            'number' => '№',
            'date' => 'ቀን',
            'code' => 'ኮድ',
            'title' => 'የተገደበ የከባድ ተሽከርካሪዎች መንቀሳቀሻ ፈቃድ',
            'entries' => [
                'organisation' => 'ፈቃዱን የጠየቀው ተቋም ስም',
                'owner' => 'የተሽከርካሪው ባለንብረት ስም',
                'time' => 'እንዲንቀሳቀስ የተፈቀደለት ጊዜ',
                'type' => 'የተሽከርካሪ ዓይነት',
                'plate' => 'የተሽከርካሪ ሰሌዳ ቁጥር',
                'reason' => 'ፈቃዱ የተሰጠበት ምክንያት',
                'valid' => 'ፈቃዱ የሚያገለግልበት ጊዜ',
                'receipt' => 'ክፍያ የተፈጸመበት ደረሰኝ ቁጥር',
                'issuer' => 'ፈቃድ ሰጪ ስምና ፊርማ',
            ],
            'from' => 'ከ',
            'until' => 'እስከ',
            'era' => 'ዓ.ም.',
            'colon' => '፦',
            'signature' => 'ፊርማ',
            'scan' => 'ለማረጋገጥ ስካን ያድርጉ',
            'stamp' => 'ክብ ማህተም',
            'notes' => 'ማሳሰቢያ',
            'footer' => "QR ኮዱን በ{$verify_url} ላይ ያረጋግጡ",
            'revoked' => 'የተሰረዘ',
            'revoked_on' => 'ይህ ፈቃድ በ:date ተሰርዟል፤ አያገለግልም።',
            'expired' => 'ጊዜው ያለፈ',
            'expired_on' => 'የዚህ ፈቃድ ጊዜ በ:date አብቅቷል፤ አያገለግልም።',
        ],
        'en' => [
            'number' => 'No.',
            'date' => 'Date',
            'code' => 'Code',
            'title' => 'Time-restricted movement permit for heavy vehicles',
            'entries' => [
                'organisation' => 'Organisation that requested the permit',
                'owner' => 'Vehicle owner',
                'time' => 'Permitted movement time',
                'type' => 'Vehicle type',
                'plate' => 'Plate number',
                'reason' => 'Reason for the permit',
                'valid' => 'Valid',
                'receipt' => 'Payment receipt number',
                'issuer' => 'Issued by (name and signature)',
            ],
            'from' => 'from',
            'until' => 'until',
            'era' => '',
            'colon' => ':',
            'signature' => 'Signature',
            'scan' => 'Scan to verify',
            'stamp' => 'Round stamp',
            'notes' => 'Notes',
            'footer' => "Verify the QR code at {$verify_url}",
            'revoked' => 'REVOKED',
            'revoked_on' => 'This permit was revoked on :date and is not valid.',
            'expired' => 'EXPIRED',
            'expired_on' => 'This permit expired on :date and is not valid.',
        ],
    ][$language];

    $owners = collect($vehicles)->pluck('owner')->filter()->unique()->implode(', ');
    $types = collect($vehicles)->pluck('type')->filter()->unique()->implode(', ');
    $plates = collect($vehicles)->map(fn (array $vehicle): string => count($vehicles) > 1
        ? "{$vehicle['plate']} ({$vehicle['role']})"
        : $vehicle['plate'])->implode(', ');

    // Security background, against copying and forgery: interlaced guilloche waves in the
    // logo's green, drawn once across the page (in millimetres, so it stays a small vector
    // drawing), and microtext naming the Authority and this permit's number, so a background
    // lifted from one permit cannot be reused on another.
    $guillochePaths = '';
    for ($row = 0; $row < 33; $row++) {
        foreach ([0, 0.5, 1, 1.5] as $phase) {
            $points = [];
            for ($x = 0.0; $x <= 282; $x += 1.5) {
                $points[] = $x.' '.round(3 + $row * 6 + 2.3 * sin(2 * M_PI * $x / 30 + $phase * M_PI), 2);
            }
            $guillochePaths .= 'M'.implode(' L', $points).' ';
        }
    }
    $microtext = str_repeat($authority_am.' · '.mb_strtoupper($authority_en).' · '.$permit->permit_number.' · ', 8);
@endphp
<body class="lang-{{ $language }} density-{{ $density }}">
<div class="sheet">
    <div class="border"></div>
    <div class="band top"></div>
    <div class="band bottom"></div>
    <div class="security" aria-hidden="true">
        <svg class="guilloche" viewBox="0 0 282 195" preserveAspectRatio="xMidYMid slice">
            <path d="{{ $guillochePaths }}" fill="none" stroke="#06aa07" stroke-width="0.18"/>
        </svg>
        <div class="microtext">
            @for ($row = 0; $row < 70; $row++)
                <div>{{ $microtext }}</div>
            @endfor
        </div>
    </div>
    @if ($watermark)
        <div class="watermark" style="background-image: url('{{ $watermark }}')"></div>
    @endif
    @if ($stamp)
        <div class="revoked-stamp{{ $stamp === 'expired' ? ' is-expired' : '' }}">{{ $text[$stamp] }}</div>
    @endif

    <div class="content">
        <header>
            <div class="masthead">
                @php($logo = \App\Domain\Settings\Branding::documentLogoPath())
                <div class="logo{{ $logo ? '' : ' placeholder' }}">
                    @if ($logo)
                        <img src="data:{{ mime_content_type($logo) }};base64,{{ base64_encode(file_get_contents($logo)) }}" alt="">
                    @else
                        LOGO<br>(placeholder)
                    @endif
                </div>
                <div class="authority">
                    <div class="am" lang="am">{{ $authority_am }}</div>
                    <div class="en" lang="en">{{ $authority_en }}</div>
                </div>
                <div class="ref">
                    <div>{{ $text['number'] }}: <span class="fill">{{ $permit->permit_number }}</span></div>
                    @if ($issued_at)
                        <div>{{ $text['date'] }}: <span class="fill">{{ $issued_at['main'] }} {{ $text['era'] }}</span></div>
                    @endif
                    @if ($certificate_code)
                        <div>{{ $text['code'] }}: <span class="fill code" translate="no">{{ $certificate_code }}</span></div>
                    @endif
                </div>
            </div>
            <div class="title">
                <h1>{{ $text['title'] }}</h1>
                @if ($stamp)
                    <div><span class="revoked-line{{ $stamp === 'expired' ? ' is-expired' : '' }}">{{ str_replace(':date', $stamp_date ? trim($stamp_date['main'].' '.$text['era']) : '', $text[$stamp.'_on']) }}</span></div>
                @endif
            </div>
        </header>

        <div class="body">
            <ol class="entries">
                <li class="entry">
                    <span class="n">1.</span>
                    <span class="what">{{ $text['entries']['organisation'] }}{{ $text['colon'] }}</span>
                    <span class="value">{{ $organization }}</span>
                </li>
                <li class="entry">
                    <span class="n">2.</span>
                    <span class="what">{{ $text['entries']['owner'] }}{{ $text['colon'] }}</span>
                    <span class="value">{{ $owners }}</span>
                </li>
                <li class="entry">
                    <span class="n">3.</span>
                    <span class="what">{{ $text['entries']['time'] }}{{ $text['colon'] }}</span>
                    <span class="value">{{ $type }}</span>
                </li>
                <li class="entry">
                    <span class="n">4.</span>
                    <span class="what">{{ $text['entries']['type'] }}{{ $text['colon'] }}</span>
                    <span class="value">{{ $types }}</span>
                </li>
                <li class="entry">
                    <span class="n">5.</span>
                    <span class="what">{{ $text['entries']['plate'] }}{{ $text['colon'] }}</span>
                    <span class="value">{{ $plates }}</span>
                </li>
                <li class="entry">
                    <span class="n">6.</span>
                    <span class="what">{{ $text['entries']['reason'] }}{{ $text['colon'] }}</span>
                    <span class="value">{{ $reason }}</span>
                </li>
                <li class="entry">
                    <span class="n">7.</span>
                    <span class="what">{{ $text['entries']['valid'] }}{{ $text['colon'] }}</span>
                    <span class="value">
                        {{ $text['from'] }} {{ $valid_from['main'] }} {{ $text['until'] }} {{ $valid_to['main'] }} {{ $text['era'] }}
                        @if ($valid_from['alt'] && $valid_to['alt'])
                            <span class="alt below">({{ $valid_from['alt'] }} – {{ $valid_to['alt'] }})</span>
                        @endif
                        @if ($hours)
                            <span class="hours"><strong>{{ __('permits.hours.title', [], $language) }}{{ $text['colon'] }}</strong> {{ $hours }}</span>
                        @endif
                    </span>
                </li>
                <li class="entry">
                    <span class="n">8.</span>
                    <span class="what">{{ $text['entries']['receipt'] }}{{ $text['colon'] }}</span>
                    <span class="value">{{ $receipt_number }}</span>
                </li>
                <li class="entry">
                    <span class="n">9.</span>
                    <span class="what">{{ $text['entries']['issuer'] }}{{ $text['colon'] }}</span>
                    <span class="value issuer">
                        <span class="name">{{ $issuer }}@if ($branch) <span class="alt">({{ $branch }})</span>@endif</span>
                        <span class="sign">{{ $text['signature'] }}{{ $text['colon'] }}<span class="line"></span></span>
                    </span>
                </li>
            </ol>

            <div class="side">
                @if ($qr)
                    <div class="qr">{!! $qr !!}</div>
                @else
                    <div class="qr-revoked{{ $stamp === 'expired' ? ' is-expired' : '' }}">{{ $text[$stamp ?? 'revoked'] }}</div>
                @endif
                @if ($qr)
                    <div class="caption">
                        <strong>{{ $text['scan'] }}</strong><br>
                        <span class="url">{{ $verify_url }}</span>
                    </div>
                @endif
                {{-- Where the Authority's round stamp goes, centred in the space left below. --}}
                <div class="stamp-spot">{{ $text['stamp'] }}</div>
            </div>
        </div>

        @if ($notes !== [])
            <section class="notes">
                <h2>{{ $text['notes'] }}{{ $text['colon'] }}</h2>
                <ul>
                    @foreach ($notes as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <footer>{{ $text['footer'] }}</footer>
    </div>
</div>
</body>
</html>
