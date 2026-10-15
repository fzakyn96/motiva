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
            border-bottom: 2px solid #000;
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
            font-weight: bold;
            font-size: 24px;
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
        .signature {
            width: 300px;
        }
        .signature-title {
            font-weight: bold;
            margin-bottom: 60px;
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
            <td class="logo">PERTAMINA<br>GAS NEGARA</td>
        </tr>
    </table>

    <div class="title">MEMO</div>

    <div class="content">
        <p>Jakarta, {{ \Carbon\Carbon::parse($memo->date)->locale('id')->translatedFormat('d F Y') }}</p>

        <p>
            Yang terhormat,<br>
            <strong>{{ $memo->to_recipient }}</strong>
        </p>

        @if($memo->agenda || $memo->title)
            <p><strong>Perihal: {{ $memo->agenda ?? $memo->title }}</strong></p>
        @endif

        <div>
            {!! $memo->content !!}
        </div>

        @if($memo->assignment_start_date || $memo->agenda || $memo->location || $memo->participants)
            <table style="width: 100%; margin-top: 20px; border-collapse: collapse;">
                @if($memo->assignment_start_date)
                <tr>
                    <td style="width: 120px; vertical-align: top;">Tanggal</td>
                    <td style="width: 20px; vertical-align: top;">:</td>
                    <td>
                        {{ \Carbon\Carbon::parse($memo->assignment_start_date)->locale('id')->translatedFormat('l / d F Y') }}
                        @if($memo->assignment_end_date && $memo->assignment_start_date !== $memo->assignment_end_date)
                            - {{ \Carbon\Carbon::parse($memo->assignment_end_date)->locale('id')->translatedFormat('l / d F Y') }}
                        @endif
                    </td>
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

        <p style="margin-top: 30px;">Demikian atas perhatian dan kerjasamanya disampaikan, terima kasih.</p>
    </div>

    <div class="signature">
        <div class="signature-title">{{ $memo->creator->position->name ?? 'Jabatan' }}</div>
        <!-- Placeholder for signature image -->
        <br><br><br>
        <div class="signature-name">{{ $memo->creator->name ?? 'Nama Penandatangan' }}</div>
    </div>

</body>
</html>
