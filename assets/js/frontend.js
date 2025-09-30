/* global window, document */
(function (window) {
    'use strict';

    if (!window || !window.document) {
        return;
    }

    var data = window.PersianOriginsData || {};
    var progress = data.readingProgress || null;

    function setCookie(name, value, maxAge) {
        if (!name) {
            return;
        }

        var cookie = name + '=' + encodeURIComponent(value) + ';path=/;SameSite=Lax';

        if (maxAge) {
            cookie += ';max-age=' + parseInt(maxAge, 10);
        }

        if (window.location && window.location.protocol === 'https:') {
            cookie += ';secure';
        }

        document.cookie = cookie;
    }

    if (progress && Array.isArray(progress.categories)) {
        try {
            var postId = progress.postId;
            var maxAge = progress.maxAge || (30 * 24 * 60 * 60);

            progress.categories.forEach(function (categoryId) {
                var key = 'po_last_read_' + categoryId;

                try {
                    window.localStorage.setItem(key, String(postId));
                } catch (storageError) {
                    // Ignore storage errors (private mode, quota, etc.).
                }

                setCookie(key, postId, maxAge);
            });
        } catch (error) {
            // Fail quietly – tracking should not break the page.
        }
    }
})(window);