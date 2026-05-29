# AGENTS.md

## Назначение проекта

`com_ishop` - устанавливаемый компонент интернет-магазина для Joomla 6. Компонент отвечает за каталог товаров, категории, карточки товаров, корзину/checkout, избранное, сравнение, заказы, склады, доставки, оплаты, скидки, производителей, поставщиков и характеристики товаров.

Компонент не является полным сайтом Joomla. Большинство PHP-файлов нельзя корректно запускать вне Joomla application context.

## Связанные проекты и расширения

Production-ready сайт для проверки: `magazin-gefest-new.local`.

- Windows: `c:\OSPanel\home\magazin-gefest-new.local\`
- WSL: `/mnt/c/OSPanel/home/magazin-gefest-new.local`
- Админка: `https://magazin-gefest-new.local/administrator/`
- Фронтенд: `https://magazin-gefest-new.local`

Связанные расширения в `/mnt/c/OSPanel/home/`:

- `com_ishop` - текущий компонент магазина.
- `com_ishopintegro` - интеграции и обмен данными со сторонними сервисами.
- `mod_ishop_cart` - модуль корзины.
- `mod_ishop_compare` - модуль сравнения.
- `mod_ishop_filter` - модуль фильтрации товаров в категории. При задачах по фильтру почти всегда нужно смотреть его вместе с этим компонентом.
- `mod_ishop_zone` - выбор зоны доставки.
- `plg_ishopfinder` - индексация товаров в штатный поиск Joomla.
- `plg_ishopintegrocron` - запуск методов интеграций из планировщика Joomla.
- `tpl_itheme` - шаблон клиентской части.
- `plg_ithemecsscompiler` - компиляция стилей шаблона из админки.

Изменения в `com_ishop` могут зависеть от этих проектов. Особенно для фильтра проверяйте `../mod_ishop_filter`.

## Официальный контекст Joomla 6

При изменениях сверяйтесь с официальной документацией Joomla, особенно:

- Getting Started: https://manual.joomla.org/docs/get-started/
- Technical Requirements: https://manual.joomla.org/docs/get-started/technical-requirements/
- Module Development Tutorial: https://manual.joomla.org/docs/building-extensions/modules/module-development-tutorial/
- Web Asset Manager: https://manual.joomla.org/docs/general-concepts/web-asset-manager/

## Стек и окружение

- Joomla CMS 6.x, расширение с `method="upgrade"`.
- PHP 8.3+.
- Node.js `>=24.0.0`, npm `>=11.8.0`, pnpm `>=10.3.0`.
- Bootstrap 5.3 для HTML-разметки на клиентской части.
- Фронтенд-ассеты собираются через Vite 8, Sass и Lightning CSS.

## Структура проекта

- `ishop.xml` - манифест Joomla-компонента. Архив собирается как `com_ishop-{version}.zip`.
- `script.php` - install/update script компонента.
- `backend/` - административная часть компонента.
- `backend/services/provider.php` - DI/service provider, регистрация MVC, роутера, категорий, ассоциаций.
- `backend/src/Extension/IshopComponent.php` - класс компонента.
- `backend/src/Model`, `backend/src/Table`, `backend/src/View`, `backend/src/Controller`, `backend/tmpl`, `backend/forms` - стандартные слои Joomla MVC для админки.
- `frontend/` - клиентская часть компонента.
- `frontend/src/Service/Router.php` - основной RouterView-роутер компонента.
- `frontend/src/Service/FilterRules.php` - кастомные правила ЧПУ для результатов фильтрации категории.
- `frontend/src/Service/Category.php` - Categories-сервис для дерева категорий товаров.
- `frontend/src/Helper/RouteHelper.php` - генерация внутренних ссылок компонента.
- `frontend/src/Model/CategoryModel.php` - модель страницы категории, состояние фильтра, объект фильтра и делегирование списка товаров в `ProductsModel`.
- `frontend/src/Model/ProductsModel.php` - основной SQL-запрос товаров и применение фильтров.
- `frontend/src/View/Category/HtmlView.php` и `frontend/tmpl/category/` - вывод категории, товаров, сортировки и кнопки фильтра.
- `media/scss` - исходники CSS.
- `media/js/*.js` - исходные JS entrypoints.
- `media/css`, `media/js/*.min.js`, `*.gz` - сгенерированные ассеты, не править вручную, если изменение должно идти через сборку.
- `media/joomla.asset.json` - декларации Joomla Web Asset Manager.
- `backend/sql/install.mysql.utf8.sql`, `backend/sql/updates/mysql` - схема БД и обновления.
- `frontend/language/*`, `backend/language/*` - языковые файлы. Новые ключи добавлять в `en-GB` и `ru-RU`.

