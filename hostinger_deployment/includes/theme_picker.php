<?php
/**
 * 10 Master Project Themes Component
 * Faithfully inspired by modern typography and color palette cards:
 * 01: BLACK / DARK
 * 02: WHITE / LIGHT
 * 03: NAVY BLUE
 * 04: ROYAL BLUE
 * 05: DEEP GREEN
 * 06: PURPLE
 * 07: RED
 * 08: ORANGE
 * 09: YELLOW
 * 10: SOFT PINK
 */

$ussThemesList = [
    [
        'id' => 'black-dark',
        'alias' => 'dark',
        'num' => '01',
        'title' => 'BLACK / DARK',
        'card_bg' => '#0e131d',
        'outer_bg' => '#000000',
        'headline' => '#FFFFFF',
        'subtext' => '#B8B8B8',
        'accent' => '#FFD43B',
        'is_dark_text' => false
    ],
    [
        'id' => 'white-light',
        'alias' => 'light',
        'num' => '02',
        'title' => 'WHITE / LIGHT',
        'card_bg' => '#FFFFFF',
        'outer_bg' => '#F8FAFC',
        'headline' => '#111111',
        'subtext' => '#666666',
        'accent' => '#2563EB',
        'is_dark_text' => true
    ],
    [
        'id' => 'navy-blue',
        'alias' => 'navy-blue',
        'num' => '03',
        'title' => 'NAVY BLUE',
        'card_bg' => '#0E223D',
        'outer_bg' => '#081426',
        'headline' => '#FFFFFF',
        'subtext' => '#C9D6E3',
        'accent' => '#FFD166',
        'is_dark_text' => false
    ],
    [
        'id' => 'royal-blue',
        'alias' => 'royal-blue',
        'num' => '04',
        'title' => 'ROYAL BLUE',
        'card_bg' => '#1D4ED8',
        'outer_bg' => '#143599',
        'headline' => '#FFFFFF',
        'subtext' => '#DCE6FF',
        'accent' => '#FFE45E',
        'is_dark_text' => false
    ],
    [
        'id' => 'deep-green',
        'alias' => 'deep-green',
        'num' => '05',
        'title' => 'DEEP GREEN',
        'card_bg' => '#064E3B',
        'outer_bg' => '#043628',
        'headline' => '#FFFFFF',
        'subtext' => '#C7DED4',
        'accent' => '#F4C95D',
        'is_dark_text' => false
    ],
    [
        'id' => 'purple',
        'alias' => 'purple',
        'num' => '06',
        'title' => 'PURPLE',
        'card_bg' => '#4C1D95',
        'outer_bg' => '#331063',
        'headline' => '#FFFFFF',
        'subtext' => '#E3D9F0',
        'accent' => '#FFD166',
        'is_dark_text' => false
    ],
    [
        'id' => 'red',
        'alias' => 'red',
        'num' => '07',
        'title' => 'RED',
        'card_bg' => '#7F1D1D',
        'outer_bg' => '#570F0F',
        'headline' => '#FFFFFF',
        'subtext' => '#F8D5D2',
        'accent' => '#FFE066',
        'is_dark_text' => false
    ],
    [
        'id' => 'orange',
        'alias' => 'orange',
        'num' => '08',
        'title' => 'ORANGE',
        'card_bg' => '#C2410C',
        'outer_bg' => '#852B05',
        'headline' => '#FFFFFF',
        'subtext' => '#FFE4C7',
        'accent' => '#17202A',
        'is_dark_text' => false
    ],
    [
        'id' => 'yellow',
        'alias' => 'yellow',
        'num' => '09',
        'title' => 'YELLOW',
        'card_bg' => '#EAB308',
        'outer_bg' => '#B88302',
        'headline' => '#111111',
        'subtext' => '#4A4030',
        'accent' => '#FFFFFF',
        'is_dark_text' => true
    ],
    [
        'id' => 'soft-pink',
        'alias' => 'soft-pink',
        'num' => '10',
        'title' => 'SOFT PINK',
        'card_bg' => '#E89AA8',
        'outer_bg' => '#C96F80',
        'headline' => '#3A1823',
        'subtext' => '#694653',
        'accent' => '#FFFFFF',
        'is_dark_text' => true
    ]
];
?>

