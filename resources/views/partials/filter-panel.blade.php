{{--
    Filter panel partial — shared between desktop sidebar and mobile overlay.
    Variables inherited from parent view: $filterGroups, $activeValueSlugs, $brands, $brandSlug, $locale
    Extra params: $idPrefix (string), $showActions (bool, default true), $applyBtnId, $clearBtnId
--}}
@foreach($filterGroups as $loop_group => $group)
@php
    $activeSlugsForGroup = $activeValueSlugs[$group->slug] ?? [];
    $hasActive           = count($activeSlugsForGroup) > 0;
    $groupLabel          = ($locale !== 'vi' && $group->name_en) ? $group->name_en : $group->name;
    $collapseId          = ($idPrefix ?? '') . 'fg-collapse-' . $group->id;
    $isOpen              = $hasActive || $loop_group < 2;
@endphp
@if($group->activeValues->count())
<div class="filter-group mb-1">
    <button class="filter-group-toggle w-100 d-flex justify-content-between align-items-center"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#{{ $collapseId }}"
            aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
            aria-controls="{{ $collapseId }}">
        <span class="font-xs fw-bold text-uppercase letter-wide text-muted">{{ $groupLabel }}</span>
        <svg class="filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <div class="collapse {{ $isOpen ? 'show' : '' }} pt-2 pb-3" id="{{ $collapseId }}">
        @foreach($group->activeValues as $value)
        @php
            $isChecked  = in_array($value->slug, $activeSlugsForGroup);
            $valueLabel = ($locale !== 'vi' && $value->name_en) ? $value->name_en : $value->name;
            $checkId    = ($idPrefix ?? '') . 'fv-' . $value->id;
        @endphp
        <div class="form-check filter-check">
            <input class="form-check-input filter-checkbox"
                   type="checkbox"
                   id="{{ $checkId }}"
                   data-group-slug="{{ $group->slug }}"
                   data-value-slug="{{ $value->slug }}"
                   {{ $isChecked ? 'checked' : '' }}>
            <label class="form-check-label" for="{{ $checkId }}">{{ $valueLabel }}</label>
        </div>
        @endforeach
    </div>
</div>
@endif
@endforeach

@if($brands->count())
<div class="filter-group mb-1">
    <button class="filter-group-toggle w-100 d-flex justify-content-between align-items-center"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#{{ ($idPrefix ?? '') . 'fg-collapse-brand' }}"
            aria-expanded="{{ $brandSlug ? 'true' : 'false' }}"
            aria-controls="{{ ($idPrefix ?? '') . 'fg-collapse-brand' }}">
        <span class="font-xs fw-bold text-uppercase letter-wide text-muted">Brand</span>
        <svg class="filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <div class="collapse {{ $brandSlug ? 'show' : '' }} pt-2 pb-3" id="{{ ($idPrefix ?? '') . 'fg-collapse-brand' }}">
        @foreach($brands as $b)
        @php
            $logoSrc     = $b->logo ? asset('storage/' . $b->logo) : null;
            $brandCheckId = ($idPrefix ?? '') . 'brand-' . $b->id;
        @endphp
        <div class="form-check filter-check d-flex align-items-center gap-2">
            <input class="form-check-input brand-checkbox"
                   type="checkbox"
                   id="{{ $brandCheckId }}"
                   data-slug="{{ $b->slug }}"
                   {{ $brandSlug === $b->slug ? 'checked' : '' }}>
            <label class="form-check-label d-flex align-items-center gap-2 w-100" for="{{ $brandCheckId }}">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="{{ $b->name }}" style="height:16px;width:auto;max-width:40px;object-fit:contain;" loading="lazy" onerror="this.style.display='none'">
                @else
                    <span class="brand-filter-dot"></span>
                @endif
                <span>{{ $b->name }}</span>
            </label>
        </div>
        @endforeach
    </div>
</div>
@endif

@if($showActions ?? true)
<div class="filter-actions mt-4 d-flex flex-column gap-2">
    <button type="button" class="btn-dark-custom w-100 text-center" id="{{ $applyBtnId ?? 'applyFiltersBtn' }}">
        {{ $locale === 'vi' ? 'Áp dụng' : 'Apply Filters' }}
    </button>
    <button type="button" class="btn-outline-custom w-100 text-center" id="{{ $clearBtnId ?? 'clearFiltersBtn' }}">
        {{ $locale === 'vi' ? 'Xoá bộ lọc' : 'Clear All' }}
    </button>
</div>
@endif
