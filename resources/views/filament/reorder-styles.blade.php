<style>
    /* ── Drag-and-drop reorder handle highlight ───────────────────────── */

    /* Always visible & blue instead of muted gray */
    .fi-ta-reorder-handle {
        color: rgb(59 130 246) !important;   /* Filament primary blue */
        opacity: 1 !important;
        cursor: grab !important;
        transition: color 0.15s ease, background-color 0.15s ease, transform 0.15s ease;
        border-radius: 0.375rem;
    }

    /* Hover: darker blue + light blue bg pill */
    .fi-ta-reorder-handle:hover {
        color: rgb(37 99 235) !important;
        background-color: rgb(239 246 255) !important;
        transform: scale(1.15);
    }

    /* Active / dragging */
    .fi-ta-reorder-handle:active {
        cursor: grabbing !important;
        color: rgb(29 78 216) !important;
        background-color: rgb(219 234 254) !important;
    }

    /* Dark mode */
    .dark .fi-ta-reorder-handle {
        color: rgb(96 165 250) !important;
    }

    .dark .fi-ta-reorder-handle:hover {
        color: rgb(147 197 253) !important;
        background-color: rgb(30 58 138 / 0.4) !important;
    }

    .dark .fi-ta-reorder-handle:active {
        color: rgb(191 219 254) !important;
        background-color: rgb(30 58 138 / 0.6) !important;
    }
</style>