## Роутинг и фильтр категории

Особое внимание: доработки фильтра товаров в категории требуют согласованности между `com_ishop` и `mod_ishop_filter`.

Текущая цепочка:

1. `mod_ishop_filter` отображает форму фильтра и отправляет параметры (`manufacturers[]`, `warehouses[]`, `min_price`, `max_price`, `good_price`, `ishop_fields[...]`, габариты/вес) на текущий URL категории.
2. `frontend/src/Model/CategoryModel.php::populateState()` читает параметры запроса через `getUserStateFromRequest()` в scope `com_ishop.category.filter.{categoryId}:{Itemid}.*`.
3. `CategoryModel::getItems()` создает `ProductsModel` с `ignore_request => true` и вручную передает туда все `filter.*` state.
4. `frontend/src/Model/ProductsModel.php::getListQuery()` применяет фильтры к SQL.
5. `CategoryModel::getFilterObject()` собирает данные для модуля фильтра и счетчик активных фильтров.
6. `frontend/src/Service/Router.php` регистрирует `FilterRules` после `MenuRules`, `StandardRules`, `NomenuRules`.
7. `frontend/src/Service/FilterRules.php` сейчас обрабатывает ЧПУ-сегмент производителей вида `brand:alias` или `brand:alias1:alias2`, преобразуя его в `manufacturers`.

Практические правила для ЧПУ фильтра:

- Каноничная страница фильтра должна оставаться страницей `view=category&id={catid}` с тем же `Itemid`, чтобы `CategoryModel` использовал правильный user-state ключ.
- При добавлении нового SEO-сегмента фильтра меняйте оба направления: `FilterRules::build()` и `FilterRules::parse()`.
- После `parse()` значения должны попасть в те же request keys, которые читает `CategoryModel::populateState()` и ожидает `mod_ishop_filter`: например `manufacturers`, `warehouses`, `ishop_fields`, `min_price`, `max_price`, `good_price`.
- `FilterRules::build()` должен удалять из `$query` обработанные параметры, если они полностью перенесены в сегменты. Иначе Joomla может оставить дубли в query string.
- Для производителей текущий формат - `brand:{manufacturer_alias}`. Несколько брендов разделяются двоеточием: `brand:bosch:gefests`.
- Алиасы производителей берутся из `#__ishop_manufacturers.alias`; ID производителю соответствует `#__ishop_products.manufacturer_id`.
- Не ломайте стандартный роутинг категорий: `Router::getCategorySegment()`, `getCategoryId()`, `noIDs` (`sef_ids` в параметрах компонента) и nested category path должны продолжать работать.
- Не смешивайте `manufacturer_id` и `manufacturers[]` без явного решения. Список производителей из фильтра идет через state `filter.manufacturers`; одиночный производитель идет через `filter.manufacturer_id` и применяется только если список пуст.
- Для характеристик фильтр товаров использует денормализованные таблицы `#__ishop_filter_cat_{categoryId}` и поля `map.field_{fieldId}`. Любой ЧПУ-формат для характеристик должен сохранять `categoryId` и учитывать, что таблица зависит от категории.
- После изменения роутинга проверяйте прямую загрузку URL, отправку формы фильтра, пагинацию, сортировку, reset фильтра, canonical/дубли query string и активные значения в `mod_ishop_filter`.

Полезные команды для анализа фильтра:

- `rg -n "FilterRules|brand:|manufacturers|manufacturer_id|ishop_fields|min_price|max_price|good_price" frontend ../mod_ishop_filter`
- `rg -n "getListQuery|filter\\.manufacturers|filter\\.ishop_fields|filter\\.warehouse|filter\\.min_price" frontend/src/Model`

## Команды

