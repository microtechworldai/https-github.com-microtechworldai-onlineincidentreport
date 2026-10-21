        </div><!-- /.app-content -->
    </main><!-- /.app-main -->

</div><!-- /.app-container -->

<script>
// Sidebar Toggle on Mobile
function toggleSidebar() {
    const sidebar = document.getElementById('appSidebar');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
}

// Theme Switcher Logic (Opens 10-theme palette)
function toggleAppTheme() {
    if (typeof openThemePaletteModal === 'function') {
        openThemePaletteModal();
    }
}

// Live UAE / GST Clock
function updateClock() {
    const clockEl = document.getElementById('liveClock');
    if (!clockEl) return;
    try {
        const now = new Date();
        const options = { timeZone: '<?= htmlspecialchars($settings['timezone'] ?? 'Asia/Dubai') ?>', hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' };
        clockEl.textContent = 'GST ' + new Intl.DateTimeFormat('en-GB', options).format(now);
    } catch (e) {
        clockEl.textContent = new Date().toTimeString().split(' ')[0];
    }
}
setInterval(updateClock, 1000);
updateClock();
</script>

<?php require_once __DIR__ . '/theme_picker.php'; ?>
</body>
</html>
