<?php

namespace App\Services;

use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class PdfReportService
{
    /**
     * @param  array<int, array{label: string, width?: string, align?: string}>  $columns
     * @param  iterable<int, array<int, mixed>>  $rows
     * @param  array<string, mixed>  $meta
     */
    public function create(string $title, array $columns, iterable $rows, array $meta = []): PDF
    {
        return app('dompdf.wrapper')
            ->loadView('pdf.report-table', [
                'title' => $title,
                'columns' => $columns,
                'rows' => $rows instanceof Collection ? $rows : collect($rows),
                'meta' => $meta,
                'logoDataUri' => $this->logoDataUri(),
            ])
            ->setPaper('a4', 'portrait');
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('logo.png');

        if (! File::exists($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(File::get($path));
    }
}