<!-- Universal Theme Palette Modal Component -->
<div id="ussThemePaletteModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.78); backdrop-filter: blur(8px); z-index: 999999; align-items: center; justify-content: center; padding: 16px;">
    <div style="background: #0f172a; border: 1px solid rgba(255,255,255,0.15); border-radius: 16px; max-width: 820px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 60px -15px rgba(0,0,0,0.8); animation: themeModalPop 0.2s ease-out;">
        
        <!-- Modal Header -->
        <div style="padding: 18px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 22px;">🎨</span>
                <div>
                    <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #ffffff;">Choose Interface Theme</h3>
                    <p style="margin: 2px 0 0 0; font-size: 12.5px; color: #94a3b8;">10 Project Color Palettes with automatic background and typography matching</p>
                </div>
            </div>
            <button type="button" onclick="closeThemePaletteModal()" style="background: none; border: none; color: #94a3b8; font-size: 26px; cursor: pointer; padding: 0 4px; line-height: 1;">&times;</button>
        </div>

        <!-- 10 Themes Grid (Scrollable) -->
        <div style="padding: 20px 24px; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 14px;">
            <?php foreach ($ussThemesList as $t): ?>
                <div class="theme-choice-card" id="theme_card_<?= $t['id'] ?>" onclick="applyUserProjectTheme('<?= $t['id'] ?>')" style="background: <?= $t['card_bg'] ?>; border: 2px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 16px; cursor: pointer; position: relative; transition: all 0.2s ease; box-shadow: 0 4px 14px rgba(0,0,0,0.25);">
                    
                    <!-- Top Ribbon: Number + Title + Accent Circle -->
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="background: <?= $t['is_dark_text'] ? '#111111' : '#ffffff' ?>; color: <?= $t['is_dark_text'] ? '#ffffff' : '#000000' ?>; font-weight: 800; font-size: 11px; padding: 2px 7px; border-radius: 20px; font-family: monospace;">
                                <?= $t['num'] ?>
                            </span>
                            <span style="font-size: 12.5px; font-weight: 800; color: <?= $t['headline'] ?>; letter-spacing: 0.5px; text-transform: uppercase;">
                                <?= $t['title'] ?>
                            </span>
                        </div>
                        
                        <!-- Accent Preview -->
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="display: inline-block; width: 14px; height: 14px; border-radius: 50%; background: <?= $t['accent'] ?>; border: 1.5px solid <?= $t['is_dark_text'] ? 'rgba(0,0,0,0.3)' : 'rgba(255,255,255,0.5)' ?>; box-shadow: 0 0 6px <?= $t['accent'] ?>;"></span>
                            <span style="font-size: 10px; font-weight: 700; color: <?= $t['subtext'] ?>; font-family: monospace;"><?= $t['accent'] ?></span>
                        </div>
                    </div>

                    <!-- Visual Headline & Subtext Sample -->
                    <div style="margin-bottom: 12px; padding: 4px 0;">
                        <div style="font-size: 20px; font-weight: 800; color: <?= $t['headline'] ?>; line-height: 1.2; letter-spacing: -0.3px;">
                            Big Headline
                        </div>
                        <div style="font-size: 13px; font-weight: 500; color: <?= $t['subtext'] ?>; margin-top: 2px;">
                            Subtext goes here.
                        </div>
                    </div>

                    <!-- Bottom Metadata & Active Indicator -->
                    <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid <?= $t['is_dark_text'] ? 'rgba(0,0,0,0.12)' : 'rgba(255,255,255,0.12)' ?>; padding-top: 8px;">
                        <div style="font-size: 10.5px; font-family: monospace; color: <?= $t['subtext'] ?>;">
                            <span>Text: <?= $t['headline'] ?></span> &bull; <span>Sub: <?= $t['subtext'] ?></span>
                        </div>
                        <span class="theme-active-tag" id="tag_active_<?= $t['id'] ?>" style="display: none; background: #10b981; color: #ffffff; font-weight: 800; font-size: 10.5px; padding: 2px 8px; border-radius: 12px; letter-spacing: 0.5px;">
                            ✓ ACTIVE
                        </span>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <!-- Modal Footer -->
        <div style="padding: 14px 24px; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.25); display: flex; align-items: center; justify-content: space-between; border-radius: 0 0 16px 16px;">
            <div style="font-size: 12px; color: #94a3b8;">
                Theme applies instantly and saves automatically across all sessions.
            </div>
            <button type="button" onclick="closeThemePaletteModal()" class="btn btn-secondary btn-sm" style="padding: 6px 14px;">
                Close
            </button>
        </div>

    </div>
