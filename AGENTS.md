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
- `backend/src/Controller/FilterController.php`, `backend/src/Controller/FiltersController.php`, `backend/src/Model/FilterModel.php`, `backend/src/Model/FiltersModel.php`, `backend/src/Table/FilterTable.php`, `backend/src/View/Filter`, `backend/src/View/Filters`, `backend/forms/filter.xml`, `backend/forms/filter_filters.xml`, `backend/tmpl/filter`, `backend/tmpl/filters` - административный CRUD SEO-страниц результатов фильтрации товаров.
- `backend/src/Service/FilterSeoKey.php` - нормализация условий SEO-фильтра и построение канонического ключа для сохранения и проверки дублей в `#__ishop_filters`.
- `backend/src/Field/FilterFieldsField.php` - кастомное поле формы для выбора характеристик SEO-страницы фильтра с учетом выбранной категории.
- `frontend/` - клиентская часть компонента.
- `frontend/src/Service/Router.php` - основной RouterView-роутер компонента.
- `frontend/src/Service/FilterRules.php` - кастомные правила ЧПУ для результатов фильтрации категории.
- `frontend/src/Service/FilterSeoKey.php` - фронтенд-версия нормализации условий SEO-фильтра; должна оставаться совместимой с backend-версией.
- `frontend/src/Controller/FilterController.php` - JSON endpoints `filter.preview` и `filter.reset` для AJAX-фильтра.
- `frontend/src/Service/Category.php` - Categories-сервис для дерева категорий товаров.
- `frontend/src/Helper/RouteHelper.php` - генерация внутренних ссылок компонента.
- `frontend/src/Model/CategoryModel.php` - модель страницы категории, состояние фильтра, объект фильтра, поиск SEO-записи фильтра и делегирование списка товаров в `ProductsModel`.
- `frontend/src/Model/ProductsModel.php` - основной SQL-запрос товаров и применение фильтров.
- `frontend/src/View/Category/HtmlView.php` и `frontend/tmpl/category/` - вывод категории, товаров, сортировки, кнопки фильтра, SEO-описания и meta для совпавшей страницы фильтра.
- `media/scss` - исходники CSS.
- `media/js/*.js` - исходные JS entrypoints.
- `media/js/admin-filter.js` - исходный JS для административной формы SEO-страницы фильтра; динамически обновляет блок характеристик при выборе категории.
- `media/css`, `media/js/*.min.js`, `*.gz` - сгенерированные ассеты, не править вручную, если изменение должно идти через сборку.
- `media/joomla.asset.json` - декларации Joomla Web Asset Manager.
- `backend/sql/install.mysql.utf8.sql`, `backend/sql/updates/mysql` - схема БД и обновления.
- `frontend/language/*`, `backend/language/*` - языковые файлы. Новые ключи добавлять в `en-GB` и `ru-RU`.

## Роутинг и фильтр категории

Особое внимание: доработки фильтра товаров в категории требуют согласованности между `com_ishop` и `mod_ishop_filter`.

Текущая цепочка:

1. `mod_ishop_filter` отображает форму фильтра, кладет в нее `data-category-id`, `data-item-id`, URL endpoints и отправляет выбранные значения AJAX-запросом в `com_ishop`.
2. `media/mod_ishop_filter/front.js` отправляет POST на `index.php?option=com_ishop&task=filter.preview&format=json` с Joomla CSRF token, `category_id`, `Itemid` и полями формы (`manufacturers[]`, `warehouses[]`, `min_price`, `max_price`, `good_price`, `ishop_fields[...]`, габариты/вес).
3. `frontend/src/Controller/FilterController.php::preview()` нормализует вход через `FilterRules::normalizeFilterInput()`, включает URL-driven режим (`filter_route=1`), получает ID подходящих товаров и возвращает JSON: `productCount`, `availableOptions`, `sefUrl`, `baseUrl`.
4. Кнопка модуля `Показать n товаров` редиректит на полученный `sefUrl`. Reset отправляет POST на `task=filter.reset`, очищает session-state фильтра и редиректит на `baseUrl` категории.
5. При прямой загрузке ЧПУ `frontend/src/Service/FilterRules.php::parse()` раскладывает SEO-сегменты в request keys, которые читает `CategoryModel::populateState()`.
6. `CategoryModel::getItems()` создает `ProductsModel` с `ignore_request => true` и вручную передает туда все `filter.*` state.
7. `frontend/src/Model/ProductsModel.php::getListQuery()` применяет фильтры к SQL.
8. `CategoryModel::getFilterObject()` собирает начальные данные для модуля фильтра и счетчик активных фильтров.
9. `CategoryModel::getFilterSeoPage()` строит ключ текущей комбинации через `frontend/src/Service/FilterSeoKey.php` и ищет опубликованную запись в `#__ishop_filters`.
10. `frontend/src/View/Category/HtmlView.php` подставляет `metatitle`, `metadesc`, `metakey` совпавшей SEO-записи вместо стандартных meta категории, а шаблон категории выводит `description` SEO-записи вместо стандартного описания категории.
11. `frontend/src/Service/Router.php` регистрирует `FilterRules` после `MenuRules`, `StandardRules`, `NomenuRules`.

