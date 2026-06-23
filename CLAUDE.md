- **Working Directory:** `schoolerp/`
- **Tech Stack:** Core PHP, Custom CSS, Core JS, Bootstrap, PDO

# Reference Blueprints (Locate these first)

To understand our UI structure, layout, syntax, and coding standards, always reference these paths:

- **PHP/UI Blueprint:** Read files inside `/modules/school/`
- **CSS Styles:** `assets/css/main.css` and `assets/css/responsive.css`
- **JS Logic:** `assets/js/app.js`

# Architectural & Navigation Rules

- **Sidebar Integration:** Whenever you create a new page or feature, always add its link/tab to the appropriate required section in the sidebar.
- **No Refactoring:** Never refactor unrelated code. Do not touch other pages' logic.
- **UI Consistency:** Strictly match existing project UI components, tables, modals, colors, and coding standards. Do not redesign.
- **Workflow:** Never stop for confirmation. If backend logic is missing or unclear, make a logical assumption, insert a `// TODO: [context]` comment, and continue building the feature.

# Frontend Strict Standards

- **CSS (Strict Reuse):** Almost all CSS for tables, forms, buttons, and layout is ALREADY written. NO inline CSS or `<style>` tags. You MUST use existing Bootstrap and custom classes. Safely append new styles to `main.css` ONLY if it is absolutely unavoidable.
- **JavaScript:** NO inline JS. Safely append to `app.js`. Always reuse existing alert, modal, toast, confirmation, and validation patterns.

# MCP & Token Optimization Rules

- **Targeted Reads:** Never scan massive directories. Rely on the "Reference Blueprints" above.
- **Concise Outputs:** When modifying files via MCP, output only a brief summary of changed files and any `TODO` action items. Do not output the entire code block in the chat.

