import re

filepath = r"c:\xampp\htdocs\schoolerp\index.php"

with open(filepath, "r", encoding="utf-8") as f:
    content = f.read()

# Replace Phosphor classes with Tabler outline classes
replacements = {
    'ph-light ph-users': 'ti ti-users',
    'ph-light ph-chalkboard-teacher': 'ti ti-presentation',
    'ph-light ph-coins': 'ti ti-coins',
    'ph-light ph-warning-circle': 'ti ti-alert-circle',
    'ph-light ph-clock': 'ti ti-clock',
    'ph-light ph-caret-down': 'ti ti-chevron-down',
    'ph-light ph-plus-circle': 'ti ti-circle-plus',
    'ph-light ph-bus': 'ti ti-bus',
    'ph-light ph-house-line': 'ti ti-home',
    'ph-light ph-magnifying-glass': 'ti ti-search',
    'ph-light ph-sliders-horizontal': 'ti ti-adjustments-horizontal',
    'ph-light ph-graduation-cap': 'ti ti-school',
    'ph-light ph-dots-three': 'ti ti-dots',
    'ph-light ph-plus': 'ti ti-plus',
    'ph-light ph-wifi-high': 'ti ti-wifi',
    'ph-bold ph-arrow-up-right': 'ti ti-arrow-up-right',
    'ph-bold ph-arrow-down-right': 'ti ti-arrow-down-right',
    'ph-light ph-check-circle': 'ti ti-circle-check',
    'ph-light ph-buildings': 'ti ti-building',
    'ph-light ph-shield-check': 'ti ti-shield-check',
    'ph-light ph-sign-out': 'ti ti-logout',
    'ph-light ph-list': 'ti ti-list'
}

for ph, ti in replacements.items():
    content = content.replace(ph, ti)

with open(filepath, "w", encoding="utf-8") as f:
    f.write(content)

print("Dashboard icons replacement complete successfully!")
