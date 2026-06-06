/**
 * Header Search Autocomplete
 * Handles autocomplete search functionality in the header search modal
 */

(function($) {
    'use strict';

    /**
     * Header search autocomplete functionality
     * - Debounced AJAX search (300ms)
     * - Displays max 7 results
     * - Shows "xem thêm" button
     * - Highlights matching text
     */
    function initHeaderAutocomplete() {
        const $searchInput = $('#header-search-input');
        const $dropdown = $('#header-autocomplete-dropdown');
        let debounceTimer;
        let currentRequest = null;
        
        // Get autocomplete URL from data attribute
        const autocompleteUrl = $searchInput.data('autocomplete-url');
        const shopUrl = $searchInput.data('shop-url');
        
        if (!autocompleteUrl || !shopUrl) {
            console.warn('Header search URLs not configured');
            return;
        }
        
        // Search input handler with debounce
        $searchInput.on('input', function() {
            const keyword = $(this).val().trim();
            
            clearTimeout(debounceTimer);
            
            if (keyword.length < 2) {
                $dropdown.hide().empty();
                return;
            }
            
            debounceTimer = setTimeout(function() {
                performSearch(keyword);
            }, 300);
        });
        
        // Perform AJAX search
        function performSearch(keyword) {
            // Cancel previous request if still pending
            if (currentRequest) {
                currentRequest.abort();
            }
            
            // Show loading state
            $dropdown.html('<div class="autocomplete-loading">Đang tìm kiếm...</div>').show();
            
            // Make AJAX request
            currentRequest = $.ajax({
                url: autocompleteUrl,
                type: 'GET',
                data: { q: keyword },
                success: function(response) {
                    displayResults(response, keyword);
                },
                error: function(xhr) {
                    if (xhr.statusText !== 'abort') {
                        $dropdown.html('<div class="autocomplete-no-results">Lỗi khi tìm kiếm</div>');
                    }
                },
                complete: function() {
                    currentRequest = null;
                }
            });
        }
        
        // Display search results
        function displayResults(data, keyword) {
            if (!data.products || data.products.length === 0) {
                $dropdown.html('<div class="autocomplete-no-results">Không tìm thấy sản phẩm</div>').show();
                return;
            }
            
            let html = '<div class="autocomplete-list">';
            
            data.products.forEach(function(product) {
                    const highlightedName = highlightText(product.name, keyword);
                    const highlightedSku = highlightText(product.sku || '', keyword);
                    const highlightedBrand = highlightText(product.brand || '', keyword);
                    const productUrl = product.url; // ✅ FIXED
                    const imageUrl = product.image_url || '/assets/img/no-image.png';
                                
                html += `
                    <a href="${productUrl}" class="autocomplete-item">
                        <img src="${imageUrl}" alt="${product.name}" class="autocomplete-item-image" onerror="this.onerror=null;this.src='/assets/img/product/default.jpg'"
>
                        <div class="autocomplete-item-info">
                            <div class="autocomplete-item-name">${highlightedName}</div>
                            <div class="autocomplete-item-meta">
                                ${product.sku ? `<span class="autocomplete-item-sku">SKU: ${highlightedSku}</span>` : ''}
                                ${product.brand ? `<span class="autocomplete-item-brand">${highlightedBrand}</span>` : ''}
                            </div>
                        </div>
                    </a>
                `;
            });
            
            html += '</div>';
            
            // Add "view all" button if there are more results
            if (data.hasMore) {
                html += `<a href="${shopUrl}?q=${encodeURIComponent(keyword)}" class="autocomplete-view-all">
                    Xem thêm ${data.total} sản phẩm →
                </a>`;
            }
            
            $dropdown.html(html).show();
        }
        
        // Highlight matching text
        function highlightText(text, keyword) {
            if (!text || !keyword) return text;
            
            const regex = new RegExp(`(${escapeRegex(keyword)})`, 'gi');
            return text.replace(regex, '<strong>$1</strong>');
        }
        
        // Escape special regex characters
        function escapeRegex(str) {
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        
        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.tp-search-content').length) {
                $dropdown.hide();
            }
        });
        
        // Prevent dropdown from closing when clicking inside
        $dropdown.on('click', function(e) {
            e.stopPropagation();
        });
        
        // Show dropdown again when focusing on input with existing value
        $searchInput.on('focus', function() {
            if ($(this).val().trim().length >= 2 && $dropdown.children().length > 0) {
                $dropdown.show();
            }
        });
        
        // Clear search when modal closes
        $('.tp-search-close-btn, .body-overlay').on('click', function() {
            $searchInput.val('');
            $dropdown.hide().empty();
        });
    }

    // ========================================
    // INITIALIZATION
    // ========================================
    
    $(document).ready(function() {
        initHeaderAutocomplete();
    });

})(jQuery);
