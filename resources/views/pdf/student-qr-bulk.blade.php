<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cetak QR Code Siswa</title>
    <style>
        @page {
            margin: 1cm;
        }
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            text-align: center;
        }
        .title {
            width: 100%;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 30px;
            clear: both;
            display: block;
        }
        
        .container::after {
            content: "";
            clear: both;
            display: table;
        }

        /* Table Grid System */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px; /* Jarak antar kotak */
        }
        td.card {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            vertical-align: top;
            box-sizing: border-box;
        }

        /* Size: 20 per page (5 cols) */
        .size-20 td.card { height: 140px; padding: 5px; }
        .size-20 .qr-img { width: 70px; height: 70px; margin-bottom: 2px; }
        .size-20 .name { font-size: 8px; margin-top: 3px; font-weight: bold; text-transform: uppercase; }
        .size-20 .nisn { font-size: 7px; color: #444; margin-top: 1px; }

        /* Size: 12 per page (3 cols) */
        .size-12 td.card { height: 230px; padding: 15px; }
        .size-12 .qr-img { width: 120px; height: 120px; margin-bottom: 8px; }
        .size-12 .name { font-size: 12px; margin-top: 5px; font-weight: bold; text-transform: uppercase; }
        .size-12 .nisn { font-size: 10px; color: #444; }

        /* Size: 6 per page (2 cols) */
        .size-6 td.card { height: 340px; padding: 20px; }
        .size-6 .qr-img { width: 180px; height: 180px; margin-bottom: 12px; }
        .size-6 .name { font-size: 16px; margin-top: 10px; font-weight: bold; text-transform: uppercase; }
        .size-6 .nisn { font-size: 14px; color: #444; }

    </style>
</head>
<body>
    @php
        use chillerlan\QRCode\QRCode;
        use chillerlan\QRCode\QROptions;

        $options = new QROptions([
            'outputType' => \chillerlan\QRCode\Output\QRMarkupSVG::class,
            'scale' => 5,
            'imageBase64' => true,
        ]);
        $qrMaker = new QRCode($options);
    @endphp

    <div class="title">QR Code Siswa</div>
    <div class="container size-{{ $size }}">
        @php
            if ($size == 20) $cols = 5;
            elseif ($size == 12) $cols = 3;
            else $cols = 2;
        @endphp
        <table>
            <tr>
            @foreach($students as $index => $student)
                @if($index > 0 && $index % $cols == 0)
                    </tr><tr>
                @endif
                @php
                    $qrData = $student->qr_code ?? $student->nisn;
                    $qrBase64 = $qrMaker->render($qrData); 
                @endphp
                <td class="card" style="width: {{ 100 / $cols }}%;">
                    <div>
                        <img src="{{ $qrBase64 }}" class="qr-img" alt="QR Code">
                    </div>
                    <div class="name">{{ $student->name }}</div>
                    <div class="nisn">NISN: {{ $student->nisn }}</div>
                    <div class="nisn">Kelas: {{ $student->schoolClass->name ?? '-' }}</div>
                </td>
            @endforeach
            
            {{-- Mengisi sisa sel kosong di baris terakhir agar tabel tidak rusak --}}
            @php $remaining = count($students) % $cols; @endphp
            @if($remaining > 0)
                @for($i = $remaining; $i < $cols; $i++)
                    <td style="border: none; background: transparent;"></td>
                @endfor
            @endif
            </tr>
        </table>
    </div>
</body>
</html>