- `pnpm install` - установить JS-зависимости по `pnpm-lock.yaml`.
- `pnpm build` - полная сборка CSS и JS через `build.mjs`.
- `pnpm build:css` - собрать `media/css/*.css`, `*.min.css`, `*.min.css.gz`.
- `pnpm build:js` - собрать `media/js/*.min.js`, `*.min.js.gz`.
- `pnpm watch:js` - наблюдать JS-сборку.
- `pnpm watch:css` - наблюдать CSS-сборку.
- `pnpm test` - сейчас заглушка `No automated tests yet`.
- `pnpm zip` - `pnpm build` и создание установочного архива `com_ishop-{version}.zip`.

## Правила внесения изменений

- Сначала меняйте исходники: SCSS в `media/scss`, обычные JS entrypoints в `media/js`, PHP в `backend`, `frontend` и шаблонах `tmpl`.
- Не правьте вручную `.min.css`, `.min.js`, `.gz`, если изменение должно генерироваться сборкой.
- После изменения SCSS/JS запускайте соответствующую сборку и включайте сгенерированные ассеты, если нужен installable package.
- `vite.config.css.mts` использует `emptyOutDir: true` для `media/css`; не держите там ручные файлы, которые не должны удаляться сборкой.
- Если добавляете новый JS entrypoint, обновите `JS_ENTRY_FILES` в `vite.config.js.mts` и `media/joomla.asset.json`.
- Если добавляете новый SCSS entrypoint, обновите `SCSS_ENTRIES` в `vite.config.css.mts` и `media/joomla.asset.json`.
- Новые assets регистрируйте в `media/joomla.asset.json` с понятными `name`, `type`, `uri`, `attributes`, `dependencies`.
- В PHP-файлах сохраняйте `defined('_JEXEC') or die;`, namespace Joomla API (`Factory`, `HTMLHelper`, `Text`, `LayoutHelper`, `Route`) и существующий стиль проекта.
- Экранируйте вывод: `$this->escape()`, `htmlspecialchars()`, `HTMLHelper::cleanImageURL()`, `Text::_()`, явные приведения типов для данных из params/input/model.
- SQL собирайте через Joomla Database Query API, `quoteName()`, `quote()`, `bind()`, `bindArray()`, `whereIn()` и `ParameterType`, особенно для входных данных фильтра.
- Формы должны содержать Joomla CSRF token через `HTMLHelper::_('form.token')`; новые POST/AJAX сценарии должны учитывать Joomla token и права доступа.
- Для Bootstrap-разметки используйте Bootstrap 5.3 и `data-bs-*`, не Bootstrap 4.
- Поддерживайте accessibility: `aria-label`, `visually-hidden`, корректные `button`/`a`, возврат фокуса в offcanvas/modal и видимые состояния focus.
- При добавлении языковых ключей обновляйте обе локали `en-GB` и `ru-RU`.
- Не меняйте API и query params, которыми пользуются связанные модули, без проверки соответствующего проекта.

## Проверка перед сдачей

Минимальный набор:

- `pnpm build`
- `pnpm test`
- `pnpm zip`

Если Node.js недоступен, явно сообщите, что команды не запускались из-за окружения.

Для функциональной проверки установите `com_ishop-{version}.zip` в Joomla 6 через `https://magazin-gefest-new.local/administrator/index.php?option=com_installer&view=install` и проверьте минимум:

- главную магазина;
- список категорий;
- категорию без фильтра;
- категорию с фильтром по бренду через форму;
- прямой ЧПУ URL категории с `brand:alias`;
- категорию с несколькими брендами, если включен такой сценарий;
- сортировку и пагинацию после активного фильтра;
- reset фильтра;
- карточку товара;
- корзину и checkout;
- поиск;
- логин;
- 403/404 и offline page.

## Ограничения и известные состояния

- Автоматических тестов пока нет; `pnpm test` является заглушкой.
- В проекте есть сгенерированные CSS/JS/gzip файлы. Их изменение должно быть следствием сборки.
- Фильтр зависит от денормализованных таблиц `#__ishop_filter_cat_{categoryId}`. Если таблица отсутствует, `CategoryModel::getItemsId()` возвращает fallback-данные или пустой результат в зависимости от сценария.
- `mod_ishop_filter/media/js/front.js` получает `category_id` из query string (`id`). На ЧПУ-страницах без `?id=` AJAX-подсказки могут требовать отдельной доработки модуля, даже если серверная фильтрация работает после submit.
