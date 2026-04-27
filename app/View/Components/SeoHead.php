<?php

namespace App\View\Components;

use App\Models\Seo\SeoMeta;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class SeoHead extends Component
{
    public function __construct(
        public readonly ?SeoMeta $seoMeta = null,
        public readonly string $currentUrl = '',
        public readonly array $alternateUrls = [],
        public readonly string $fallbackTitle = '',
        public readonly string $fallbackDescription = '',
    ) {}

    public function render(): View
    {
        return view('components.seo.head');
    }
}
