<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Summary numbers for the admin and manager dashboards.
 */
class DashboardController extends Controller
{
    /**
     * Query params: days (7|30|90|365, default 30). "prev" values cover the period just before.
     */
    public function index(Request $request)
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90, 365], true) ? (int) $request->query('days') : 30;
        $from = now()->startOfDay()->subDays($days - 1);
        $prevFrom = $from->copy()->subDays($days);

        $period = fn () => Order::where('created_at', '>=', $from);
        $previous = fn () => Order::whereBetween('created_at', [$prevFrom, $from]);

        $revenue = (float) $period()->billable()->sum('total');
        $orders = $period()->count();
        $billableOrders = $period()->billable()->count();

        return response()->json([
            'days' => $days,
            'revenue' => $revenue,
            'revenue_prev' => (float) $previous()->billable()->sum('total'),
            'orders' => $orders,
            'orders_prev' => $previous()->count(),
            'avg_order_value' => $billableOrders ? round($revenue / $billableOrders, 2) : 0,
            'customers' => User::where('role', 'customer')->count(),
            'new_customers' => User::where('role', 'customer')->where('created_at', '>=', $from)->count(),
            'new_customers_prev' => User::where('role', 'customer')->whereBetween('created_at', [$prevFrom, $from])->count(),
            'products' => Product::count(),
            'low_stock_count' => Product::whereBetween('stock', [1, Product::LOW_STOCK])->count(),
            'out_of_stock_count' => Product::where('stock', 0)->count(),
            'inventory_value' => (float) Product::selectRaw('coalesce(sum(price * stock), 0) as v')->value('v'),
            // Open work, regardless of the selected period.
            'pending_orders' => Order::where('status', 'pending')->count(),
            'processing_orders' => Order::where('status', 'processing')->count(),
            'unpaid_orders' => Order::billable()->where('payment_status', 'unpaid')->count(),
            'status_counts' => $period()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'payment_methods' => $period()->billable()->selectRaw('payment_method, count(*) as orders, sum(total) as revenue')
                ->groupBy('payment_method')->get(),
            'sales' => $this->dailySales($from, $days),
            'top_products' => $this->topProducts($from),
            'category_sales' => $this->categorySales($from),
            'recent_orders' => Order::with('user:id,name,email')->withCount('items')->latest()->orderByDesc('id')->limit(6)->get(),
            'low_stock' => Product::with('category:id,name')->where('stock', '<=', Product::LOW_STOCK)
                ->orderBy('stock')->orderBy('name')->limit(8)->get(['id', 'name', 'sku', 'stock', 'image', 'category_id']),
        ]);
    }

    /**
     * One row per day, including days without orders.
     */
    private function dailySales(Carbon $from, int $days): array
    {
        $rows = Order::billable()->where('created_at', '>=', $from)
            ->selectRaw('date(created_at) as day, count(*) as orders, sum(total) as revenue')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        return collect(range(0, $days - 1))->map(function ($i) use ($from, $rows) {
            $day = $from->copy()->addDays($i)->toDateString();

            return [
                'date' => $day,
                'orders' => (int) ($rows[$day]->orders ?? 0),
                'revenue' => (float) ($rows[$day]->revenue ?? 0),
            ];
        })->all();
    }

    private function topProducts(Carbon $from)
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.status', '!=', 'cancelled')
            ->where('orders.created_at', '>=', $from)
            ->selectRaw('order_items.product_id, order_items.product_name as name, products.image, products.stock,
                sum(order_items.qty) as qty, sum(order_items.qty * order_items.price) as revenue')
            ->groupBy('order_items.product_id', 'order_items.product_name', 'products.image', 'products.stock')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();
    }

    private function categorySales(Carbon $from)
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.status', '!=', 'cancelled')
            ->where('orders.created_at', '>=', $from)
            ->selectRaw("coalesce(categories.name, 'Uncategorized') as name, sum(order_items.qty * order_items.price) as revenue")
            ->groupBy('categories.name')
            ->orderByDesc('revenue')
            ->get();
    }
}
