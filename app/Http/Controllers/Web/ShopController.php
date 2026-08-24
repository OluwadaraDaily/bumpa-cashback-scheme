<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class ShopController extends Controller
{
    public function index(): View
    {
        return view('shop.index', [
            'products' => Product::query()->orderBy('id')->get(),
        ]);
    }
}
