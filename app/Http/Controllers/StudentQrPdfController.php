<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentQrPdfController extends Controller
{
    public function download(Request $request, Student $student): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user?->isStaff() || $user?->isKepsek(), 403);

        $pdf = app('dompdf.wrapper')
            ->loadView('pdf.student-qr-single', compact('student'))
            ->setPaper('a4', 'portrait');

        $studentName = Str::slug($student->name, '_');
        $filename = "QR_{$studentName}_{$student->nisn}.pdf";

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
