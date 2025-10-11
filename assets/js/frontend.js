/* global window, document */
(function (window, document) {
  "use strict";

  if (!window || !document) {
    return;
  }

  var data = window.PersianOriginsData || {};
  var progress = data.readingProgress || null;
  var settings = data.siteSettings || null;

  var body = document.body || document.getElementsByTagName("body")[0];
  var html = document.documentElement;

  // The only element that should change theme:
  var themableArticles = document.querySelectorAll("#post-section > div > div > article");
  if (!themableArticles.length) {
    themableArticles = document.querySelectorAll("article");
  }
  // ---- THEME HELPERS ----
  var THEME_KEY = "po_site_theme"; // localStorage + cookie key

  function getSystemTheme() {
    try {
      return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
    } catch (e) {
      return "light";
    }
  }

  function getSavedTheme() {
    try {
      var v = window.localStorage.getItem(THEME_KEY);
      if (v === "dark" || v === "light") return v;
    } catch (e) {}
    // Fallback to cookie if localStorage not available
    var c = getCookie(THEME_KEY);
    if (c === "dark" || c === "light") return c;
    return null;
  }

  function setCookie(name, value, maxAge) {
    if (!name) {
      return;
    }

    var cookie = name + "=" + encodeURIComponent(value) + ";path=/;SameSite=Lax";

    if (typeof maxAge === "number" && !isNaN(maxAge)) {
      cookie += ";max-age=" + parseInt(maxAge, 10);
    }

    if (window.location && window.location.protocol === "https:") {
      cookie += ";secure";
    }

    document.cookie = cookie;
  }

  // ---- LANGUAGE COOKIE HELPER ----
  function setLangCookie(lang) {
    if (!lang) return;
    var maxAge = 365 * 24 * 60 * 60; // 1 year
    var base = ";path=/;SameSite=Lax;max-age=" + maxAge + (location.protocol === "https:" ? ";secure" : "");

    // Cookie the PHP actually reads:
    document.cookie = "po_preferred_language=" + encodeURIComponent(lang) + base;
  }

  function getCookie(name) {
    if (!name) {
      return "";
    }

    var match = document.cookie.match(new RegExp("(?:^|; )" + name.replace(/[.$?*|{}()\[\]\\\/\+^]/g, "\\$&") + "=([^;]*)"));
    return match ? decodeURIComponent(match[1]) : "";
  }

  // Apply saved/system theme as early as possible to avoid flash
  (function earlyThemePaint() {
    try {
      var saved = getSavedTheme();
      var initial = saved || getSystemTheme() || "light";
      if (html) html.setAttribute("data-theme", initial);
      if (body) {
        removeClassByPrefix(body, "po-theme-");
        body.classList.add("po-theme-" + initial);
      }
    } catch (e) {}
  })();

  function parseReadPosts(value) {
    if (!value) {
      return [];
    }

    if (Array.isArray(value)) {
      return value
        .map(function (item) {
          return parseInt(item, 10);
        })
        .filter(function (num) {
          return !isNaN(num);
        });
    }

    if (typeof value === "string") {
      var trimmed = value.trim();
      if (!trimmed) {
        return [];
      }

      try {
        if (trimmed.charAt(0) === "[") {
          return parseReadPosts(JSON.parse(trimmed));
        }
      } catch (error) {
        // Fall back to simple parsing.
      }

      return trimmed
        .split(",")
        .map(function (part) {
          return parseInt(part, 10);
        })
        .filter(function (num) {
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

  function removeClassByPrefix(element, prefix) {
    if (!element || !element.classList) {
      return;
    }

    var classes = Array.prototype.slice.call(element.classList);
    classes.forEach(function (className) {
      if (className.indexOf(prefix) === 0) {
        element.classList.remove(className);
      }
    });
  }

  function applyTheme(theme, persist) {
    if (!body || !settings) return;

    var normalized = theme === "dark" ? "dark" : "light";
    settings.theme = normalized;

    removeClassByPrefix(body, "po-theme-");
    body.classList.add("po-theme-" + normalized);

    // Apply theme attribute to ALL articles
    themableArticles.forEach(function (article) {
      article.setAttribute("data-theme", normalized);
    });

    if (persist) {
      try {
        window.localStorage.setItem(THEME_KEY, normalized);
      } catch (e) {}
      setCookie(THEME_KEY, normalized, 365 * 24 * 60 * 60);
    }
  }

  function applyFont(language, fontSlug, persist) {
    if (!body || !settings) return;

    var lang = language === "fa" ? "fa" : "en";
    var normalized = fontSlug || "system";
    if (!settings.currentFonts) settings.currentFonts = {};
    settings.currentFonts[lang] = normalized;

    // keep the body class if you like
    removeClassByPrefix(body, "po-font-" + lang + "-");
    if (normalized !== "system") {
      body.classList.add("po-font-" + lang + "-" + normalized);
    }

    // NEW: write the chosen font onto each article so CSS can target it
    themableArticles.forEach(function (article) {
      article.setAttribute("data-font-" + lang, normalized);
    });

    if (persist) {
      setCookie("po_font_" + lang, normalized, settings.cookieMaxAge || 30 * 24 * 60 * 60);
    }
  }

  function initSiteSettings() {
    if (!settings || !body) return;

    // Find ALL site-settings instances
    var containers = document.querySelectorAll(".po-site-settings");
    if (!containers.length) return;

    // Decide initial theme first (global)
    var currentLanguage = settings.currentLanguage || "en";
    var initialTheme = getSavedTheme() || settings.theme || getSystemTheme() || "light";
    applyTheme(initialTheme, false);

    // Apply initial fonts (global per language)
    ["en", "fa"].forEach(function (lang) {
      var fontSlug = settings.currentFonts && settings.currentFonts[lang] ? settings.currentFonts[lang] : "system";
      applyFont(lang, fontSlug, false);
    });

    // Init EACH panel independently
    containers.forEach(function (container) {
      container.setAttribute("data-current-language", currentLanguage);

      var toggle = container.querySelector(".po-site-settings__toggle");
      var panel = container.querySelector(".po-site-settings__panel");

      if (toggle) {
        toggle.addEventListener("mousedown", function () {
          toggle.classList.add("is-pressing");
        });
        ["mouseup", "mouseleave", "blur"].forEach(function (ev) {
          toggle.addEventListener(ev, function () {
            toggle.classList.remove("is-pressing");
          });
        });
      }

      if (toggle && panel) {
        // Toggle open/close
        toggle.addEventListener("click", function (e) {
          var isHidden = panel.hasAttribute("hidden");
          if (isHidden) {
            panel.removeAttribute("hidden");
            toggle.setAttribute("aria-expanded", "true");
            container.classList.add("is-open");
          } else {
            panel.setAttribute("hidden", "hidden");
            toggle.setAttribute("aria-expanded", "false");
            container.classList.remove("is-open");
          }
        });

        // ESC to close (per container)
        container.addEventListener("keydown", function (e) {
          if (e.key === "Escape" && container.classList.contains("is-open")) {
            panel.setAttribute("hidden", "hidden");
            toggle.setAttribute("aria-expanded", "false");
            container.classList.remove("is-open");
            toggle.focus();
          }
        });
      }

      // Theme select (scoped to this container's selects)
      var themeSelect = container.querySelector('[id$="-theme"]') || container.querySelector("#po-site-settings-theme");
      if (!themeSelect && panel) {
        themeSelect = container.querySelector("#" + panel.id.replace("-panel", "-theme"));
      }
      if (themeSelect) {
        themeSelect.value = initialTheme;
        themeSelect.addEventListener("change", function () {
          var val = this.value === "dark" ? "dark" : "light";
          applyTheme(val, true);
        });
      }

      // Font selects (scoped)
      var fontGroups = container.querySelectorAll(".po-site-settings__group");
      var fontSelects = container.querySelectorAll(".po-site-settings__select--font");

      // Set current values
      fontSelects.forEach(function (select) {
        var lang = select.getAttribute("data-language") || "en";
        var cur = settings.currentFonts && settings.currentFonts[lang] ? settings.currentFonts[lang] : "system";
        select.value = cur;
        select.addEventListener("change", function () {
          applyFont(lang, this.value, true);
        });
      });

      function syncFontVisibility(language) {
        fontGroups.forEach(function (group) {
          if (group.getAttribute("data-language") === language) {
            group.removeAttribute("hidden");
          } else {
            group.setAttribute("hidden", "hidden");
          }
        });
      }

      // Font-size controls scoped to THIS container
      (function initFontSizeControlsIn(container) {
        var out = container.querySelector(".po-site-settings__font-size-output");
        var dec = container.querySelector(".po-font-size--decrease");
        var inc = container.querySelector(".po-font-size--increase");
        var reset = container.querySelector(".po-font-size--reset");
        if (!out || !dec || !inc || !reset) return;

        var MIN = 85,
          MAX = 150,
          STEP = 5,
          KEY = "po_font_size_percent";

        function clamp(v) {
          return Math.max(MIN, Math.min(MAX, v));
        }
        function getSaved() {
          var raw = localStorage.getItem(KEY);
          var n = raw ? parseInt(raw, 10) : NaN;
          return Number.isFinite(n) ? clamp(n) : 100;
        }
        function apply(val, persist) {
          document.documentElement.style.setProperty("--po-font-size", val + "%");
          out.textContent = val + "%";
          if (persist !== false) localStorage.setItem(KEY, String(val));
        }

        // initial
        apply(getSaved(), false);

        dec.addEventListener("click", function () {
          apply(clamp(getSaved() - STEP));
        });
        inc.addEventListener("click", function () {
          apply(clamp(getSaved() + STEP));
        });
        reset.addEventListener("click", function () {
          apply(100);
        });

        // Optional: shortcuts while this container is focused/open
        container.addEventListener("keydown", function (e) {
          if (!(e.ctrlKey || e.metaKey)) return;
          if (e.key === "=") apply(clamp(getSaved() + STEP));
          if (e.key === "-") apply(clamp(getSaved() - STEP));
          if (e.key.toLowerCase() === "0") apply(100);
        });
      })(container);

      syncFontVisibility(currentLanguage);
    });

    // Keep your existing language change listener (applies globally)
    document.addEventListener("poLangChange", function (e) {
      var lang = e.detail && e.detail.lang ? e.detail.lang : "en";
      setLangCookie(lang);

      // Update all panels’ language state + visible group
      document.querySelectorAll(".po-site-settings").forEach(function (container) {
        container.setAttribute("data-current-language", lang);

        var groups = container.querySelectorAll(".po-site-settings__group");
        groups.forEach(function (group) {
          if (group.getAttribute("data-language") === lang) group.removeAttribute("hidden");
          else group.setAttribute("hidden", "hidden");
        });
      });

      // Re-apply stored font for that language
      var chosen = settings.currentFonts && settings.currentFonts[lang] ? settings.currentFonts[lang] : "system";
      applyFont(lang, chosen, false);
    });
  }

  // ONE global outside-click handler for all instances
  document.addEventListener("click", function (e) {
    var openContainers = document.querySelectorAll(".po-site-settings.is-open");
    openContainers.forEach(function (c) {
      if (!c.contains(e.target)) {
        var t = c.querySelector(".po-site-settings__toggle");
        var p = c.querySelector(".po-site-settings__panel");
        if (p && t) {
          p.setAttribute("hidden", "hidden");
          t.setAttribute("aria-expanded", "false");
          c.classList.remove("is-open");
        }
      }
    });
  });

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
      bar.setAttribute("data-read", readCount);
      bar.setAttribute("data-total", total);

      var percentEl = bar.querySelector(".po-progress-bar__percent");
      if (percentEl) {
        percentEl.textContent = percentage + "%";
      }

      var track = bar.querySelector(".po-progress-bar__track");
      if (track) {
        track.setAttribute("aria-valuenow", percentage);
      }

      var fill = bar.querySelector(".po-progress-bar__fill");
      if (fill) {
        fill.style.width = percentage + "%";
      }

      var countsEl = bar.querySelector(".po-progress-bar__counts");
      if (countsEl) {
        var numbersEl = countsEl.querySelector(".po-progress-bar__numbers");
        if (numbersEl) {
          numbersEl.textContent = readCount + " / " + total;
        } else {
          countsEl.textContent = readCount + " / " + total;
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

      var maxAge = progress.maxAge || 30 * 24 * 60 * 60;
      var totals = progress.totals || {};
      var initialReadCounts = progress.readCounts || {};

      progress.categories.forEach(function (categoryIdRaw) {
        var categoryId = parseInt(categoryIdRaw, 10);
        if (isNaN(categoryId)) {
          return;
        }

        var lastKey = "po_last_read_" + categoryId;
        var readKey = "po_read_posts_" + categoryId;
        var storedRead = "";

        try {
          storedRead = window.localStorage.getItem(readKey) || "";
        } catch (storageError) {
          storedRead = "";
        }

        var readPosts = parseReadPosts(storedRead);
        if (!readPosts.length) {
          readPosts = parseReadPosts(getCookie(readKey));
        }

        if (postId && readPosts.indexOf(postId) === -1) {
          readPosts.push(postId);
        }

        readPosts = uniqueInts(readPosts);

        var maxEntries = typeof window.persianOriginsGuestLimit === "number" ? window.persianOriginsGuestLimit : 200;
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

        setCookie(readKey, readPosts.join(","), maxAge);

        var total = parseInt(totals[categoryId], 10);
        if (isNaN(total)) {
          total = 0;
        }

        var readCount = readPosts.length;
        if (!readCount && typeof initialReadCounts[categoryId] !== "undefined") {
          readCount = parseInt(initialReadCounts[categoryId], 10) || 0;
        }

        updateProgressBars(categoryId, readCount, total);
      });
    } catch (error) {
      // Fail silently.
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initSiteSettings);
  } else {
    initSiteSettings();
  }
})(window, document);
