<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class HomeController extends Controller
{
    // ML-16: implement full SSR logic
    public function index(string $locale): Response
    {
        return response('Home — ' . strtoupper($locale), 200);
    }
}
