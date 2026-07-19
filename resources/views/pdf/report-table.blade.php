<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 18mm 18mm 16mm;
        }

        body {
            margin: 0;
            color: #000;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.35;
        }

        .header {
            width: 100%;
            margin-bottom: 18px;
            border-collapse: collapse;
        }

        .header-logo {
            width: 78px;
            vertical-align: top;
        }

        .header-logo img {
            width: 58px;
            height: 58px;
            object-fit: contain;
        }

        .school-name,
        .report-title {
            margin: 0;
            font-size: 19px;
            font-weight: 800;
            line-height: 1.3;
            text-transform: uppercase;
        }

        .report-subtitle {
            margin: 3px 0 0;
            font-size: 14px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.report thead {
            display: table-header-group;
        }

        table.report th,
        table.report td {
            border: 1px solid #c8c8c8;
            padding: 8px 8px;
            vertical-align: top;
            word-wrap: break-word;
        }

        table.report th {
            background: #f3f4f6;
            font-size: 11px;
            font-weight: 800;
            text-align: left;
            vertical-align: middle;
        }

        .align-center {
            text-align: center;
        }

        .subject {
            font-weight: 800;
        }

        .empty {
            text-transform: uppercase;
        }

        .signatures {
            width: 100%;
            margin-top: 34px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .signatures td {
            width: 50%;
            padding: 0 36px;
            vertical-align: top;
        }

        .signature-space {
            height: 66px;
        }

        .signature-name {
            font-weight: 800;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="header-logo">
                @if ($logoDataUri)
                    <img src="{{ $logoDataUri }}" alt="Logo">
                @endif
            </td>
            <td>
                <p class="school-name">{{ $meta['school_name'] ?? 'UPT SMP NEGERI 8 PASURUAN' }}</p>
                <p class="report-title">{{ $title }}</p>
                <p class="report-subtitle">{{ $meta['subtitle'] ?? 'Rekapitulasi Laporan' }}</p>
            </td>
        </tr>
    </table>

    <table class="report">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th class="{{ ($column['align'] ?? '') === 'center' ? 'align-center' : '' }}" style="width: {{ $column['width'] ?? 'auto' }}">
                        {!! nl2br(e($column['label'])) !!}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $index => $cell)
                        @php
                            $column = $columns[$index] ?? [];
                            $align = ($column['align'] ?? '') === 'center' ? 'align-center' : '';
                        @endphp
                        <td class="{{ $align }}">
                            @if (is_array($cell))
                                @if (($cell['type'] ?? null) === 'subject')
                                    <span class="subject">{{ $cell['title'] ?? '-' }}</span><br>
                                    {!! nl2br(e($cell['description'] ?? '-')) !!}
                                @else
                                    {!! nl2br(e(implode("\n", $cell))) !!}
                                @endif
                            @else
                                <span class="{{ strtoupper((string) $cell) === 'NIHIL' ? 'empty' : '' }}">
                                    {!! nl2br(e((string) $cell)) !!}
                                </span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" class="align-center">Tidak ada data laporan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if (! empty($meta['signatures']))
        <table class="signatures">
            <tr>
                <td>
                    <div>Mengetahui</div>
                    <div>Kepala Sekolah,</div>
                    <div class="signature-space"></div>
                    <div class="signature-name">{{ $meta['signatures']['principal_name'] ?? '-' }}</div>
                    <div>NIP. {{ $meta['signatures']['principal_nip'] ?? '-' }}</div>
                </td>
                <td>
                    <div>{{ $meta['signatures']['place_date'] ?? '' }}</div>
                    <div>Operator Sekolah,</div>
                    <div class="signature-space"></div>
                    <div class="signature-name">{{ $meta['signatures']['teacher_name'] ?? '-' }}</div>
                    <div>NIP. {{ $meta['signatures']['teacher_nip'] ?? '-' }}</div>
                </td>
            </tr>
        </table>
    @endif
</body>
</html>