SEO-страницы результатов фильтрации:

- Данные SEO-страниц фильтра хранятся в `#__ishop_filters`. Таблица содержит условия фильтра, HTML-описание, `metatitle`, `metadesc`, `metakey`, системные поля `state`, `created`, `created_by`, `created_by_alias`, `modified`, `modified_by`, `checked_out`, `checked_out_time`, `ordering`, `language`.
- Условия SEO-страницы включают `category_id`, производителей (`manufacturers`), характеристики (`ishop_fields`), габариты (`min_width`, `max_width`, `min_height`, `max_height`, `min_depth`, `max_depth`) и вес (`min_weight`, `max_weight`). Склад (`warehouses`) сейчас участвует в ЧПУ/товарном фильтре, но не хранится в `#__ishop_filters`; если потребуется SEO по складам, нужно синхронно добавить колонку/форму/ключ/поиск.
- Для числовых характеристик и диапазонов габаритов/веса хранится точная пара `min`/`max`; `0` означает незаданную границу.
- Для списочных характеристик в `ishop_fields` хранятся массивы ID значений, для булевых - `1`, для числовых - объект с `min` и `max`.
- `filter_key` - канонический ключ условий без `category_id`; категория проверяется отдельной колонкой. Ключ строится через `FilterSeoKey::build()` и используется для поиска совпадения на фронтенде и проверки дублей в админке.
- Backend и frontend версии `FilterSeoKey` должны оставаться логически идентичными. При изменении формата ключа, нормализации производителей, характеристик или диапазонов меняйте оба файла и проверяйте существующие записи `#__ishop_filters`.
- Если найдено несколько опубликованных SEO-записей с одинаковыми условиями, фронтенд берет первую по `ordering`, но `FilterTable::store()` должен не допускать дублей по `category_id + filter_key + language`.
- Язык учитывается при поиске: запись с текущим языком Joomla имеет приоритет над `*`; обе записи сортируются затем по `ordering`.
- SEO-описание и meta совпавшей записи должны заменять стандартные описание и метаданные категории, а не дополнять их. Если SEO-запись не найдена, категория работает по прежней логике.

Практические правила для ЧПУ фильтра:

- Каноничная страница фильтра должна оставаться страницей `view=category&id={catid}` с тем же `Itemid`, чтобы `CategoryModel` использовал правильный user-state ключ.
- При добавлении нового SEO-сегмента фильтра меняйте оба направления: `FilterRules::build()` и `FilterRules::parse()`, а также JSON-preview в `FilterController`, если модулю нужны доступные значения или URL.
- После `parse()` значения должны попасть в те же request keys, которые читает `CategoryModel::populateState()` и отправляет `mod_ishop_filter`: `manufacturers`, `warehouses`, `ishop_fields`, `min_price`, `max_price`, `good_price`, `min_width`, `max_width`, `min_height`, `max_height`, `min_depth`, `max_depth`, `min_weight`, `max_weight`.
- `FilterRules::build()` должен удалять из `$query` обработанные параметры, если они полностью перенесены в сегменты. Иначе Joomla может оставить дубли в query string.
- Поддерживаемые системные сегменты: `brand:alias1:alias2`, `sale:yes`, `price:min:max`, `warehouse:alias1:alias2`, `width:min:max`, `height:min:max`, `depth:min:max`, `weight:min:max`.
- Для характеристик используются `#__ishop_fields.alias` и `#__ishop_values.alias`: `field:value1:value2` для списков, `field:min:max` для числовых, `field:yes` для булевых. `field:no` не используется.
- Значения диапазонов в URL и AJAX должны быть целыми; `0` означает невыбранную границу и не должен создавать активный фильтр сам по себе.
- Алиасы производителей берутся из `#__ishop_manufacturers.alias`; алиасы складов - из `#__ishop_warehouses.alias`; ID производителю соответствует `#__ishop_products.manufacturer_id`.
- Канонический порядок сегментов и значений важен для защиты от дублей: сортируйте бренды, склады и значения характеристик по `ordering`/`alias`, как это делает `FilterRules`.
- Не ломайте стандартный роутинг категорий: `Router::getCategorySegment()`, `getCategoryId()`, `noIDs` (`sef_ids` в параметрах компонента) и nested category path должны продолжать работать.
- Не смешивайте `manufacturer_id` и `manufacturers[]` без явного решения. Список производителей из фильтра идет через state `filter.manufacturers`; одиночный производитель идет через `filter.manufacturer_id` и применяется только если список пуст.
- Для характеристик фильтр товаров использует денормализованные таблицы `#__ishop_filter_cat_{categoryId}` и поля `map.field_{fieldId}`. Любой ЧПУ-формат для характеристик должен сохранять `categoryId` и учитывать, что таблица зависит от категории.
- Неизвестные или невалидные SEO-сегменты должны доходить до штатного 404 Joomla, а не молча игнорироваться.
- При изменении состава фильтров, влияющих на SEO-страницы, синхронизируйте: схему `#__ishop_filters`, backend form/table/model, `backend/src/Service/FilterSeoKey.php`, `frontend/src/Service/FilterSeoKey.php`, `CategoryModel::getFilterSeoPage()`, шаблоны вывода и языковые файлы.
- После изменения роутинга проверяйте прямую загрузку URL, AJAX-preview, кнопку submit, reset фильтра, пагинацию, сортировку, canonical/дубли query string и активные значения в `mod_ishop_filter`.

