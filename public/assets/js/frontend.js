/* ============================================================
   FLEVIE — Main JavaScript
   Features: Navbar scroll, Countdown, Smooth scroll,
             Scroll-to-top, Fade-in animations, Product slider,
             Momentum (inertia) scroll
   ============================================================ */

"use strict";

/* ── Helpers ─────────────────────────────────────────────── */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

/* ── Navbar: shadow on scroll ────────────────────────────── */
function initNavbar() {
  const nav = $("#mainNav");
  if (!nav) return;

  const onScroll = () => {
    nav.classList.toggle("scrolled", window.scrollY > 10);
  };
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();
}

/* ── Countdown Timer ─────────────────────────────────────── */
function initCountdown() {
  const days = $("#cd-days");
  const hours = $("#cd-hours");
  const mins = $("#cd-mins");
  const secs = $("#cd-secs");
  if (!days) return;

  const target = new Date();
  target.setDate(target.getDate() + 2);
  target.setHours(target.getHours() + 6);
  target.setMinutes(target.getMinutes() + 5);

  function pad(n) { return String(n).padStart(2, "0"); }

  function tick() {
    const diff = Math.max(0, target - Date.now());
    days.textContent = pad(Math.floor(diff / 86400000));
    hours.textContent = pad(Math.floor((diff % 86400000) / 3600000));
    mins.textContent = pad(Math.floor((diff % 3600000) / 60000));
    secs.textContent = pad(Math.floor((diff % 60000) / 1000));
    if (diff > 0) requestAnimationFrame(() => setTimeout(tick, 1000));
  }
  tick();
}

/* ── Scroll-to-Top Button ────────────────────────────────── */
function initScrollTop() {
  const btn = $("#scrollTop");
  if (!btn) return;
  window.addEventListener("scroll", () => {
    btn.classList.toggle("visible", window.scrollY > 400);
  }, { passive: true });
  btn.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));
}

/* ── Fade-up on Scroll (IntersectionObserver) ────────────── */
function initFadeUp() {
  const els = $$(".fade-up");
  if (!els.length || !window.IntersectionObserver) {
    els.forEach((el) => el.classList.add("visible"));
    return;
  }
  const obs = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          obs.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12, rootMargin: "0px 0px -40px 0px" }
  );
  els.forEach((el) => obs.observe(el));
}

/* ── Carousel Dots ───────────────────────────────────────── */
function initDots() {
  $$(".carousel-dots").forEach((wrap) => {
    const dots = $$(".carousel-dot", wrap);
    dots.forEach((dot) => {
      dot.addEventListener("click", () => {
        dots.forEach((d) => d.classList.remove("active"));
        dot.classList.add("active");
      });
    });
  });
}

/* ── Smooth In-Page Anchor Scroll ────────────────────────── */
function initSmoothScroll() {
  $$('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", (e) => {
      const target = $(anchor.getAttribute("href"));
      if (!target) return;
      e.preventDefault();
      const navH = document.getElementById("mainNav")?.offsetHeight || 70;
      const top = target.getBoundingClientRect().top + window.scrollY - navH;
      window.scrollTo({ top, behavior: "smooth" });
    });
  });
}

/* ── Product Slider ──────────────────────────────────────── */
function initProductSliders() {
  $$("[data-slider]").forEach((wrap) => {
    const prev = $("[data-prev]", wrap.parentElement);
    const next = $("[data-next]", wrap.parentElement);
    const cards = $$(".product-card", wrap);
    let idx = 0;

    // Always >= 2 per row
    const visibleCount = () =>
      window.innerWidth < 768
        ? 2      // mobile  → 2 per row
        : window.innerWidth < 992
          ? 3    // tablet  → 3 per row
          : 4;   // desktop → 4 per row

    const show = () => {
      const cnt = visibleCount();
      cards.forEach((c, i) => {
        c.style.display = i >= idx && i < idx + cnt ? "" : "none";
      });
    };

    if (prev) prev.addEventListener("click", () => { idx = Math.max(0, idx - 1); show(); });
    if (next) next.addEventListener("click", () => {
      idx = Math.min(cards.length - visibleCount(), idx + 1);
      show();
    });

    window.addEventListener("resize", show, { passive: true });
    show();
  });
}

