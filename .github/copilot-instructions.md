# Copilot Instructions

These instructions are for GitHub Copilot to understand and follow specific rules when working with this project.

## Core Rules

### 1. **Use Vue.js instead of Blade**
- **Mandatory:** Always use Vue.js components and syntax
- **Forbidden:** Do not use Blade templates (.blade.php files)
- **Exception:** Only for system Laravel files like layouts, if absolutely necessary

### 2. **Language Files**
- **Mandatory:** Always use language files with the current separate file structure
- **Structure:** `resources/lang/{locale}/{section}.php`
- **Examples:**
  - `resources/lang/en/home.php` - for the home page
  - `resources/lang/bg/global.php` - for global translations
- **Forbidden:** Hardcoded texts in components

### 3. **Build and Development Commands**
- **Forbidden:** Do not run `npm run build` or `npm run dev`, except if very necessary
- **Forbidden:** Do not run `php artisan serve`
- **Explanation:** These commands are started at the beginning and run continuously

## Additional Recommendations (from Copilot)

### 4. **Code Styles**
- Follow existing code styles in the project
- Use consistent naming (camelCase for JavaScript, kebab-case for Vue components)
- Add appropriate comments for complex logic

### 5. **Vue.js Best Practices**
- Use Composition API with `<script setup>` when possible
- Properly organize reactive data with `ref()` and `reactive()`
- Use `inject()` for global dependencies like `$t` for translations

### 6. **Language Support**
- Always check if corresponding translations exist before adding new text
- If adding new text, create translations in both English and Bulgarian
- Use `$t('section.key')` syntax

### 7. **Testing**
- Before finalizing changes, verify they work correctly
- Special attention to language switching and reactivity

### 8. **File Structure**
- Maintain logical file organization
- Vue components in `resources/js/components/`
- Stores in `resources/js/stores/`
- Translations in `resources/lang/`

### 9. **Git Practices**
- Make meaningful commit messages
- If necessary, create separate branches for large changes

### 10. **Performance**
- Use lazy loading for heavy components if necessary
- Optimize images and assets
- Avoid unnecessary re-renders in Vue

## Project-Specific

### 11. **Global Online Game**
- **Time Synchronization:** This is a worldwide online game, so date/time conflicts are critical
- **UTC Standard:** Always use UTC timestamps for all date operations
- **Consistent Experience:** Everything must work synchronously regardless of user's location
- **Timezone Handling:** Store dates in UTC, convert to local time only for display

### 12. **Security & Anti-Cheating**
- **Backend Validation:** Never trust frontend data - always validate everything on backend
- **Cheat Prevention:** Implement solid backend checks for all operations and records
- **Input Sanitization:** Validate, sanitize, and verify all user inputs
- **Secure Storage:** Never store sensitive game data on frontend

### 13. **Performance & Server Load**
- **Optimization Priority:** Always seek the most optimal and lightweight approach
- **Server Load:** Avoid unnecessary server strain - minimize database queries and computations
- **Fast Response:** Focus on speed and minimal delays for better user experience
- **Caching:** Use appropriate caching strategies when possible
- **Efficient Queries:** Write optimized database queries, avoid N+1 problems

### 14. **UI/UX**
- Use CoreUI components for consistency
- Maintain responsive design
- Add appropriate loading states and error handling

## How to Use These Instructions

When working with this project, always consult these instructions. If you have doubts about any rule, ask the user for clarification. These instructions can be updated over time according to project needs.

