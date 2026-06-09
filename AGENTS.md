# AGENTS.md

## Документация и контекст

- Если задача касается библиотеки, фреймворка, SDK, API, CLI или cloud-сервиса, сначала получай актуальную документацию через Context7 MCP: `resolve-library-id`, затем `query-docs`. Для Joomla 6 также сверяйся с официальной документацией.
- Полезные ссылки Joomla: https://manual.joomla.org/docs/get-started/, https://manual.joomla.org/docs/general-concepts/web-asset-manager/, https://manual.joomla.org/docs/general-concepts/javascript/ajax/.
- `com_ishop` - устанавливаемый компонент интернет-магазина для Joomla 6, не полный сайт. Большинство PHP-файлов корректно работают только внутри Joomla application context.

## Окружение

- Production-проверка: `https://magazin-gefest-new.local`, админка `https://magazin-gefest-new.local/administrator/`, файлы сайта `C:\OSPanel\home\magazin-gefest-new.local\`.
- Связанные расширения в `C:\OSPanel\home\`: `mod_ishop_cart`, `mod_ishop_compare`, `mod_ishop_filter`, `mod_ishop_zone`, `plg_ishopfinder`, `tpl_itheme`, `plg_ithemecsscompiler`, `com_ishopintegro`, `plg_ishopintegrocron`.
- Особенно для фильтра проверяй совместимость с `../mod_ishop_filter`.
- Стек: Joomla CMS 6.x, PHP 8.3+, Bootstrap 5.3, Node.js `>=24.0.0`, npm `>=11.8.0`, pnpm `>=10.3.0`, Vite 8, Sass, Lightning CSS.

## Структура

- `ishop.xml`, `script.php` - манифест и install/update script компонента. Текущая версия синхронизируется с `package.json` и `media/joomla.asset.json`.
- `backend/` - админка: MVC-слои, формы, таблицы, SQL, язык, DI provider `backend/services/provider.php`, компонент `backend/src/Extension/IshopComponent.php`.
- `frontend/` - сайт: RouterView-роутер, модели, контроллеры, шаблоны, язык.
- Ключевые файлы фильтра: `frontend/src/Service/FilterRules.php`, `frontend/src/Controller/FilterController.php`, `frontend/src/Service/FilterAvailabilityService.php`, `frontend/src/Model/CategoryModel.php`, `frontend/src/Model/ProductsModel.php`, `frontend/src/Service/FilterSeoKey.php`, `backend/src/Service/FilterSeoKey.php`.
- CRUD SEO-страниц фильтра: `backend/src/Controller/FilterController.php`, `FiltersController.php`, `backend/src/Model/FilterModel.php`, `FiltersModel.php`, `backend/src/Table/FilterTable.php`, `backend/src/View/Filter`, `backend/src/View/Filters`, `backend/forms/filter.xml`, `backend/forms/filter_filters.xml`, `backend/tmpl/filter`, `backend/tmpl/filters`.
- Assets: исходники SCSS в `media/scss`, JS entrypoints в `media/js/*.js`; сгенерированные `media/css`, `*.min.js`, `*.gz` вручную не править. Web Asset Manager: `media/joomla.asset.json`.
- SQL: `backend/sql/install.mysql.utf8.sql`, `backend/sql/updates/mysql`. Языки обновлять в обеих локалях `en-GB` и `ru-RU`.

## Команды

- `pnpm install` - зависимости.
- `pnpm build`, `pnpm build:css`, `pnpm build:js` - сборка ассетов.
- `pnpm watch:css`, `pnpm watch:js` - watch-сборки.
- `pnpm test` - сейчас заглушка `No automated tests yet`.
- `pnpm zip` - сборка и архив `com_ishop-{version}.zip`.

## Правила изменений

- Сначала меняй исходники: PHP в `backend/`, `frontend/`, `tmpl`; SCSS в `media/scss`; JS entrypoints в `media/js`.
- После изменений SCSS/JS запускай нужную сборку и включай сгенерированные файлы, если нужен installable package.
- Новые JS entrypoints добавляй в `JS_ENTRY_FILES` в `vite.config.js.mts`; новые SCSS entrypoints - в `SCSS_ENTRIES` в `vite.config.css.mts`; assets регистрируй в `media/joomla.asset.json`.
- `vite.config.css.mts` очищает `media/css` через `emptyOutDir: true`; не держи там ручные файлы.
- При изменении версии синхронно обновляй `package.json`, `ishop.xml`, `media/joomla.asset.json`.
- В PHP сохраняй `defined('_JEXEC') or die;`, namespace Joomla API и стиль проекта.
- Новые или измененные классы и методы классов комментируй на русском языке.
- Экранируй вывод (`$this->escape()`, `htmlspecialchars()`, `Text::_()`, `HTMLHelper::cleanImageURL()`), явно приводи входные данные к нужным типам.
- SQL собирай через Joomla Database Query API: `quoteName()`, `quote()`, `bind()`, `bindArray()`, `whereIn()`, `ParameterType`.
- Формы и AJAX/POST должны учитывать Joomla CSRF token и права доступа.
- Bootstrap-разметка - Bootstrap 5.3 и `data-bs-*`; поддерживай accessibility (`aria-label`, `visually-hidden`, корректные `button`/`a`, focus states).
- Не меняй API, query params и data-атрибуты, которыми пользуются связанные модули, без проверки этих модулей.

## Фильтр категории и ЧПУ

- Основной AJAX фильтра идет через `com_ishop`: `task=filter.preview` и `task=filter.reset`.
- `mod_ishop_filter` должен передавать `category_id` из `data-category-id`, `Itemid`, Joomla token и поля формы: `manufacturers[]`, `warehouses[]`, `min_price`, `max_price`, `good_price`, `ishop_fields[...]`, габариты/вес.
- `FilterController::preview()` нормализует вход через `FilterRules::normalizeFilterInput()`, считает товары через `FilterAvailabilityService`, возвращает `productCount`, `availableOptions`, `sefUrl`, `baseUrl`.
- Прямая загрузка ЧПУ разбирается в `FilterRules::parse()` в те же request keys, которые читает `CategoryModel::populateState()`. `CategoryModel` передает state фильтра в `ProductsModel`.
- Поддерживаемые системные сегменты: `brand:alias1:alias2`, `sale:yes`, `price:min:max`, `warehouse:alias1:alias2`, `width:min:max`, `height:min:max`, `depth:min:max`, `weight:min:max`.
- Сегменты характеристик: `field:value1:value2` для списков, `field:min:max` для числовых, `field:yes` для boolean. `field:no` не используется.
- Диапазоны в URL/AJAX - целые числа; `0` означает пустую границу и сам по себе не активирует фильтр.
- `FilterRules::build()` должен удалять из `$query` параметры, перенесенные в сегменты, чтобы не оставлять дубли в query string.
- Каноничный route фильтра остается `view=category&id={catid}` с тем же `Itemid`; стандартный роутинг категорий (`getCategorySegment()`, `getCategoryId()`, `sef_ids`, nested path) не ломать.
- Алиасы производителей: `#__ishop_manufacturers.alias`; складов: `#__ishop_warehouses.alias`; характеристики: `#__ishop_fields.alias`; значения: `#__ishop_values.alias`.
- Канонический порядок брендов, складов, характеристик и значений важен для защиты от дублей: используй порядок `ordering`, затем `alias`.
- Не смешивай `manufacturer_id` и `manufacturers[]` без явного решения: список идет через `filter.manufacturers`, одиночный производитель - через `filter.manufacturer_id` и применяется только если список пуст.
- Фильтр характеристик зависит от денормализованных таблиц `#__ishop_filter_cat_{categoryId}` и полей `map.field_{fieldId}`; любые изменения URL-формата должны сохранять привязку к категории.
- Невалидные SEO-сегменты должны доходить до штатного 404 Joomla, а не молча игнорироваться.

## SEO-страницы фильтра

- Таблица `#__ishop_filters` хранит условия фильтра, HTML-описание, `metatitle`, `metadesc`, `metakey`, системные поля Joomla, `ordering`, `language`.
- В SEO-условиях сейчас участвуют `category_id`, `manufacturers`, `ishop_fields`, диапазоны `width/height/depth/weight`. `warehouses` участвует в товарном фильтре и ЧПУ, но не хранится в `#__ishop_filters`.
- `filter_key` - SHA-256 от канонических условий без `category_id`; категория, язык и ключ проверяются отдельно.
- `backend/src/Service/FilterSeoKey.php` и `frontend/src/Service/FilterSeoKey.php` должны оставаться логически идентичными. При изменении нормализации меняй оба файла и учитывай существующие записи `#__ishop_filters`.
- `FilterTable::store()` должен защищать от дублей по `category_id + filter_key + language`.
- При совпадении SEO-записи `CategoryModel::getFilterSeoPage()` и `Category/HtmlView` заменяют стандартные описание и meta категории данными SEO-записи. При отсутствии записи категория работает по старой логике.
- Язык учитывается при поиске SEO-записи: текущий язык приоритетнее `*`, затем сортировка по `ordering`.
- Если добавляешь фильтр, влияющий на SEO-страницы, синхронизируй SQL-схему, backend form/table/model, оба `FilterSeoKey`, `CategoryModel::getFilterSeoPage()`, шаблоны и языковые файлы.

## Быстрый поиск

- Фильтр/роутинг: `rg -n "FilterRules|FilterController|FilterAvailabilityService|filter.preview|filter.reset|sefUrl|baseUrl|brand:|sale:|warehouse|manufacturers|ishop_fields|min_price|max_price|good_price" frontend ../mod_ishop_filter`
- Модели фильтра: `rg -n "getListQuery|getFilteredItemsId|filter\\.manufacturers|filter\\.ishop_fields|filter\\.warehouse|filter\\.min_price" frontend/src/Model frontend/src/Controller`
- SEO-фильтры: `rg -n "FilterSeoKey|getFilterSeoPage|#__ishop_filters|filter_key|admin-filter|view=filters|task=filter.categoryFields" backend frontend media`

## Проверка перед сдачей

- Минимум: `pnpm build`, `pnpm test`, `pnpm zip`. Если Node.js недоступен, явно сообщи, что не запускалось.
- Для функциональной проверки установи `com_ishop-{version}.zip` в Joomla 6 и проверь затронутые сценарии. Для изменений фильтра обязательно проверить: категорию без фильтра, AJAX preview/reset, submit по бренду/складу/скидке/цене/габаритам/весу/характеристикам, прямые ЧПУ URL, пагинацию, сортировку, canonical/query-дубли и активные значения в `mod_ishop_filter`.
- Для SEO-фильтра проверить админский CRUD `view=filters`, форму SEO-страницы, защиту от дублей, подстановку описания/meta при совпадении и fallback без SEO-записи.
- Для широких изменений также проверить главную магазина, список категорий, карточку товара, корзину/checkout, поиск, логин, 403/404 и offline page.

## Известные ограничения

- Автотестов пока нет; `pnpm test` только заглушка.
- Сгенерированные CSS/JS/gzip файлы должны меняться только через сборку.
- Если таблица `#__ishop_filter_cat_{categoryId}` отсутствует, фильтр может вернуть fallback-данные или пустой результат в зависимости от сценария.