/* ── Newsletter Form ─────────────────────────────────────── */
function initNewsletter() {
  const form = $("#newsletterForm");
  if (!form) return;
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    const input = $('input[type="email"]', form);
    const btn = $("button", form);
    if (!input.value.trim()) return;
    btn.textContent = "Subscribed ✓";
    btn.disabled = true;
    btn.style.background = "var(--color-accent)";
    input.value = "";
    setTimeout(() => {
      btn.textContent = "Subscribe";
      btn.disabled = false;
      btn.style.background = "";
    }, 3000);
  });
}

/* ── Deals Slider ────────────────────────────────────────── */
function initDealsSlider() {
  const slider = $("#dealsSlider");
  if (!slider) return;
  const slides = $$(".deals-slide");
  const dots = $$("#dealsDots .carousel-dot");
  const prevBtn = $(".deals-prev", slider);
  const nextBtn = $(".deals-next", slider);
  if (!slides.length) return;

  let current = 0, timer = null;

  function goTo(n) {
    slides[current].classList.remove("active");
    dots[current]?.classList.remove("active");
    current = ((n % slides.length) + slides.length) % slides.length;
    slides[current].classList.add("active");
    dots[current]?.classList.add("active");
  }
  const startTimer = () => { timer = setInterval(() => goTo(current + 1), 8000); };
  const resetTimer = () => { clearInterval(timer); startTimer(); };

  dots.forEach((dot, i) => dot.addEventListener("click", () => { goTo(i); resetTimer(); }));
  if (prevBtn) prevBtn.addEventListener("click", () => { goTo(current - 1); resetTimer(); });
  if (nextBtn) nextBtn.addEventListener("click", () => { goTo(current + 1); resetTimer(); });

  slider.addEventListener("mouseenter", () => clearInterval(timer));
  slider.addEventListener("mouseleave", startTimer);
  startTimer();
}

/* ── Hero Slider ─────────────────────────────────────────── */
function initHeroSlider() {
  const items    = $$(".hero-item");
  const dots     = $$(".hero-dot");
  const prevBtn  = $("#heroPrev");
  const nextBtn  = $("#heroNext");
  const stage    = $(".hero-stage");
  const total    = items.length;
  if (!total) return;

  const POS = ["pos-center", "pos-right", "pos-hidden-right", "pos-hidden-left", "pos-left"];
  const TRANSITION_MS = 720; // matches the CSS transition duration (0.7s + small buffer)
  const AUTO_MS       = 8000;

  let activeCenter = 0;
  let timer        = null;
  let isAnimating  = false;  // lock: ignore clicks during slide transition

  /* ── core: mark outgoing content, swap positions, refresh dots */
  function updatePositions() {
    items.forEach((item, i) => {
      const isPendingCenter = (i - activeCenter + total) % total === 0;
      const content = item.querySelector(".hero-item-content");

      // Mark content of items LEAVING the center so they fade out fast
      if (content) {
        if (isPendingCenter) {
          content.classList.remove("is-leaving");
        } else {
          content.classList.add("is-leaving");
        }
      }

      item.classList.remove(...POS);
      item.classList.add(POS[(i - activeCenter + total) % total]);
    });

    dots.forEach((d, i) => d.classList.toggle("active", i === activeCenter));
  }

  function goTo(n) {
    if (isAnimating) return;          // prevent jank on rapid clicks
    isAnimating = true;

    activeCenter = ((n % total) + total) % total;
    updatePositions();

    // After the transition is done, release the lock
    setTimeout(() => { isAnimating = false; }, TRANSITION_MS);
  }

  /* ── timer: always clear before setting — never stack intervals */
  function startTimer() {
    clearInterval(timer);
    timer = setInterval(() => goTo(activeCenter + 1), AUTO_MS);
  }
  function stopTimer()  { clearInterval(timer); }
  function resetTimer() { startTimer(); }

  /* ── arrows */
  if (prevBtn) prevBtn.addEventListener("click", () => { goTo(activeCenter - 1); resetTimer(); });
  if (nextBtn) nextBtn.addEventListener("click", () => { goTo(activeCenter + 1); resetTimer(); });

  /* ── dots */
  dots.forEach((dot, i) => dot.addEventListener("click", () => { goTo(i); resetTimer(); }));

  /* ── pause on hover */
  if (stage) {
    stage.addEventListener("mouseenter", stopTimer);
    stage.addEventListener("mouseleave", startTimer);
  }

  /* ── swipe support for touch devices */
  if (stage) {
    let touchStartX = 0;
    stage.addEventListener("touchstart", (e) => {
      touchStartX = e.changedTouches[0].clientX;
    }, { passive: true });
    stage.addEventListener("touchend", (e) => {
      const dx = e.changedTouches[0].clientX - touchStartX;
      if (Math.abs(dx) < 40) return;  // ignore tiny taps
      if (dx < 0) { goTo(activeCenter + 1); } else { goTo(activeCenter - 1); }
      resetTimer();
    }, { passive: true });
  }

  /* ── keyboard arrow navigation (only when hero is in viewport) */
  document.addEventListener("keydown", (e) => {
    const heroEl = document.getElementById("hero");
    if (!heroEl) return;
    const rect = heroEl.getBoundingClientRect();
    // Only when hero section is at least partially visible
    if (rect.bottom < 0 || rect.top > window.innerHeight) return;
    if (e.key === "ArrowLeft")  { goTo(activeCenter - 1); resetTimer(); }
    if (e.key === "ArrowRight") { goTo(activeCenter + 1); resetTimer(); }
  });

  /* ── init */
  updatePositions();
  startTimer();
}

