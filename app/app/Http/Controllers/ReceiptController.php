<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Order receipt (nota) for buyers, the assigned sales user and admin — each on
 * their own portal. Plain black-on-white monospace so it prints on any thermal
 * printer; the print page takes its paper size from the device (@page auto).
 */
class ReceiptController
{
    /** Width of the downloadable PDF: an 80mm thermal roll, in points. */
    private const PDF_WIDTH_PT = 226.77;

    public function show(Request $request, Order $order): View
    {
        $this->authorizeReceipt($request, $order);

        return view('receipts.show', $this->data($request, $order) + ['pdf' => false]);
    }

    public function pdf(Request $request, Order $order): Response
    {
        $this->authorizeReceipt($request, $order);
        $data = $this->data($request, $order) + ['pdf' => true];

        return Pdf::loadView('receipts.show', $data)
            ->setPaper([0, 0, self::PDF_WIDTH_PT, $this->pdfHeight($data)])
            ->download($order->receiptNumber().'.pdf');
    }

    /**
     * A thermal roll is one page as tall as its content. Lay the receipt out
     * once on a very tall page and measure where the paper ends; names and
     * addresses wrap, so counting lines would guess wrong.
     *
     * @param  array<string, mixed>  $data
     */
    private function pdfHeight(array $data): float
    {
        $probe = Pdf::loadView('receipts.show', $data)->setPaper([0, 0, self::PDF_WIDTH_PT, 5000])->getDomPDF();
        $probe->render();

        foreach ($probe->getTree() as $frame) {
            if ($frame->get_node()->nodeName === 'main') {
                return $frame->get_position('y') + $frame->get_margin_height() + 8;
            }
        }

        return 600; // not reached: the view always has <main>
    }

    /** Own order for a buyer, assigned order for sales, any order for admin — and only once it is finished. */
    private function authorizeReceipt(Request $request, Order $order): void
    {
        $user = $request->user();
        $allowed = $user->isAdmin()
            || ($user->isSales() && $order->sales_id === $user->id)
            || ($user->isUser() && $order->buyer_id === $user->id);

        abort_unless($allowed && $order->isReceiptReady(), 404);
    }

    /** @return array<string, mixed> */
    private function data(Request $request, Order $order): array
    {
        $order->load('items', 'buyer', 'salesUser');
        $base = $request->url();

        return [
            'order' => $order,
            'pdfUrl' => $base.'.pdf',
            'backUrl' => preg_replace('#/nota$#', '', $base),
            // Sales can't print receipts yet (product decision 2026-10-05); they view and download.
            'canPrint' => ! $request->user()->isSales(),
        ];
    }
}
