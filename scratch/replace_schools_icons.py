import os

files = [
    r"c:\xampp\htdocs\schoolerp\modules\admin\schools.php",
    r"c:\xampp\htdocs\schoolerp\modules\admin\schools-edit.php"
]

replacements = {
    'ph-bold ph-plus': 'ti ti-plus',
    'ph-light ph-plus-circle': 'ti ti-circle-plus',
    'ph-light ph-magnifying-glass': 'ti ti-search',
    'ph-light ph-buildings': 'ti ti-building',
    'ph-light ph-bank': 'ti ti-building-bank',
    'ph-light ph-pencil-simple': 'ti ti-pencil',
    'ph-light ph-caret-down': 'ti ti-chevron-down',
    'ph-light ph-trash': 'ti ti-trash',
    'ph-light ph-arrow-left': 'ti ti-arrow-left',
    'ph-light ph-floppy-disk': 'ti ti-device-floppy',
    'ph-light ph-eye-slash': 'ti ti-eye-off',
    'ph-light ph-eye': 'ti ti-eye',
    'ph-light ph-user-check': 'ti ti-user-check',
    'ph-light ph-key': 'ti ti-key',
    'ph-light ph-calendar': 'ti ti-calendar',
    'ph-light ph-envelope-simple': 'ti ti-mail',
    'ph-light ph-phone': 'ti ti-phone'
}

for filepath in files:
    if os.path.exists(filepath):
        with open(filepath, "r", encoding="utf-8") as f:
            content = f.read()
        for ph, ti in replacements.items():
            content = content.replace(ph, ti)
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Replaced icons in {filepath}")

print("Super Admin school pages replacement complete successfully!")
