<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Placeholder until the feature is built out. */
class InvoiceController
{
    public function index(Request $request): Response
    {
        return Inertia::render('invoices/InvoiceList', ['items' => null]);
    }
}
