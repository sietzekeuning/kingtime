<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Actions;

use App\Domain\Invoice\Models\Invoice;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Browsershot\Browsershot;

/**
 * Renders the invoice as an A4 PDF: the sender's business details, the
 * client, the lines and totals, and the hour specification per project.
 * Headless Chrome does the layout (see config/browsershot.php).
 */
class RenderInvoicePdfAction
{
    /**
     * @return string The PDF file contents.
     */
    public function handle(Invoice $invoice): string
    {
        return $this->browsershot($this->html($invoice))->pdf();
    }

    public function html(Invoice $invoice): string
    {
        $invoice->load([
            'user',
            'client',
            'lines.project',
            'timeEntries' => fn (HasMany $query) => $query->with('project')->orderBy('spent_on')->orderBy('id'),
        ]);

        return view('invoices.pdf', ['invoice' => $invoice])->render();
    }

    private function browsershot(string $html): Browsershot
    {
        // Regular Chrome in headless mode, so only the `chrome` build that
        // `npm install puppeteer` fetches is needed (no chrome-headless-shell).
        $browsershot = Browsershot::html($html)
            ->newHeadless()
            ->format('A4')
            ->margins(16, 16, 18, 16)
            ->showBackground()
            ->noSandbox()
            ->timeout((int) config('browsershot.timeout_seconds'));

        $nodeBinary = config('browsershot.node_binary');
        $npmBinary = config('browsershot.npm_binary');
        $nodeModulePath = config('browsershot.node_module_path');
        $chromePath = config('browsershot.chrome_path');

        if (is_string($nodeBinary) && $nodeBinary !== '') {
            $browsershot->setNodeBinary($nodeBinary);
        }

        if (is_string($npmBinary) && $npmBinary !== '') {
            $browsershot->setNpmBinary($npmBinary);
        }

        if (is_string($nodeModulePath) && $nodeModulePath !== '') {
            $browsershot->setNodeModulePath($nodeModulePath);
        }

        if (is_string($chromePath) && $chromePath !== '') {
            $browsershot->setChromePath($chromePath);
        }

        return $browsershot;
    }
}
