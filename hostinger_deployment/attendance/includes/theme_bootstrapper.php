<?php
/**
 * Zero-Flash Anti-Flicker Theme Bootstrapper
 * Defaults to luxurious Forest Green / Crisp White Light Theme inspired by UAE Enterprise Spec
 */
?>
<script>
(function() {
    try {
        var savedTheme = localStorage.getItem('uss_theme');
        var theme = savedTheme || 'light';
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme-preference', savedTheme || 'light');
    } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
    }
})();
</script>