/* ── Bootstrap Mobile Menu ───────────────────────────────── */
function initMobileMenu() {
  const toggler = $(".navbar-toggler");
  const collapse = $("#navCollapse");
  if (!toggler || !collapse) return;

  // Handle dropdown toggles on mobile
  $$(".custom-dropdown-link > .nav-link", collapse).forEach((link) => {
    link.addEventListener("click", (e) => {
      // Only behave as toggle on mobile view
      if (window.innerWidth < 992) {
        e.preventDefault();
        const parent = link.parentElement;
        parent.classList.toggle("open");
      }
    });
  });

  // Close the menu when a standard link or a dropdown item is clicked
  $$(".navbar-nav .nav-link, .custom-dropdown-menu a", collapse).forEach((link) => {
    link.addEventListener("click", () => {
      // Do nothing if it's hitting a dropdown toggle on mobile
      if (window.innerWidth < 992 && link.parentElement.classList.contains("custom-dropdown-link")) {
        return;
      }
      const bsCollapse = bootstrap.Collapse.getInstance(collapse);
      if (bsCollapse) bsCollapse.hide();
    });
  });
}

/* ── GSAP ScrollSmoother ──────────────────────────────────────
   Uses GSAP ScrollSmoother for premium inertia scrolling.
──────────────────────────────────────────────────────────── */
function initMomentumScroll() {
  // 17. scroll wrapper //
  gsap.registerPlugin(ScrollTrigger, ScrollSmoother, ScrollToPlugin);

  if ($('#smooth-wrapper') && $('#smooth-content')) {
    ScrollSmoother.create({
      smooth: 1.35,
      effects: true,
      smoothTouch: 0.1,
      ignoreMobileResize: true
    });
  }
}

/* ── Product Detail Page ────────────────────────────────────
   All functions below only run on product-detail.html.
   Each one guards itself with an early return if the key
   element doesn't exist, so they're safe to call on any page.
──────────────────────────────────────────────────────────── */

/* ── Gallery: thumbnail switching ─────── */
function initGallery() {
  const mainImg = document.getElementById("pdMainImg");
  const thumbBtns = document.querySelectorAll(".pd-thumb");
  if (!mainImg || !thumbBtns.length) return;

  thumbBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      const imgSrc = btn.dataset.img;
      mainImg.style.opacity = "0";
      setTimeout(() => {
        mainImg.src = imgSrc;
        mainImg.style.opacity = "1";
      }, 200);
      thumbBtns.forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
    });
  });
}

/* ── Size selector ────────────────────── */
function initSizeSelector() {
  const grid = document.getElementById("pdSizeGrid");
  const label = document.getElementById("pdSelectedSize");
  if (!grid) return;
  grid.querySelectorAll(".pd-size-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      grid.querySelectorAll(".pd-size-btn").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      if (label) label.textContent = btn.textContent.trim();
    });
  });
}

/* ── Color selector ──────────────────── */
function initColorSelector() {
  const grid = document.getElementById("pdColorGrid");
  const label = document.getElementById("pdSelectedColor");
  if (!grid) return;
  grid.querySelectorAll(".pd-color-swatch").forEach((btn) => {
    btn.addEventListener("click", () => {
      grid.querySelectorAll(".pd-color-swatch").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      if (label) label.textContent = btn.dataset.color;
    });
  });
}

