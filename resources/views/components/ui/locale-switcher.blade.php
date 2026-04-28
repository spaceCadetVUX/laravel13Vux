@props(['alternateUrls' => []])
<div class="locale-switcher">
    @foreach(config('app.supported_locales') as $locale)
        @if($locale !== app()->getLocale())
            <a href="{{ $alternateUrls[$locale] ?? route('home', ['locale' => $locale]) }}"
               lang="{{ $locale }}">
                {{ strtoupper($locale) }}
            </a>
        @else
            <span class="active" lang="{{ $locale }}">{{ strtoupper($locale) }}</span>
        @endif
    @endforeach
</div>
