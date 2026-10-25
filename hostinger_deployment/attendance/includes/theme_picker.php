<?php
/**
 * Universal Theme Picker Component (3-Way: Light, Dark, System)
 * High Contrast WCAG 2.2 AA Conforming Selector
 */
?>
<div id="themePickerModal" class="theme-modal-backdrop" style="display: none; position: fixed; inset: 0; background: var(--modal-overlay); z-index: 99999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; max-width: 380px; width: 90%; box-shadow: var(--card-shadow); text-align: center;">
        <h3 style="margin-top: 0; font-size: 18px; color: var(--heading); font-weight: 600;">Theme Appearance</h3>
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Choose your preferred display theme for this device.</p>
        
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <button type="button" onclick="setUssTheme('light')" class="btn" style="background: #FFFFFF; color: #1E242E; border: 1px solid #C9D1DB; padding: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span>☀️</span> Light Theme
            </button>
            <button type="button" onclick="setUssTheme('dark')" class="btn" style="background: #172024; color: #E6EEF0; border: 1px solid #2F3E44; padding: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span>🌙</span> Dark Theme
            </button>
            <button type="button" onclick="setUssTheme('system')" class="btn" style="background: var(--surface-2); color: var(--text); border: 1px solid var(--border); padding: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span>💻</span> Match System Device
            </button>
        </div>

        <button type="button" onclick="closeThemePickerModal()" style="margin-top: 18px; background: none; border: none; color: var(--text-muted); font-size: 13px; cursor: pointer; text-decoration: underline;">
            Close
        </button>
    </div>
</div>

<script>
function openThemePaletteModal() {
    var m = document.getElementById('themePickerModal');
    if (m) m.style.display = 'flex';
}

function closeThemePickerModal() {
    var m = document.getElementById('themePickerModal');
    if (m) m.style.display = 'none';
}

function setUssTheme(mode) {
    localStorage.setItem('uss_theme', mode);
    var actual = mode;
    if (mode === 'system') {
        actual = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    document.documentElement.setAttribute('data-theme', actual);
    document.documentElement.setAttribute('data-theme-preference', mode);
    closeThemePickerModal();
}
</script>
