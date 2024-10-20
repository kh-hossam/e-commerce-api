<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductService
{
    public function getAllProducts($filters)
    {
        // Check if name or price filter is applied, Skip cache and query the database directly
        if (!empty($filters['name']) || !empty($filters['price_min']) || !empty($filters['price_max'])) {

            return Product::when($filters['name'] ?? null, function ($query, $name) {
                return $query->where('name', 'like', "%$name%");
            })
            ->when($filters['price_min'] ?? null, function ($query, $price_min) {
                return $query->where('price', '>=', $price_min);
            })
            ->when($filters['price_max'] ?? null, function ($query, $price_max) {
                return $query->where('price', '<=', $price_max);
            })
            ->with('category')
            ->latest()
            ->paginate(config('app.pagination'));
        }

        $cacheKey = 'products_listing_' . ($filters['page'] ?? 1);

        return Cache::remember($cacheKey, config('app.cache_time'), function() {
            return Product::with('category')->latest()->paginate(config('app.pagination'));
        });
    }

}
