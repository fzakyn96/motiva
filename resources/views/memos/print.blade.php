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
        <p>Jakarta, {{ \Carbon\Carbon::parse($memo->date)->translatedFormat('d F Y') }}</p>

        <p>
            Yang terhormat,<br>
            <strong>{{ $memo->to_recipient }}</strong>
        </p>

        @if($memo->title)
            <p><strong>Perihal: {{ $memo->title }}</strong></p>
        @endif

        <div>
            {!! $memo->content !!}
        </div>

        <p>Demikian atas perhatian dan kerjasamanya disampaikan, terima kasih</p>
    </div>

    <div class="signature">
        <div class="signature-title">{{ $memo->creator->position->name ?? 'Jabatan' }}</div>
        <!-- Placeholder for signature image -->
        <br><br><br>
        <div class="signature-name">{{ $memo->creator->name ?? 'Nama Penandatangan' }}</div>
    </div>

</body>
</html>
