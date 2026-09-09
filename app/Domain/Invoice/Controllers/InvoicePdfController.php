<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Controllers;

use App\Domain\Invoice\Actions\RenderInvoicePdfAction;
use App\Domain\Invoice\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class InvoicePdfController
{
    public function __invoke(Invoice $invoice, RenderInvoicePdfAction $renderPdf): Response|RedirectResponse
    {
        try {
            $pdf = $renderPdf->handle($invoice);
        } catch (Throwable $exception) {
            Log::error("Could not render the PDF of invoice #{$invoice->id}: {$exception->getMessage()}");

            return back()->with('toast', [
                'type' => 'error',
                'message' => 'The PDF could not be generated. Check that Node and Puppeteer are installed on the server.',
            ]);
        }

        $filename = Str::slug($invoice->number !== null ? "invoice-{$invoice->number}" : "draft-invoice-{$invoice->id}").'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
