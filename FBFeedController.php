<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use Illuminate\Http\Response;
use App\Models\Product;
use App\FrontProduct;
use App\WarehouseProduct;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Brand;

class FBFeedController extends Controller
{
    public function feed(): StreamedResponse
    {
        $domain_url = env('APP_BACKEND_URL');
        $frontProduct = FrontProduct::where('domain_name', $domain_url)->first();
        $products = WarehouseProduct::where('warehouse_products.domain_name', $domain_url)
            ->join('orders_products', 'warehouse_products.product_id', '=', 'orders_products.product_id')
            ->select(
                'warehouse_products.*',
                DB::raw('sum(orders_products.product_quantity) as total'),
                DB::raw("min(selling_price) AS minPrice, max(market_price) AS maxMarketPrice")
            )
            ->groupBy('warehouse_products.product_id', 'warehouse_products.product_title')
            ->orderBy('total', 'DESC')
            ->where('warehouse_products.status', '!=', 0)
            ->with('product')
            ->with('images')
            ->take(10)
            ->get();
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="datafeed.csv"',
        ];
        $columns = [
            'id',
            'title',
            'description',
            'availability',
            'condition',
            'price',
            'link',
            'image_link',
            'brand'
        ];
        $callback = function () use ($products, $columns) {
        $file = fopen('php://output', 'w');
        fputcsv($file, $columns);
        foreach ($products as $p) {
            $product = $p->product;
            $title = $product->title ?? $p->product_title;
            $description = strip_tags($product->description ?? '');
            $availability = ($p->stock > 0) ? "in stock" : "out of stock";
            $price = number_format($p->selling_price, 2) . " BDT";
            $productUrl = url('/product/' . $product->slug);
            $image = $p->images->first();
            $imageUrl = $image ? url($image->image) : '';
            $brandName = Brand::find($product->brand_id)->name ?? 'Unknown';
                fputcsv($file, [
                    $p->id,
                    $title,
                    $description,
                    $availability,
                    "new",               
                    $price,
                    $productUrl,
                    $imageUrl,
                    $brandName
                ]);
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

}
