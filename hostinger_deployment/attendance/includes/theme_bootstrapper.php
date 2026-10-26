<?php
/**
 * Zero-Flash Anti-Flicker Theme Bootstrapper
 * Injected in <head> to synchronously set data-theme before first paint.
 */
?>
<script>
(function() {
    try {
        var savedTheme = localStorage.getItem('uss_theme');
        var theme = savedTheme;
        if (!theme || theme === 'system') { theme = 'dark'; }
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme-preference', savedTheme || 'dark');
    } catch (e) {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
})();
</script>
