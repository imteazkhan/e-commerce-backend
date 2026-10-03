<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Order management for admins and managers. Payment status is admin-only.
 */
class OrderController extends Controller
{
    private const DETAIL = ['items.product:id,image,sku,stock', 'user:id,name,email,created_at'];

    /**
     * Query params: status, payment_status, search (id/name/phone/email), from, to (Y-m-d), per_page.
     */
    public function index(Request $request)
    {
        $orders = $this->filtered($request)
            ->with('user:id,name,email')
            ->withCount('items')
            ->latest()
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        // Per-status totals for the filter tabs, ignoring the status filter itself.
        $counts = $this->filtered($request, withStatus: false)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([...$orders->toArray(), 'counts' => $counts]);
    }

    public function show(Order $order)
    {
        return response()->json($order->load(self::DETAIL));
    }

    public function update(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(Order::STATUSES)],
            'payment_status' => ['sometimes', Rule::in(Order::PAYMENT_STATUSES)],
            'admin_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('payment_status', $data) && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can change the payment status.'], 403);
        }

        DB::transaction(function () use ($order, $data) {
            if (isset($data['status'])) {
                $this->syncStock($order, $data['status']);
            }
            $order->update($data);
        });

        return response()->json($order->load(self::DETAIL));
    }

    public function export(Request $request)
    {
        $orders = $this->filtered($request)->with('user:id,email')->withCount('items')->latest()->get();

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order', 'Date', 'Customer', 'Email', 'Phone', 'Address', 'Items', 'Total', 'Payment', 'Payment status', 'Status']);
            foreach ($orders as $o) {
                fputcsv($out, [
                    $o->id, $o->created_at->format('Y-m-d H:i'), $o->name, $o->user?->email, $o->phone,
                    $o->address, $o->items_count, $o->total, $o->payment_method, $o->payment_status, $o->status,
                ]);
            }
            fclose($out);
        }, 'orders-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $request, bool $withStatus = true)
    {
        $query = Order::query();

        if ($withStatus && ($status = $request->query('status'))) {
            $query->where('status', $status);
        }

        if ($payment = $request->query('payment_status')) {
            $query->where('payment_status', $payment);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"));
                if (ctype_digit(ltrim($search, '#'))) {
                    $q->orWhere('id', (int) ltrim($search, '#'));
                }
            });
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Cancelling an order puts its items back in stock; reopening it takes them out again.
     */
    private function syncStock(Order $order, string $newStatus): void
    {
        $wasCancelled = $order->status === 'cancelled';
        $isCancelled = $newStatus === 'cancelled';

        if ($wasCancelled === $isCancelled) {
            return;
        }

        $items = $order->items()->whereNotNull('product_id')->get();
        $products = Product::whereIn('id', $items->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');

        foreach ($items as $item) {
            $product = $products[$item->product_id] ?? null;
            if (! $product) {
                continue;
            }

            if ($isCancelled) {
                $product->increment('stock', $item->qty);
            } elseif ($product->stock < $item->qty) {
                throw ValidationException::withMessages([
                    'status' => ["Not enough stock to reopen this order ({$product->name})."],
                ]);
            } else {
                $product->decrement('stock', $item->qty);
            }
        }
    }
}
