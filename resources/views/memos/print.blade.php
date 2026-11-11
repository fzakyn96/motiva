<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Memo - {{ $memo->nomor_memo }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #000;
        }
        .header {
            width: 100%;
            /* border-bottom: 2px solid #000; */
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .header td {
            vertical-align: middle;
        }
        .energy {
            font-style: italic;
            font-size: 20px;
            font-weight: normal;
        }
        .logo {
            text-align: right;
        }
        .logo img {
            height: 48px;
            width: auto;
        }
        .title {
            font-size: 24px;
            color: #555;
            margin-bottom: 30px;
            letter-spacing: 2px;
        }
        .content {
            margin-bottom: 40px;
        }
        .memo-body, .memo-body p {
            text-align: justify;
            text-justify: inter-word;
        }
        .closing-text {
            margin-top: 30px;
            text-align: justify;
            text-justify: inter-word;
        }
        .signature {
            width: 380px;
        }
        .signature-title {
            font-weight: bold;
            margin-bottom: 8px;
        }
        .signature-name {
            font-weight: bold;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <table class="header">
        <tr>
            <td class="energy">Energy for Life</td>
            <td class="logo">
                @php
                    $logoPath = public_path('images/logo-pertamina-gas-negara.png');
                    $logoBase64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
                @endphp
                @if($logoBase64)
                    <img src="data:image/png;base64,{{ $logoBase64 }}" alt="Pertamina Gas Negara" style="height: 48px; width: auto;">
                @else
                    <span style="font-weight: bold; font-size: 22px;">PERTAMINA<br>GAS NEGARA</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="title">MEMO</div>

    <div class="content">
        <p>Jakarta, {{ \Carbon\Carbon::parse($memo->date)->locale('id')->translatedFormat('d F Y') }}</p>

        <p>
            Yang terhormat,<br>
            <strong>{{ $memo->to_recipient }}</strong>
        </p>

        <!-- @if($memo->agenda || $memo->title)
            <p><strong>Perihal: {{ $memo->agenda ?? $memo->title }}</strong></p>
        @endif -->

        <div class="memo-body">
            {!! $memo->content !!}
        </div>

        @if($memo->assignment_start_date || $memo->agenda || $memo->location || $memo->participants)
            <table style="width: 100%; margin-top: 20px; border-collapse: collapse;">
                @if($memo->assignment_start_date)
                @php
                    $start = \Carbon\Carbon::parse($memo->assignment_start_date)->locale('id');
                    $end = $memo->assignment_end_date ? \Carbon\Carbon::parse($memo->assignment_end_date)->locale('id') : null;

                    if (! $end || $start->isSameDay($end)) {
                        $formattedDate = $start->translatedFormat('l / d F Y');
                    } else {
                        $dayNames = $start->translatedFormat('l') . ' - ' . $end->translatedFormat('l');
                        if ($start->isSameMonth($end) && $start->isSameYear($end)) {
                            $dateRange = $start->format('d') . ' - ' . $end->translatedFormat('d F Y');
                        } elseif ($start->isSameYear($end)) {
                            $dateRange = $start->translatedFormat('d F') . ' - ' . $end->translatedFormat('d F Y');
                        } else {
                            $dateRange = $start->translatedFormat('d F Y') . ' - ' . $end->translatedFormat('d F Y');
                        }
                        $formattedDate = $dayNames . ' / ' . $dateRange;
                    }
                @endphp
                <tr>
                    <td style="width: 120px; vertical-align: top;">Tanggal</td>
                    <td style="width: 20px; vertical-align: top;">:</td>
                    <td>{{ $formattedDate }}</td>
                </tr>
                @endif
                @if($memo->agenda)
                <tr>
                    <td style="vertical-align: top;">Agenda</td>
                    <td style="vertical-align: top;">:</td>
                    <td>{{ $memo->agenda }}</td>
                </tr>
                @endif
                @if($memo->location)
                <tr>
                    <td style="vertical-align: top;">Tempat</td>
                    <td style="vertical-align: top;">:</td>
                    <td>{{ $memo->location }}</td>
                </tr>
                @endif
                @if($memo->participants && count($memo->participants) > 0)
                <tr>
                    <td style="vertical-align: top;">Peserta</td>
                    <td style="vertical-align: top;">:</td>
                    <td>
                        <ol style="margin-top: 0; padding-left: 20px;">
                            @foreach($memo->participants as $participant)
                                @php
                                    $emp = is_numeric($participant) ? \App\Models\Employee::with('position')->find($participant) : null;
                                    $displayName = $emp ? "{$emp->name} - " . ($emp->position?->name ?? 'Pegawai') : $participant;
                                @endphp
                                <li>{{ $displayName }}</li>
                            @endforeach
                        </ol>
                    </td>
                </tr>
                @endif
            </table>
        @endif

        <p class="closing-text">Demikian atas perhatian dan kerjasamanya disampaikan, terima kasih.</p>
    </div>

    @php
        $signerTitle = $manager?->position?->name ?? ($managerTitle ?? 'Manajer');
        $signerName = $manager?->name ?? 'Nama Manajer';
        $signerNip = $manager?->identity_number ?? null;
        $signatureRender = $signatureRender ?? app(\App\Services\Signature\SignatureService::class)->resolveRenderData(
            $memo,
            $manager ?? null,
            'manager',
            $memo->status === 'approved'
        );
    @endphp

    <div class="signature">
        <div class="signature-title">{{ $signerTitle }}</div>
        @include('signatures.pdf-stamp', ['render' => $signatureRender, 'align' => 'left', 'emptyHeight' => '55px'])
        <div class="signature-name">{{ $signerName }}</div>
        <!-- @if($signerNip)
            <div style="font-size: 11px; color: #555; margin-top: 2px;">NIP. {{ $signerNip }}</div>
        @endif -->
    </div>

</body>
</html>
