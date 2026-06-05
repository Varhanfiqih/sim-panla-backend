<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>QR Code {{ $student->name }}</title>
    <style>
        @page {
            margin: 20mm;
        }

        body {
            margin: 0;
            color: #000;
            font-family: DejaVu Sans, sans-serif;
            text-align: center;
        }

        .content {
            padding-top: 35mm;
        }

        .qr-code {
            display: block;
            width: 280px;
            height: 280px;
            margin: 0 auto 18px;
        }

        .student-name {
            margin: 0 0 8px;
            font-size: 22px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .student-nisn {
            margin: 0;
            font-size: 16px;
        }
    </style>
</head>
<body>
    @php
        $options = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\Output\QRMarkupSVG::class,
            'scale' => 8,
            'imageBase64' => true,
        ]);
        $qrCode = (new \chillerlan\QRCode\QRCode($options))
            ->render($student->qr_code ?? $student->nisn);
    @endphp

    <div class="content">
        <img class="qr-code" src="{{ $qrCode }}" alt="QR Code">
        <p class="student-name">{{ $student->name }}</p>
        <p class="student-nisn">NISN: {{ $student->nisn }}</p>
    </div>
</body>
</html>
