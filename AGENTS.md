# AGENTS.md

## Build/Lint/Test Commands

### General Commands
- `npm run build` - Build the project (if applicable)
- `npm run lint` - Run linting checks
- `npm run test` - Run all tests
- `npm run test:unit` - Run unit tests
- `npm run test:integration` - Run integration tests

### Running Single Tests
- For PHP projects, use PHPUnit to run specific tests:
  ```
  php vendor/bin/phpunit --filter testName path/to/test/file.php
  ```
- For JavaScript/TypeScript projects, use Jest or similar:
  ```
  npm test -- --testNamePattern="test name"
  ```

### Code Style Guidelines

#### Imports
- PHP: Use PSR-4 autoloading standards
- JavaScript: Follow ES6 module syntax with explicit imports
- All imports should be at the top of files

#### Formatting
- PHP: Follow PSR-2 coding standards
- JavaScript/TypeScript: Use Prettier for consistent formatting
- Maintain consistent indentation (4 spaces for PHP, 2 spaces for JS)

#### Types
- PHP: Use type hints where possible
- JavaScript/TypeScript: Use TypeScript interfaces and types

#### Naming Conventions
- PHP: 
  - Classes: PascalCase
  - Methods/Functions: camelCase
  - Variables: camelCase
  - Constants: UPPER_CASE
- JavaScript/TypeScript:
  - Variables: camelCase
  - Functions: camelCase
  - Classes: PascalCase
  - Constants: UPPER_CASE

#### Error Handling
- PHP: Use try/catch blocks for exceptions
- JavaScript/TypeScript: Use async/await with try/catch
- All errors should be properly logged and handled

#### Documentation
- All classes, methods, and functions should include PHPDoc or JSDoc comments
- Use clear, descriptive names for variables and functions
- Add inline comments for complex logic

## Cursor/Copilot Rules

### Cursor Rules
No specific Cursor rules found in .cursor/rules/ or .cursorrules

### Copilot Instructions
No specific Copilot instructions found in .github/copilot-instructions.md

## Project Structure Notes
This is a Joomla! extension project with:
- Backend components in `/backend/src/`
- Frontend components in `/frontend/src/`
- Language files in `/language/` directories
- Configuration files in root and component-specific directories

All PHP code should follow Joomla! coding standards and PSR guidelines.