Полезные команды для анализа фильтра:

- `rg -n "FilterRules|FilterController|filter.preview|filter.reset|sefUrl|baseUrl|brand:|sale:|warehouse|manufacturers|ishop_fields|min_price|max_price|good_price" frontend ../mod_ishop_filter`
- `rg -n "getListQuery|getFilteredItemsId|filter\.manufacturers|filter\.ishop_fields|filter\.warehouse|filter\.min_price" frontend/src/Model frontend/src/Controller`
- `rg -n "FilterSeoKey|getFilterSeoPage|#__ishop_filters|filter_key|admin-filter|view=filters|task=filter.categoryFields" backend frontend media`

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
- Административный asset `com_ishop.admin-filter` должен подключаться через Joomla Web Asset Manager и собираться из `media/js/admin-filter.js`; минифицированные файлы обновляйте только через сборку.
- В PHP-файлах сохраняйте `defined('_JEXEC') or die;`, namespace Joomla API (`Factory`, `HTMLHelper`, `Text`, `LayoutHelper`, `Route`) и существующий стиль проекта.
- Экранируйте вывод: `$this->escape()`, `htmlspecialchars()`, `HTMLHelper::cleanImageURL()`, `Text::_()`, явные приведения типов для данных из params/input/model.
- SQL собирайте через Joomla Database Query API, `quoteName()`, `quote()`, `bind()`, `bindArray()`, `whereIn()` и `ParameterType`, особенно для входных данных фильтра.
- Формы должны содержать Joomla CSRF token через `HTMLHelper::_('form.token')`; новые POST/AJAX сценарии должны учитывать Joomla token и права доступа.
- Для Bootstrap-разметки используйте Bootstrap 5.3 и `data-bs-*`, не Bootstrap 4.
- Поддерживайте accessibility: `aria-label`, `visually-hidden`, корректные `button`/`a`, возврат фокуса в offcanvas/modal и видимые состояния focus.
- При добавлении языковых ключей обновляйте обе локали `en-GB` и `ru-RU`.
- Не меняйте API и query params, которыми пользуются связанные модули, без проверки соответствующего проекта.
- Когда добавляете и или меняете классы, методы классов - обязательно добавлять комментарии на русском языке.

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
- AJAX-preview в `mod_ishop_filter`: счетчик, доступность опций, `sefUrl`, `baseUrl`;
- submit фильтра по бренду, складу, скидке, цене, габаритам/весу и характеристикам;
- прямые ЧПУ URL с `brand:alias`, `sale:yes`, `price:min:max`, `warehouse:alias`, `width/height/depth/weight:min:max` и сегментами характеристик;
- административный список SEO-страниц фильтра `view=filters`: создание, редактирование, публикация, снятие с публикации, ordering, batch, check-in, удаление в корзину и окончательное удаление;
- форму SEO-страницы фильтра: выбор категории, динамическую подгрузку характеристик, производителей, числовые min/max, булевые и списочные характеристики, габариты/вес, HTML-описание и meta-поля;
- защиту от дублей SEO-страниц фильтра по одинаковым условиям, категории и языку;
- подстановку HTML-описания и meta Title/Description/Keywords из `#__ishop_filters` вместо стандартных данных категории при совпадении фильтра;
- fallback без SEO-записи: категория должна показывать стандартное описание и meta как раньше;
- категорию с несколькими брендами/складами/значениями list-характеристик, если включен такой сценарий;
- сортировку и пагинацию после активного фильтра;
- reset фильтра на базовый URL категории;
- карточку товара;
- корзину и checkout;
- поиск;
- логин;
- 403/404 и offline page.

## Ограничения и известные состояния

- Автоматических тестов пока нет; `pnpm test` является заглушкой.
- В проекте есть сгенерированные CSS/JS/gzip файлы. Их изменение должно быть следствием сборки.
- Фильтр зависит от денормализованных таблиц `#__ishop_filter_cat_{categoryId}`. Если таблица отсутствует, `CategoryModel::getItemsId()` возвращает fallback-данные или пустой результат в зависимости от сценария.
- `mod_ishop_filter/media/js/front.js` должен получать `category_id` из `data-category-id` формы, а не из query string: на ЧПУ-страницах `?id=` обычно отсутствует.
- Основной AJAX фильтра больше не должен идти через `com_ajax&module=ishop_filter`; используйте endpoints `com_ishop` `filter.preview` и `filter.reset`.