/* ── Quantity stepper ────────────────── */
function initQtyStepper() {
  const input = document.getElementById("pdQtyInput");
  const minus = document.getElementById("pdQtyMinus");
  const plus  = document.getElementById("pdQtyPlus");
  if (!input) return;

  minus.addEventListener("click", () => {
    const val = parseInt(input.value) || 1;
    input.value = Math.max(1, val - 1);
  });
  plus.addEventListener("click", () => {
    const val = parseInt(input.value) || 1;
    input.value = Math.min(99, val + 1);
  });
}

/* ── Flash sale countdown ────────────── */
function initFlashCountdown() {
  const hEl = document.getElementById("pd-cd-hours");
  const mEl = document.getElementById("pd-cd-mins");
  const sEl = document.getElementById("pd-cd-secs");
  if (!hEl) return;

  const end = Date.now() + (5 * 3600 + 30 * 60) * 1000;
  function pad(n) { return String(n).padStart(2, "0"); }
  function tick() {
    const diff = Math.max(0, end - Date.now());
    hEl.textContent = pad(Math.floor(diff / 3600000));
    mEl.textContent = pad(Math.floor((diff % 3600000) / 60000));
    sEl.textContent = pad(Math.floor((diff % 60000) / 1000));
    if (diff > 0) setTimeout(tick, 1000);
  }
  tick();
}

/* ── Tab system ──────────────────────── */
function initTabs() {
  const tabs   = document.querySelectorAll(".pd-tab");
  const panels = document.querySelectorAll(".pd-tab-panel");
  if (!tabs.length) return;

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      const target = tab.dataset.tab;
      tabs.forEach((t)   => t.classList.remove("active"));
      panels.forEach((p) => p.classList.remove("active"));
      tab.classList.add("active");
      const panel = document.querySelector(`.pd-tab-panel[data-panel="${target}"]`);
      if (panel) panel.classList.add("active");
    });
  });
}

/* ── Add to Cart feedback ────────────── */
function initAddToCart() {
  const btn          = document.getElementById("pdAddToCart");
  const toast        = document.getElementById("pdToast");
  const wishlistIcon = document.getElementById("pdWishlistIcon");
  const wishlistBtn  = document.getElementById("pdWishlistBtn");
  if (!btn) return;

  btn.addEventListener("click", () => {
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 2500);
    btn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Added!';
    btn.style.background   = "var(--color-accent)";
    btn.style.borderColor  = "var(--color-accent)";
    setTimeout(() => {
      btn.innerHTML = '<i class="bi bi-bag-plus me-2"></i>Add to Cart';
      btn.style.background  = "";
      btn.style.borderColor = "";
    }, 2000);
  });

  [wishlistIcon, wishlistBtn].forEach((b) => {
    if (!b) return;
    b.addEventListener("click", () => {
      b.classList.toggle("active");
      const icon = b.querySelector("i");
      if (icon) icon.className = b.classList.contains("active") ? "bi bi-heart-fill" : "bi bi-heart";
    });
  });
}

