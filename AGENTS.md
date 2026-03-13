# AGENTS.md

## Build/Lint/Test Commands

### General Commands
- `npm run build` - Build the project (if applicable)
- `npm run lint` - Run linting checks
- `npm run test` - Run all tests
- `php vendor/bin/phpunit --filter testName path/to/test/file.php` - Run single PHP test
- `npm test -- --testNamePattern="test name"` - Run single JS/TS test

### Testing Notes
- No PHPUnit configuration currently present in the project root
- Tests should follow Joomla! 6 testing conventions when implemented
- Use `--filter` flag to target specific test methods in PHP

## Code Style Guidelines

### PHP (Joomla! Component)
All PHP code follows **Joomla! coding standards** and **PSR-12** guidelines:

#### Namespace Convention
- Backend: `Ilange\Component\Ishop\Administrator\{Namespace}`
- Frontend: `Ilange\Component\Ishop\Site\{Namespace}`

#### File Organization
- Backend classes: `/backend/src/`
- Frontend classes: `/frontend/src/`
- View templates: `/backend/tmpl/` and `/frontend/tmpl/`
- Helper classes: `/backend/src/Helper/` and `/frontend/src/Helper/`

#### Naming Conventions
- Classes: PascalCase (e.g., `ProductModel`, `ProductHelper`)
- Interfaces/Trait: PascalCase with suffixes (e.g., `BootableExtensionInterface`)
- Methods/Functions: camelCase (e.g., `getProductRoute()`, `populateState()`)
- Variables: camelCase (e.g., `$productId`, `$itemName`)
- Constants: UPPER_CASE (e.g., `CONDITION_PUBLISHED`)
- Field classes: PascalCase with suffix `Field` (e.g., `PrefixesField`)

#### Imports
```php
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;
```

#### Formatting
- Use 4 spaces for indentation
- Class opening brace on new line
- Function/method opening brace on same line as declaration
- No trailing whitespace
- Blank lines between methods and logical sections

### JavaScript/TypeScript (Frontend)
When implementing frontend JS:

#### Module System
- ES6 module syntax with explicit imports
- Use Joomla! web asset manager for script loading

#### Formatting
- 2 spaces for indentation (consistent with Prettier)
- Single quotes for strings
- Semicolons required

#### Naming Conventions
- Variables/Functions: camelCase (e.g., `updateCart()`, `cartItems`)
- Classes: PascalCase
- Constants: UPPER_CASE

## Error Handling

### PHP
```php
try {
    $data = $this->getItem($id);
} catch (\Exception $e) {
    if ($e->getCode() == 404) {
        throw $e;
    }
    // Log error and handle appropriately
}
```

- Use try/catch blocks for database operations
- Check access permissions before data retrieval
- Return meaningful error codes (404 for not found)

### JavaScript
```javascript
try {
    await fetch('/api/endpoint');
} catch (error) {
    console.error('Error:', error);
}
```

## Documentation

### PHPDoc Requirements
All classes, methods, and functions require PHPDoc comments:

```php
/**
 * Метод получения данных записи
 *
 * @param  int  $pk  Идентификатор записи
 * @return object|bool Объект данных записи при успехе, иначе false
 * @throws \Exception
 * @since 1.0.0
 */
public function getItem($pk = null)
```

### Required PHPDoc Tags
- `@package` - Component name
- `@author` - Developer information
- `@copyright` - Copyright notice
- `@license` - License type
- `@param` or `@return` - Parameter/return types
- `@throws` - Exception types
- `@since` - Version introduced

## Database Conventions

### Table Names
- Use prefix: `#__ishop_`
- Examples: `#__ishop_products`, `#__ishop_fields`

### Query Building
```php
$db = Factory::getContainer()->get(DatabaseInterface::class);
$query = $db->getQuery(true)
    ->select($db->quoteName('a.id'))
    ->from($db->quoteName('#__ishop_products', 'a'));
```

## Joomla! Specific Patterns

### MVC Implementation
- Models extend `ItemModel` or `ListModel`
- Views extend `HtmlView`
- Controllers follow standard Joomla! patterns

### Form Handling
- Use Joomla! form classes and field types
- Follow core UI conventions with tabs
- Always include CSRF token in forms

### Layouts
Use Joomla! LayoutHelper for reusable components:
```php
echo LayoutHelper::render('joomla.edit.global', $this);
```

## Project Structure Notes

This is a **Joomla! 6 component** (com_ishop) with:

- **Backend**: `/backend/src/` (MVC), `/backend/tmpl/` (templates)
- **Frontend**: `/frontend/src/` (MVC), `/frontend/tmpl/` (templates)
- **Language files**: `/language/en-GB/`, `/language/ru-RU/`
- **Database SQL**: `sql/install.mysql.utf8.sql`, `sql/uninstall.mysql.utf8.sql`
- **Configuration**: `ishop.xml` (extension manifest)

All PHP code must follow Joomla! coding standards with PSR-12 compliance.