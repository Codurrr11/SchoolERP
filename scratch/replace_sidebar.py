import re

filepath = r"c:\xampp\htdocs\schoolerp\includes\sidebar.php"

with open(filepath, "r", encoding="utf-8") as f:
    content = f.read()

# Replace Phosphor classes with Tabler outline classes
replacements = {
    'ph-light ph-x': 'ti ti-x',
    'ph-light ph-house': 'ti ti-home',
    'ph-light ph-buildings': 'ti ti-building',
    'ph-light ph-list': 'ti ti-list',
    'ph-light ph-plus-circle': 'ti ti-circle-plus',
    'ph-light ph-sign-out': 'ti ti-logout',
    'ph-light ph-address-book': 'ti ti-address-book',
    'ph-light ph-users': 'ti ti-users',
    'ph-light ph-user-check': 'ti ti-user-check',
    'ph-light ph-link': 'ti ti-link',
    'ph-light ph-activity': 'ti ti-activity',
    'ph-light ph-notebook': 'ti ti-notebook',
    'ph-light ph-file-spreadsheet': 'ti ti-file-spreadsheet',
    'ph-light ph-printer': 'ti ti-printer',
    'ph-light ph-graduation-cap': 'ti ti-school',
    'ph-light ph-tag': 'ti ti-tag',
    'ph-light ph-chalkboard-teacher': 'ti ti-presentation',
    'ph-light ph-book-open': 'ti ti-book-open',
    'ph-light ph-coins': 'ti ti-coins',
    'ph-light ph-heart': 'ti ti-heart',
    'ph-light ph-hand-coins': 'ti ti-cash',
    'ph-light ph-arrows-clockwise': 'ti ti-refresh',
    'ph-light ph-receipt': 'ti ti-receipt',
    'ph-light ph-chart-pie': 'ti ti-chart-pie',
    'ph-light ph-chart-line': 'ti ti-chart-line',
    'ph-light ph-note-pencil': 'ti ti-edit',
    'ph-light ph-desktop': 'ti ti-desktop',
    'ph-light ph-tree-structure': 'ti ti-sitemap',
    'ph-light ph-chart-bar': 'ti ti-chart-bar',
    'ph-light ph-user-focus': 'ti ti-user-circle',
    'ph-light ph-bus': 'ti ti-bus',
    'ph-light ph-credit-card': 'ti ti-credit-card',
    'ph-light ph-gear': 'ti ti-settings',
    'ph-light ph-device-mobile': 'ti ti-device-mobile',
    'ph-light ph-calendar-check': 'ti ti-calendar-check',
    'ph-light ph-calendar': 'ti ti-calendar',
    'ph-light ph-scroll': 'ti ti-scroll',
    'ph-light ph-file-text': 'ti ti-file-text',
    'ph-light ph-scales': 'ti ti-scale',
    'ph-light ph-trend-up': 'ti ti-trending-up',
    'ph-light ph-wallet': 'ti ti-wallet',
    'ph-light ph-chat-text': 'ti ti-message',
    'ph-light ph-megaphone': 'ti ti-megaphone',
    'ph-light ph-whatsapp-logo': 'ti ti-brand-whatsapp',
    'ph-light ph-sliders': 'ti ti-sliders',
    'ph-light ph-history': 'ti ti-history',
    'ph-light ph-bell': 'ti ti-bell',
    'ph-light ph-identification-card': 'ti ti-id',
    'ph-light ph-list-checks': 'ti ti-list-check',
    'ph-light ph-book-bookmark': 'ti ti-bookmark',
    'ph-light ph-door': 'ti ti-door-enter',
    'ph-light ph-key': 'ti ti-key',
    'ph-light ph-clipboard': 'ti ti-clipboard',
    'ph-light ph-percent': 'ti ti-percentage',
    'ph-light ph-pen': 'ti ti-pencil',
    'ph-light ph-medal': 'ti ti-medal',
    'ph-light ph-shield': 'ti ti-shield',
    'ph-light ph-arrows-left-right': 'ti ti-arrows-left-right',
    'ph-light ph-file-arrow-up': 'ti ti-file-upload',
    'ph-light ph-folder-open': 'ti ti-folder-open',
    'ph-light ph-upload': 'ti ti-upload',
    'ph-light ph-image': 'ti ti-photo',
    'ph-light ph-calendar-blank': 'ti ti-calendar-event',
    'ph-light ph-calendar-x': 'ti ti-calendar-off',
    'ph-light ph-chat-centered-dots': 'ti ti-messages',
    'ph-light ph-ticket': 'ti ti-ticket',
    'ph-light ph-database': 'ti ti-database',
    'ph-light ph-grid-four': 'ti ti-grid-pattern',
    'ph-light ph-user-gear': 'ti ti-user-cog',
    'ph-light ph-caret-down': 'ti ti-chevron-down',
    'ph-light ph-briefcase': 'ti ti-briefcase',
    'ph-light ph-shield-check': 'ti ti-shield-check'
}

for ph, ti in replacements.items():
    content = content.replace(ph, ti)

# Adjust branding block
brand_target = """    <!-- Mobile Close & Logo Header -->
    <div class="sidebar-brand-header">
        <div class="d-flex align-items-center gap-2">
            <div class="sidebar-brand-logo">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="24" height="24" rx="6" fill="#FFFFFF" />
                    <path d="M6 18L18 6" stroke="var(--color-accent)" stroke-width="4" stroke-linecap="round" />
                    <path d="M11 18L18 11" stroke="var(--color-accent)" stroke-width="3" stroke-linecap="round" />
                </svg>
            </div>
            <span class="sidebar-brand-text">SchoolSaaS</span>
        </div>
        <button type="button" id="sidebarCloseBtn" aria-label="Close Navigation">
            <i class="ti ti-x class fs-5"></i>
        </button>
    </div>"""

brand_replacement = """    <!-- Mobile Close & Logo Header -->
    <div class="sidebar-brand-header">
        <div class="d-flex align-items-center gap-2 py-2">
            <div class="sidebar-brand-logo">
                <i class="ti ti-school fs-4 text-white"></i>
            </div>
            <span class="sidebar-brand-text">SchoolSaaS</span>
        </div>
        <button type="button" id="sidebarCloseBtn" aria-label="Close Navigation">
            <i class="ti ti-x fs-5"></i>
        </button>
    </div>
    <div class="sidebar-brand-divider"></div>"""

content = content.replace(brand_target, brand_replacement)

# Also fix the close icon class syntax error if there was one (class typo in ph-x button line 17: class="ti ti-x class fs-5" -> class="ti ti-x fs-5")
content = content.replace('class="ti ti-x class fs-5"', 'class="ti ti-x fs-5"')

with open(filepath, "w", encoding="utf-8") as f:
    f.write(content)

print("Replacement complete successfully!")