/* ── Cart Page ───────────────────────────────────────────── */
function initCart() {
  const cartMain  = document.getElementById('cartMain');
  const cartEmpty = document.getElementById('cartEmpty');
  if (!cartMain) return; // not the cart page

  const cartCountEl      = document.getElementById('cartCount');
  const summaryCountEl   = document.getElementById('summaryCount');
  const summarySubtotalEl= document.getElementById('summarySubtotal');
  const summaryDiscountEl= document.getElementById('summaryDiscount');
  const summaryTotalEl   = document.getElementById('summaryTotal');
  const couponRow        = document.getElementById('couponRow');
  const summaryCouponEl  = document.getElementById('summaryCoupon');
  const couponCodeTag    = document.getElementById('couponCodeTag');
  const cartNavBadge     = document.getElementById('cartNavBadge');
  const cartToast        = document.getElementById('cartToast');

  // Valid coupon codes: code → discount %
  const COUPONS = { 'FLEVIE10': 10, 'SAVE15': 15, 'WELCOME20': 20 };
  let activeCouponDiscount = 0;
  const FREE_SHIPPING_THRESHOLD = 2000000; // 2M VND

  function formatVND(n) {
    return n.toLocaleString('vi-VN') + ' VND';
  }

  /* -- Recalculate totals -- */
  function recalc() {
    const items = [...document.querySelectorAll('.cart-item')];
    let subtotal = 0;
    let originalTotal = 0;

    items.forEach(item => {
      const priceEl  = item.querySelector('.cart-item-total-price');
      const qtyInput = item.querySelector('.cart-qty-input');
      const unit     = parseInt(priceEl.dataset.unit, 10) || 0;
      const qty      = parseInt(qtyInput.value, 10) || 1;
      priceEl.dataset.qty = qty;
      subtotal     += unit * qty;
      // calc original from old price if shown
      const oldPriceEl = item.querySelector('.cart-price-old');
      if (oldPriceEl) {
        const oldText = oldPriceEl.textContent.replace(/[^0-9]/g, '');
        originalTotal += (parseInt(oldText, 10) || unit) * qty;
      } else {
        originalTotal += unit * qty;
      }
      // update item total display
      priceEl.textContent = formatVND(unit * qty);
    });

    const itemCount = items.length;
    const discount  = originalTotal - subtotal;
    const couponAmt = Math.round(subtotal * activeCouponDiscount / 100);
    const shipping  = subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : 50000;
    const total     = subtotal - couponAmt + shipping;

    if (cartCountEl)     cartCountEl.textContent  = itemCount;
    if (summaryCountEl)  summaryCountEl.textContent = itemCount;
    if (cartNavBadge)    cartNavBadge.textContent = itemCount;
    if (summarySubtotalEl) summarySubtotalEl.textContent = formatVND(subtotal);
    if (summaryDiscountEl && discount > 0) {
      summaryDiscountEl.textContent = '–' + formatVND(discount);
    } else if (summaryDiscountEl) {
      summaryDiscountEl.textContent = '–0 VND';
    }
    if (summaryTotalEl) summaryTotalEl.textContent = formatVND(total);

    // Shipping bar
    const fillEl = document.getElementById('shippingFill');
    const barMsg = document.getElementById('shippingBarMsg');
    if (fillEl && barMsg) {
      if (subtotal >= FREE_SHIPPING_THRESHOLD) {
        fillEl.style.width = '100%';
        barMsg.innerHTML = `You've unlocked <strong>free shipping!</strong>`;
      } else {
        const pct = Math.min(100, (subtotal / FREE_SHIPPING_THRESHOLD) * 100);
        fillEl.style.width = pct + '%';
        const remaining = formatVND(FREE_SHIPPING_THRESHOLD - subtotal);
        barMsg.innerHTML = `Add <strong>${remaining}</strong> more for free shipping`;
      }
    }

    // Coupon row
    if (couponRow && summaryCouponEl) {
      if (activeCouponDiscount > 0) {
        couponRow.style.display = '';
        summaryCouponEl.textContent = '–' + formatVND(couponAmt);
      } else {
        couponRow.style.display = 'none';
      }
    }

    // Empty state toggle
    if (itemCount === 0) {
      cartMain.style.display = 'none';
      if (cartEmpty) cartEmpty.style.display = '';
      document.getElementById('cartRelated')?.style.setProperty('display', 'none');
    }
  }

  /* -- Quantity steppers -- */
  document.querySelectorAll('.cart-qty-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const wrap  = btn.closest('.pd-qty-wrap');
      const input = wrap.querySelector('.cart-qty-input');
      const priceEl = btn.closest('.cart-item').querySelector('.cart-item-total-price');
      let val = parseInt(input.value, 10) || 1;
      if (btn.dataset.action === 'plus')  val = Math.min(99, val + 1);
      if (btn.dataset.action === 'minus') val = Math.max(1, val - 1);
      input.value = val;
      priceEl.dataset.qty = val;
      recalc();
    });
  });

  /* -- Remove item -- */
  document.querySelectorAll('.cart-item-remove').forEach(btn => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.cart-item');
      item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
      item.style.opacity = '0';
      item.style.transform = 'translateX(20px)';
      setTimeout(() => {
        item.remove();
        recalc();
        // Show toast
        if (cartToast) {
          cartToast.classList.add('show');
          setTimeout(() => cartToast.classList.remove('show'), 2200);
        }
      }, 300);
    });
  });

  /* -- Clear cart -- */
  const clearBtn = document.getElementById('cartClearBtn');
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      document.querySelectorAll('.cart-item').forEach(item => {
        item.style.transition = 'opacity 0.25s ease';
        item.style.opacity = '0';
        setTimeout(() => { item.remove(); recalc(); }, 280);
      });
    });
  }

  /* -- Coupon -- */
  const couponInput    = document.getElementById('couponInput');
  const couponApplyBtn = document.getElementById('couponApplyBtn');
  const couponMsg      = document.getElementById('couponMsg');

  if (couponApplyBtn && couponInput) {
    couponApplyBtn.addEventListener('click', () => {
      const code = couponInput.value.trim().toUpperCase();
      if (!code) { return; }
      if (COUPONS[code]) {
        activeCouponDiscount = COUPONS[code];
        if (couponCodeTag) couponCodeTag.textContent = code;
        couponMsg.textContent = `✓ Coupon "${code}" applied — ${COUPONS[code]}% off!`;
        couponMsg.className = 'cart-coupon-msg success';
        couponInput.value = '';
        couponApplyBtn.textContent = 'Applied ✓';
        couponApplyBtn.style.background = 'var(--color-accent)';
        setTimeout(() => {
          couponApplyBtn.textContent = 'Apply';
          couponApplyBtn.style.background = '';
        }, 2000);
      } else {
        couponMsg.textContent = `✕ Invalid coupon code. Try FLEVIE10 or SAVE15.`;
        couponMsg.className = 'cart-coupon-msg error';
      }
      recalc();
    });

    couponInput.addEventListener('keydown', e => {
      if (e.key === 'Enter') couponApplyBtn.click();
    });
  }

  /* -- Checkout CTA -- */
  const checkoutBtn = document.getElementById('checkoutBtn');
  if (checkoutBtn) {
    checkoutBtn.addEventListener('click', () => {
      checkoutBtn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Processing…';
      checkoutBtn.disabled = true;
      setTimeout(() => {
        checkoutBtn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Proceed to Checkout';
        checkoutBtn.disabled = false;
      }, 2000);
    });
  }

  // Initial calculation
  recalc();
}