</div>

<style>
@keyframes themeModalPop {
    0% { transform: scale(0.96); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}

.theme-choice-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(0,0,0,0.45) !important;
    border-color: #38bdf8 !important;
}

.theme-choice-card.is-selected {
    border-color: #10b981 !important;
    box-shadow: 0 0 0 2px #10b981, 0 10px 24px rgba(0,0,0,0.4) !important;
}
</style>

<script>
// Normalize legacy or alias themes to the 10 core IDs
function normalizeThemeName(theme) {
    if (!theme) return 'black-dark';
    if (theme === 'dark') return 'black-dark';
    if (theme === 'light') return 'white-light';
    return theme;
}

function openThemePaletteModal() {
    const modal = document.getElementById('ussThemePaletteModal');
    if (!modal) return;
    
    // Highlight currently active theme
    const currentTheme = normalizeThemeName(localStorage.getItem('uss_theme') || document.documentElement.getAttribute('data-theme') || 'black-dark');
    
    document.querySelectorAll('.theme-choice-card').forEach(el => el.classList.remove('is-selected'));
    document.querySelectorAll('.theme-active-tag').forEach(el => el.style.display = 'none');
    
    const activeCard = document.getElementById('theme_card_' + currentTheme);
    const activeTag = document.getElementById('tag_active_' + currentTheme);
    if (activeCard) activeCard.classList.add('is-selected');
    if (activeTag) activeTag.style.display = 'inline-block';

    modal.style.display = 'flex';
}

function closeThemePaletteModal() {
    const modal = document.getElementById('ussThemePaletteModal');
    if (modal) modal.style.display = 'none';
}

function applyUserProjectTheme(themeId) {
    const normalized = normalizeThemeName(themeId);
    document.documentElement.setAttribute('data-theme', normalized);
    try {
        localStorage.setItem('uss_theme', normalized);
    } catch(e) {}

    // Update active badges
    document.querySelectorAll('.theme-choice-card').forEach(el => el.classList.remove('is-selected'));
    document.querySelectorAll('.theme-active-tag').forEach(el => el.style.display = 'none');
    
    const activeCard = document.getElementById('theme_card_' + normalized);
    const activeTag = document.getElementById('tag_active_' + normalized);
    if (activeCard) activeCard.classList.add('is-selected');
    if (activeTag) activeTag.style.display = 'inline-block';

    // Update trigger labels if present
    updateThemeTriggerLabel(normalized);

    // Auto-close after subtle confirmation
    setTimeout(closeThemePaletteModal, 280);
}

function updateThemeTriggerLabel(themeId) {
    const labelEl = document.getElementById('activeThemeNameLabel');
    if (labelEl) {
        const themeTitles = {
            'black-dark': 'Dark',
            'white-light': 'Light',
            'navy-blue': 'Navy',
            'royal-blue': 'Royal Blue',
            'deep-green': 'Green',
            'purple': 'Purple',
            'red': 'Red',
            'orange': 'Orange',
            'yellow': 'Yellow',
            'soft-pink': 'Soft Pink'
        };
        labelEl.textContent = themeTitles[themeId] || 'Theme';
    }
}

// Close modal on Escape or backdrop click
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeThemePaletteModal();
});
document.getElementById('ussThemePaletteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeThemePaletteModal();
});

// Run once on load to sync trigger
(function() {
    const saved = normalizeThemeName(localStorage.getItem('uss_theme') || 'black-dark');
    document.documentElement.setAttribute('data-theme', saved);
    updateThemeTriggerLabel(saved);
})();
</script>
