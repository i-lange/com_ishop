# AGENTS.md

## Назначение проекта

`com_ishop` - устанавливаемое расширение, компонент для CMS Joomla 6. Компонент реализует сложную логику работы интернет-магазина:
- Каталог товаров сгруппированных по категориям.
- Возможность подбора товара по характеристикам, срока доставки, скидкам и т.п.
- Возможность добавления товаров в корзину, список сравнения, в список избранных товаров.
- Возможность оформить заказ через корзину или быстрый заказ в 1 клик.



## Связанные проекты и расширения
Данный компонент разрабатывается для интернет-магазина на Joomla.
Собранный production-ready проект это magazin-gefest-new.local, он доступен:
- в окружении Windows путь к директории проекта: "c:\OSPanel\home\magazin-gefest-new.local\"
- в окружении WSL путь к директории проекта: "mnt/c/OSPanel/home/magazin-gefest-new.local"
- на локальном сервере (сервер всегда запущен по-умолчанию) панель администратора доступна по адресу: https://magazin-gefest-new.local/administrator/
- на локальном сервере (сервер всегда запущен по-умолчанию) фронтенд сайта доступен по адресу: https://magazin-gefest-new.local

Расширения, которые работают вместе в рамках magazin-gefest-new.local:
- com_ishop (в окружении Windows путь "c:\OSPanel\home\com_ishop\") это компонент Joomla непосредственно интернет-магазина.
- com_ishopintegro (в окружении Windows путь "c:\OSPanel\home\com_ishopintegro\") это компонент Joomla для интернет-магазина com_ishop со сторонними сервисами, для обмена данными.
- mod_ishop_cart (в окружении Windows путь "c:\OSPanel\home\mod_ishop_cart\") это модуль Joomla для реализации функций корзины пользователя.
- mod_ishop_compare (в окружении Windows путь "c:\OSPanel\home\mod_ishop_compare\") это модуль Joomla для реализации функций сравнения товаров.
- mod_ishop_filter (в окружении Windows путь "c:\OSPanel\home\mod_ishop_filter\") это модуль Joomla для реализации фильтрации товаров в категории по параметрам.
- mod_ishop_zone (в окружении Windows путь "c:\OSPanel\home\mod_ishop_zone\") это модуль Joomla для реализации выбора подходящей зоны доставки (местоположения) пользователем.
- plg_ishopfinder (в окружении Windows путь "c:\OSPanel\home\plg_ishopfinder\") это плагин Joomla для индексации товаров в штатном поиск CMS Joomla.
- plg_ishopintegrocron (в окружении Windows путь "c:\OSPanel\home\plg_ishopintegrocron\") это плагин Joomla для запуска некоторых методов com_ishopintegro из планировщика задач CMS Joomla.
- tpl_itheme (в окружении Windows путь "c:\OSPanel\home\tpl_itheme\") это шаблон Joomla который используется на всей клиентской части сайта
- plg_ithemecsscompiler (в окружении Windows путь "c:\OSPanel\home\plg_ithemecsscompiler\") это плагин Joomla который добавляет в шаблон tpl_itheme возможность компилировать стили прямо из административной панели Joomla

При внесении изменений в проект нужно держать во внимании данный контекст. Все эти расширения дополняют друг друга и имеют некоторые зависимости одно от другого!

## Официальный контекст Joomla 6

При изменениях сверяйтесь с официальной документацией Joomla, особенно:

- Getting Started: https://manual.joomla.org/docs/get-started/
- Technical Requirements: https://manual.joomla.org/docs/get-started/technical-requirements/
- Module Development Tutorial: https://manual.joomla.org/docs/building-extensions/modules/module-development-tutorial/
- Web Asset Manager: https://manual.joomla.org/docs/general-concepts/web-asset-manager/


## Стек и окружение

- Joomla CMS 6.x, `method="upgrade"`.
- PHP 8.3+; для Joomla 6.x ориентируйтесь на актуальные требования официальной документации.
- Для вывода html по-умолчанию используются подходы Bootstrap 5.3.

## Команды

- `pnpm install` - установить JS-зависимости по `pnpm-lock.yaml`.
- `pnpm build` - полная сборка CSS и JS через `build.mjs`.
- `pnpm build:css` - собрать `media/css/*.css`, `*.min.css`, `*.min.css.gz`.
- `pnpm build:js` - собрать `media/js/*.min.js`, `*.min.js.gz`.
- `pnpm watch:js` - наблюдать `media/js/*.min.js`, `*.min.js.gz`.
- `pnpm watch:css` - наблюдать `media/js/*.min.js`, `*.min.js.gz`.
- `pnpm test` - сейчас заглушка `No automated tests yet`.
- `pnpm zip` - `pnpm build` и создание установочного архива `tpl_itheme-{version}.zip`.

## Правила внесения изменений

- Сначала меняйте исходники: SCSS в `media/scss`, обычные JS entrypoints в `media/js`, PHP overrides в `html` или корневых template-файлах. Не правьте вручную `.min.css`, `.min.js`, `.gz`, если изменение должно генерироваться сборкой.
- После изменения SCSS/JS запускайте соответствующую сборку и включайте сгенерированные assets, если проект ожидает готовый installable template.
- `vite.config.css.mts` использует `emptyOutDir: true` для `media/css`; не держите там ручные файлы, которые не должны удаляться сборкой.
- В PHP-файлах сохраняйте `defined('_JEXEC') or die;`, namespaced Joomla API (`Factory`, `HTMLHelper`, `Text`, `LayoutHelper`, `Route`) и существующий стиль шаблона.
- Экранируйте вывод: `$this->escape()`, `htmlspecialchars()`, `HTMLHelper::cleanImageURL()`, `Text::_()` и явные приведения типов там, где данные приходят из params/input/model.
- Формы должны содержать Joomla CSRF token через `HTMLHelper::_('form.token')`; новые POST/AJAX сценарии должны учитывать Joomla token и права доступа.
- Новые assets регистрируйте в `joomla.asset.json` с понятными именами, `type`, `uri`, `attributes` и `dependencies`.
- Если добавляете новый JS entrypoint, обновите `JS_ENTRY_FILES` в `vite.config.js.mts` и asset declaration в `joomla.asset.json`.
- Если добавляете новый SCSS entrypoint, обновите `SCSS_ENTRIES` в `vite.config.css.mts` и asset declaration в `joomla.asset.json`.
- Для Bootstrap-разметки используйте классы и data-атрибуты Bootstrap 5.3 (`data-bs-*`), а не устаревшие Bootstrap 4 подходы.
- Поддерживайте accessibility: `aria-label`, `visually-hidden`, корректные `button`/`a`, возврат фокуса в offcanvas/modal и видимые состояния focus.
- При добавлении языковых ключей обновляйте обе локали `en-GB` и `ru-RU`.

## Проверка перед сдачей

Минимальный набор:

- `pnpm build`
- `pnpm test`
- `pnpm zip`

Если Node.js недоступен, явно сообщите, что команды не запускались из-за окружения.
Для функциональной проверки установите zip в Joomla 6 по адресу https://magazin-gefest-new.local/administrator/index.php?option=com_installer&view=install и проверьте как минимум главную, категорию, карточку товара, корзину, checkout, поиск, логин, 403/404 и offline page.

## Ограничения и известные состояния

- Это не полный сайт Joomla, а только компонент, устанавливаемый как расширение. Корневые PHP-файлы нельзя полноценно запускать вне Joomla application context.
- Автоматических тестов пока нет; `pnpm test` является заглушкой.
