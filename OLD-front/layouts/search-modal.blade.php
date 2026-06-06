<!-- search area start -->
<div class="tp-search-area p-relative">
    <div class="tp-search-close">
        <button class="tp-search-close-btn" aria-label="Close search">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path class="path-1" d="M11 1L1 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path class="path-2" d="M1 1L11 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    </div>
    <div class="container container-1230">
        <div class="row">
            <div class="tp-search-wrapper">
                <div class="col-lg-8">
                    <div class="tp-search-content">
                        <div class="search p-relative">
                            <form action="{{ route(current_locale() . '.product.shop') }}" method="GET" id="headerSearchForm">
                                <input type="text"
                                    name="q"
                                    id="header-search-input"
                                    class="search-input"
                                    placeholder="Tìm kiếm sản phẩm, SKU..."
                                    autocomplete="off"
                                    data-autocomplete-url="{{ route(current_locale() . '.product.autocomplete') }}"
                                    data-shop-url="{{ route(current_locale() . '.product.shop') }}">
                                <button type="submit"
                                    class="tp-search-icon"
                                    aria-label="Search"
                                    >   
                                    <i class="fal fa-search"></i>

                            </button>

                            </form>

                            
                            <!-- Autocomplete Dropdown -->
                            <div id="header-autocomplete-dropdown" class="autocomplete-dropdown" style="display: none;">
                                <div class="autocomplete-list"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="body-overlay"></div>
<!-- search area end -->
