<div class="page-loader" data-page-loader role="status" aria-live="polite" aria-label="Loading Lensify">
    <div class="page-loader__wrap">
        <svg class="page-loader__glasses" viewBox="0 0 240 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <defs>
                <linearGradient id="lensGradient" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#ff7a3d" />
                    <stop offset="55%" stop-color="#f7418f" />
                    <stop offset="100%" stop-color="#8b2fc9" />
                </linearGradient>
                <clipPath id="lensClipL"><rect x="35" y="25" width="65" height="50" rx="20" /></clipPath>
                <clipPath id="lensClipR"><rect x="140" y="25" width="65" height="50" rx="20" /></clipPath>
            </defs>
            <path class="page-loader__frame page-loader__temple" d="M2,32 L35,38" />
            <path class="page-loader__frame page-loader__temple" d="M238,32 L205,38" />
            <path class="page-loader__frame page-loader__bridge" d="M100,42 Q120,28 140,42" />
            <rect class="page-loader__lens" x="35" y="25" width="65" height="50" rx="20" fill="url(#lensGradient)" />
            <rect class="page-loader__lens" x="140" y="25" width="65" height="50" rx="20" fill="url(#lensGradient)" />
            <g clip-path="url(#lensClipL)"><rect class="page-loader__shine" x="20" y="10" width="18" height="80" transform="skewX(-20)" /></g>
            <g clip-path="url(#lensClipR)"><rect class="page-loader__shine" x="125" y="10" width="18" height="80" transform="skewX(-20)" /></g>
            <rect class="page-loader__frame page-loader__outline" x="35" y="25" width="65" height="50" rx="20" />
            <rect class="page-loader__frame page-loader__outline" x="140" y="25" width="65" height="50" rx="20" />
        </svg>
        <div class="page-loader__label" aria-hidden="true"></div>
        <span class="sr-only">Loading...</span>
    </div>
</div>
