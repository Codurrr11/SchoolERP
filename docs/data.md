


## School SaaS Development Rules

We are building a School ERP / School Management SaaS similar to Vedmarg using PHP, MySQL, HTML, CSS, and JavaScript.

When I provide screenshots, page names, files, or tasks:

- Scan the attached image and identify all UI elements, tables, forms, buttons, filters, actions, fields, modals, tabs, and features.
- Recreate the same functionality in our project while following our existing UI, color palette, typography, spacing, components, coding style, and folder structure.
- First check existing CSS, JS, libraries, CDNs, icons, alerts, modals, animations, and reusable components already available in the project and use them wherever possible.
- Do not introduce a different design system.

## Development Rules

- Work only on the files I mention.
- Do not read unrelated files.
- Do not scan the entire project.
- Do not perform extra work.
- Do not add features I did not request.
- Do not explain what you are doing.
- Do not waste time with analysis reports.
- Directly implement the task.

## CSS Rules

- Never use inline CSS.
- Never use style tags.
- Use existing project CSS files.
- If new CSS is required, append it to the existing CSS file.
- Do not overwrite existing styles.
- Ensure new styles do not affect existing pages or components.

## JavaScript Rules

- Never use inline JavaScript.
- Use existing project JS files.
- If new JS is required, append it to the existing JS file.
- Reuse existing alert, modal, toast, confirmation, validation, and interaction patterns.

## Database Rules

- SQLTools is connected.
- Check existing tables before creating new ones.
- Create, alter, update, or optimize database structures only when required for the feature.
- Reuse existing relationships and conventions.
- Do not modify unrelated tables.

## Functionality Rules

Every feature must be fully working:

- Add
- Edit
- Delete
- Restore (if applicable)
- Search
- Filter
- Pagination
- Validation
- Status Toggle
- Form Submission
- Data Fetching

Use the same alerts, confirmations, toasts, loaders, and response handling already used elsewhere in the project.

## Security Rules

- Never change login credentials.
- Never modify authentication logic unless explicitly instructed.
- Never hardcode passwords or credentials.

## Output Rules

After completing the task:

- Provide only modified files.
- Provide SQL queries if database changes were required.
- Keep code clean, efficient, and production-ready.
- Ensure no existing functionality breaks.

If any requirement is unclear, stop immediately and ask a short clarification question.
