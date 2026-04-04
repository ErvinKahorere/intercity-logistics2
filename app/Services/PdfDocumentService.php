<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class PdfDocumentService
{
    public function download(string $view, array $data, string $filename): Response
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4')
            ->download($filename);
    }
}