/* ── Shop Page Filters ───────────────────────────────────── */
function initShopFilters() {
  const clearBtn = $("#clearFiltersBtn");
  const applyBtn = $("#applyFiltersBtn");
  if (!clearBtn || !applyBtn) return;

  clearBtn.addEventListener("click", () => {
    // Uncheck all checkboxes
    $$(".shop-sidebar .filter-check input[type='checkbox']").forEach(cb => cb.checked = false);
    
    // Remove active styles from buttons and tags
    $$(".size-btn, .color-btn, .sidebar-tag").forEach(btn => btn.classList.remove("active"));
  });

  applyBtn.addEventListener("click", () => {
    const originalText = applyBtn.textContent;
    applyBtn.textContent = "Applied ✓";
    applyBtn.style.background = "var(--color-accent)";
    setTimeout(() => {
      applyBtn.textContent = originalText;
      applyBtn.style.background = "";
    }, 1500);
  });

  // Handle click toggling on buttons and tags for demo purposes
  $$(".size-btn, .color-btn, .sidebar-tag").forEach(btn => {
    btn.addEventListener("click", () => {
      btn.classList.toggle("active");
    });
  });
}

/* ── Homepage Category Product Slider ───────────────────── */
function initHcpSliders() {
  document.querySelectorAll('.hcp-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const sliderId = this.dataset.slider;
      const dir      = this.dataset.dir;
      const slider   = document.getElementById(sliderId);
      if (!slider) return;

      const item = slider.querySelector('.hcp-item');
      if (!item) return;

      const gap         = 16;
      const itemWidth   = item.offsetWidth + gap;
      const wrapWidth   = slider.parentElement.offsetWidth;
      const visibleCount = Math.max(1, Math.floor(wrapWidth / itemWidth));
      const step        = itemWidth * visibleCount;
      const maxOffset   = -(slider.scrollWidth - wrapWidth + gap);

      let current = slider._hcpOffset || 0;
      current = dir === 'next'
        ? Math.max(maxOffset, current - step)
        : Math.min(0, current + step);

      slider._hcpOffset = current;
      slider.style.transform = `translateX(${current}px)`;
    });
  });
}

/* ── Init All ────────────────────────────────────────────── */
document.addEventListener("DOMContentLoaded", () => {
  initNavbar();
  initCountdown();
  initScrollTop();
  initFadeUp();
  initDots();
  initSmoothScroll();
  initProductSliders();
  initNewsletter();
  initDealsSlider();
  initHeroSlider();
  initMobileMenu();
  initMomentumScroll();
  initHcpSliders();
  initShopFilters();
  initCart();

  // Product Detail page — safe to call on every page;
  // each function returns early if its elements don't exist.
  initGallery();
  initSizeSelector();
  initColorSelector();
  initQtyStepper();
  initFlashCountdown();
  initTabs();
  initAddToCart();
});
