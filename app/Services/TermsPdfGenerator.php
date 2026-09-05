<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class TermsPdfGenerator
{
    public function generate(string $content): string
    {
        // Dompdf necesita un public_path resoluble; en algunos hostings el path "public" no existe.
        // Ajustamos en runtime para evitar RuntimeException: "Cannot resolve public path".
        if (! is_dir(public_path())) {
            config(['dompdf.public_path' => base_path()]);
        } else {
            config(['dompdf.public_path' => public_path()]);
        }

        $pdf = Pdf::loadView('terms.pdf', ['content' => $content])
            ->setPaper('a4', 'portrait');

        $storagePath = storage_path('app/terms');
        if (!File::exists($storagePath)) {
            File::makeDirectory($storagePath, 0755, true);
        }

        $path = $storagePath . '/terminos_y_condiciones.pdf';
        File::put($path, $pdf->output());

        return $path;
    }
}
