<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\ProductResource;
use App\Models\Product;

class ProductController extends Controller
{
    public function index(Request $request) {
        $query = Product::query();

        //  Search by product name
        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }

        // Minimum price
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        // Maximum price
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Availability
        if ($request->filled('availability')) {
            if ($request->availability === 'in_stock') {
                $query->where('stock_quantity', '>', 0);
            } 

            if ($request->availability === 'out_of_stock') {
                $query->where('stock_quantity', '=', 0);
            }
        }

        //  sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $allowedSortColumns = [
            'name',
            'price',
            'stock_quantity',
            'created_at'
        ];
        
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortBy, $sortDirection);

        return ProductResource::collection(
            $query->paginate(10)->withQueryString()
        );
    }

    public function show(Product $product) {
        return new ProductResource($product);
    }
}
