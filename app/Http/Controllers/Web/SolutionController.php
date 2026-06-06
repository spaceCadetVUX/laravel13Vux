<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class SolutionController extends Controller
{
    public function dali(string $locale): View
    {
        view()->share('alternateUrls', [
            'vi' => route('vi.dali-casambi'),
            'en' => route('en.dali-casambi'),
        ]);

        return view('pages.solutions.dali', compact('locale'));
    }

    public function wireless(string $locale): View
    {
        view()->share('alternateUrls', [
            'vi' => route('vi.wireless-casambi'),
            'en' => route('en.wireless-casambi'),
        ]);

        return view('pages.solutions.wireless', compact('locale'));
    }

    public function byRole(string $locale): View
    {
        view()->share('alternateUrls', [
            'vi' => route('vi.solutions-by-role'),
            'en' => route('en.solutions-by-role'),
        ]);

        return view('pages.solutions.by-role', compact('locale'));
    }
}
