/* global window, document */
(function (window, document) {
    'use strict';

    if (!window || !document) {
        return;
    }

    var data = window.PersianOriginsData || {};
    var progress = data.readingProgress || null;

    function setCookie(name, value, maxAge) {
        if (!name) {
            return;
        }

        var cookie = name + '=' + encodeURIComponent(value) + ';path=/;SameSite=Lax';

        if (typeof maxAge === 'number' && !isNaN(maxAge)) {
            cookie += ';max-age=' + parseInt(maxAge, 10);
        }

        if (window.location && window.location.protocol === 'https:') {
            cookie += ';secure';
        }

        document.cookie = cookie;
    }

    function getCookie(name) {
        if (!name) {
            return '';
        }

        var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()\[\]\\\/\+^]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function parseReadPosts(value) {
        if (!value) {
            return [];
        }

        if (Array.isArray(value)) {
            return value.map(function (item) {
                return parseInt(item, 10);
            }).filter(function (num) {
                return !isNaN(num);
            });
        }

        if (typeof value === 'string') {
            var trimmed = value.trim();
            if (!trimmed) {
                return [];
            }

            try {
                if (trimmed.charAt(0) === '[') {
                    return parseReadPosts(JSON.parse(trimmed));
                }
            } catch (error) {
                // Ignore JSON parse errors and fall back to CSV parsing.
            }

            return trimmed.split(',').map(function (part) {
                return parseInt(part, 10);
            }).filter(function (num) {
                return !isNaN(num);
            });
        }

        return [];
    }

    function uniqueInts(values) {
        var seen = {};
        return values.filter(function (value) {
            var intValue = parseInt(value, 10);
            if (isNaN(intValue)) {
                return false;
            }

            if (seen[intValue]) {
                return false;
            }

            seen[intValue] = true;
            return true;
        });
    }


    function updateProgressBars(categoryId, readCount, total) {
        var bars = document.querySelectorAll('.po-progress-bar[data-category="' + categoryId + '"]');
        if (!bars.length) {
            return;
        }

        var percentage = 0;
        if (total > 0) {
            percentage = Math.min(100, Math.round((readCount / total) * 100));
        }

        bars.forEach(function (bar) {
            bar.setAttribute('data-read', readCount);
            bar.setAttribute('data-total', total);

            var percentEl = bar.querySelector('.po-progress-bar__percent');
            if (percentEl) {
                percentEl.textContent = percentage + '%';
            }

            var track = bar.querySelector('.po-progress-bar__track');
            if (track) {
                track.setAttribute('aria-valuenow', percentage);
            }

            var fill = bar.querySelector('.po-progress-bar__fill');
            if (fill) {
                fill.style.width = percentage + '%';
            }

            var countsEl = bar.querySelector('.po-progress-bar__counts');
            if (countsEl) {
                var numbersEl = countsEl.querySelector('.po-progress-bar__numbers');
                if (numbersEl) {
                    numbersEl.textContent = readCount + ' / ' + total;
                } else {
                    countsEl.textContent = readCount + ' / ' + total;
                }
            }
        });
    }

    if (progress && Array.isArray(progress.categories)) {
        try {
            var postId = parseInt(progress.postId, 10);
            if (isNaN(postId) || postId < 1) {
                postId = null;
            }

            var maxAge = progress.maxAge || (30 * 24 * 60 * 60);
            var totals = progress.totals || {};
            var initialReadCounts = progress.readCounts || {};

            progress.categories.forEach(function (categoryIdRaw) {
                var categoryId = parseInt(categoryIdRaw, 10);
                if (isNaN(categoryId)) {
                    return;
                }

                var lastKey = 'po_last_read_' + categoryId;
                var readKey = 'po_read_posts_' + categoryId;
                var storedRead = '';

                try {
                    storedRead = window.localStorage.getItem(readKey) || '';
                } catch (storageError) {
                    storedRead = '';
                }

                var readPosts = parseReadPosts(storedRead);
                if (!readPosts.length) {
                    readPosts = parseReadPosts(getCookie(readKey));
                }

                if (postId && readPosts.indexOf(postId) === -1) {
                    readPosts.push(postId);
                }

                readPosts = uniqueInts(readPosts);

                var maxEntries = typeof window.persianOriginsGuestLimit === 'number'
                    ? window.persianOriginsGuestLimit
                    : 200;
                if (maxEntries > 0 && readPosts.length > maxEntries) {
                    readPosts = readPosts.slice(readPosts.length - maxEntries);
                }

                if (postId) {
                    try {
                        window.localStorage.setItem(lastKey, String(postId));
                    } catch (setLastError) {
                        // Ignore storage errors.
                    }

                    setCookie(lastKey, postId, maxAge);
                }

                try {
                    window.localStorage.setItem(readKey, JSON.stringify(readPosts));
                } catch (setReadError) {
                    // Ignore storage errors.
                }

                setCookie(readKey, readPosts.join(','), maxAge);

                var total = parseInt(totals[categoryId], 10);
                if (isNaN(total)) {
                    total = 0;
                }

                var readCount = readPosts.length;
                if (!readCount && typeof initialReadCounts[categoryId] !== 'undefined') {
                    readCount = parseInt(initialReadCounts[categoryId], 10) || 0;
                }

                updateProgressBars(categoryId, readCount, total);
            });
        } catch (error) {
            // Fail quietly – tracking should not break the page.
        }
    }
})(window, document);



















