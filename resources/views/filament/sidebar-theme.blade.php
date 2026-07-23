<style>
    /* Hand cursor on everything clickable (Tailwind v4 drops it on buttons). */
    button:not(:disabled),
    [role='button']:not(:disabled),
    summary,
    .fi-btn:not(:disabled),
    .fi-icon-btn:not(:disabled),
    .fi-ta-header-cell-sort-btn,
    label.fi-checkbox-input,
    .fi-select-input,
    .choices__item {
        cursor: pointer;
    }

    button:disabled,
    .fi-btn:disabled {
        cursor: not-allowed;
    }

    /* ===== News Paper — dark sidebar theme ===== */
    .fi-sidebar,
    .fi-sidebar-header,
    .fi-sidebar-nav {
        background-color: #111827 !important; /* gray-900 */
        border-color: #1f2937 !important;     /* gray-800 */
    }

    /* Brand / logo text */
    .fi-sidebar-header .fi-logo,
    .fi-sidebar-header a {
        color: #ffffff !important;
    }

    /* Group headings */
    .fi-sidebar-group-label,
    .fi-sidebar-group-collapse-button {
        color: #9ca3af !important; /* gray-400 */
    }

    /* Nav items (default) */
    .fi-sidebar-item-label {
        color: #d1d5db !important; /* gray-300 */
    }
    .fi-sidebar-item-icon {
        color: #9ca3af !important; /* gray-400 */
    }

    /* Hover */
    .fi-sidebar-item-button:hover {
        background-color: rgba(255, 255, 255, 0.06) !important;
    }
    .fi-sidebar-item-button:hover .fi-sidebar-item-label,
    .fi-sidebar-item-button:hover .fi-sidebar-item-icon {
        color: #ffffff !important;
    }

    /* Active item — red accent */
    .fi-sidebar-item-active .fi-sidebar-item-button {
        background-color: rgba(239, 68, 68, 0.15) !important; /* red-500/15 */
    }
    .fi-sidebar-item-active .fi-sidebar-item-label,
    .fi-sidebar-item-active .fi-sidebar-item-icon {
        color: #f87171 !important; /* red-400 */
    }

    /* Collapse (minimize) button + badges stay readable */
    .fi-sidebar .fi-icon-btn {
        color: #9ca3af !important;
    }
    .fi-sidebar .fi-icon-btn:hover {
        color: #ffffff !important;
    }
</style>
