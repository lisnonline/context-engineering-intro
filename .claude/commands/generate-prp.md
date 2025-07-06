# Create PRP

## Feature file: $ARGUMENTS
Generate a complete PRP for general WordPress plugin feature implementation with thorough research. Ensure context is passed to the AI agent to enable self-validation and iterative refinement. Read the feature file first to understand what needs to be created, how the examples provided help, and any other considerations.
The AI agent only gets the context you are appending to the PRP and training data. Assume the AI agent has access to the codebase and the same knowledge cutoff as you, so it's important that your research findings are included or referenced in the PRP. The Agent has Websearch capabilities, so pass URLs to documentation and examples.

## Research Process

1. Codebase Analysis
- Search for similar features/patterns in the plugin codebase
- Identify PHP classes, JavaScript modules to reference in PRP
- Note existing WordPress hooks and filters usage
- Check PHPUnit test patterns for validation approach
- Review existing AJAX handlers and REST API endpoints

2. External Research

- Search for similar WordPress features/patterns online
- WordPress Codex and Developer documentation (include specific URLs)
- Implementation examples (WordPress.org plugins, GitHub, StackExchange)
- Best practices for WordPress coding standards
- JavaScript library documentation (if using React/jQuery/etc.)

3. User Clarification (if needed)

- Specific WordPress patterns to mirror and where to find them?
- Admin interface requirements (settings page, metaboxes, etc.)?
- Frontend/backend integration requirements?


## PRP Generation
Using PRPs/templates/prp_base.md as template:

### Critical Context to Include and pass to the AI agent as part of the PRP
- **Documentation**: WordPress Developer Resources URLs with specific sections
- **Code Examples**: Real snippets from plugin codebase (PHP classes, JS modules)
- **Gotchas**: WordPress version compatibility, nonce verification, escaping/sanitization
- **Patterns**: Existing hooks/filters, AJAX patterns, database operations
- **Dependencies**: Required WordPress version, PHP version

### Implementation Blueprint

- Start with pseudocode showing WordPress-specific approach
- Reference real plugin files for patterns
- Include WordPress security best practices (nonces, capabilities, sanitization)
- List tasks to be completed to fulfill the PRP in the order they should be completed
- Define database schema changes if needed

*** CRITICAL AFTER YOU ARE DONE RESEARCHING AND EXPLORING THE CODEBASE BEFORE YOU START WRITING THE PRP ***
*** ULTRATHINK ABOUT THE PRP AND PLAN YOUR APPROACH THEN START WRITING THE PRP ***
Output
Save as: PRPs/{feature-name}.md
Quality Checklist
- [ ] All necessary WordPress context included
- [ ] Validation gates are executable by AI
- [ ] References existing plugin patterns
- [ ] Clear implementation path with WordPress hooks
- [ ] Security measures documented (nonces, sanitization, escaping)
- [ ] Database operations follow WordPress standards
- [ ] JavaScript/PHP integration documented
- [ ] Internationalization (i18n) considered

Score the PRP on a scale of 1-10 (confidence level to succeed in one-pass implementation using claude codes)
Remember: The goal is one-pass implementation success through comprehensive context.