<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Component\Finder\Site\Model\SearchModel;
use Joomla\Utilities\ArrayHelper;
use RuntimeException;

/**
 * Контроллер AJAX-дозагрузки товарных списков.
 *
 * @since 1.0.5
 */
class ProductsController extends BaseController
{
    /**
     * Возвращает следующую порцию товаров в виде готового HTML.
     *
     * @return void
     * @throws \Exception
     *
     * @since 1.0.5
     */
    public function load(): void
    {
        $app = Factory::getApplication();
        $this->prepareJsonResponse();

        try {
            if (!Session::checkToken('post')) {
                throw new RuntimeException('Invalid token', 403);
            }

            $context = $this->input->getCmd('context', 'category');
            $limit = $this->getLimit();
            $limitstart = $this->input->getUint('limitstart', 0);

            $result = match ($context) {
                'category' => $this->loadCategoryProducts($limit, $limitstart),
                'finder'   => $this->loadFinderProducts($limit, $limitstart),
                default    => throw new RuntimeException('Invalid products load context', 400),
            };

            echo new JsonResponse($result);
        } catch (\Throwable $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Устанавливает JSON headers для AJAX-ответа.
     *
     * @return void
     *
     * @since 1.0.5
     */
    private function prepareJsonResponse(): void
    {
        $app = Factory::getApplication();
        $app->mimeType = 'application/json';
        $app->setHeader('Content-Type', $app->mimeType . '; charset=' . $app->charSet);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $app->setHeader('Pragma', 'no-cache');
        $app->sendHeaders();
    }

    /**
     * Получает лимит порции из запроса или глобальных настроек Joomla.
     *
     * @return int
     *
     * @since 1.0.5
     */
    private function getLimit(): int
    {
        $limit = $this->input->getUint('limit', 0);

        if ($limit <= 0) {
            $limit = (int) Factory::getApplication()->get('list_limit', 20);
        }

        return max(1, $limit);
    }

    /**
     * Загружает товары категории с учетом текущего состояния фильтров.
     *
     * @param   int  $limit       Размер порции.
     * @param   int  $limitstart  Смещение списка.
     *
     * @return array<string, mixed>
     * @throws \Exception
     *
     * @since 1.0.5
     */
    private function loadCategoryProducts(int $limit, int $limitstart): array
    {
        $categoryId = $this->input->getInt('category_id', 0) ?: $this->input->getInt('id', 0);

        if ($categoryId <= 0) {
            throw new RuntimeException('Invalid category id', 400);
        }

        $app = Factory::getApplication();
        $input = $app->getInput();

        $input->set('view', 'category');
        $input->set('id', $categoryId);
        $input->set('limit', $limit);
        $input->set('limitstart', $limitstart);

        $model = $this->getModel('Category', 'Site', ['ignore_request' => true]);

        $model->setState('category.id', $categoryId);
        $model->setState('filter.category_id', $categoryId);
        $model->setState('filter.published', 1);
        $model->setState('params', $app->getParams());

        if (!$app->getParams()->get('show_noauth')) {
            $model->setState('filter.access', true);
        } else {
            $model->setState('filter.access', false);
        }

        $model->setState('filter.tag', $this->input->getInt('filter_tag', 0));
        $model->setState('list.filter', $this->input->getString('filter-search', ''));
        $model->setState('list.ordering', $this->getCategoryOrdering());
        $model->setState('list.direction', $this->getDirection('filter_order_Dir', 'ASC'));
        $model->setState('list.start', $limitstart);
        $model->setState('list.limit', $limit);
        $model->setState('filter.max_category_levels', $app->getParams()->get('show_subcategory_content', '1'));
        $model->setState('filter.subcategories', true);
        $model->setState('filter.language', Multilanguage::isEnabled());
        $model->setState('layout', $this->input->getString('layout'));
        $model->setState('filter.featured', $app->getParams()->get('show_featured'));
        $model->setState('filter.route', (bool) $this->input->getInt('filter_route', 0));

        foreach ([
            'min_price', 'max_price',
            'min_width', 'max_width',
            'min_height', 'max_height',
            'min_depth', 'max_depth',
            'min_weight', 'max_weight',
        ] as $key) {
            $model->setState('filter.' . $key, max(0, $this->input->getInt($key, 0)));
        }

        if ($app->getParams()->get('discounts_use', 0)) {
            $model->setState('filter.good_price', $this->input->getInt('good_price', 0) > 0 ? 1 : 0);
        } else {
            $model->setState('filter.good_price', 0);
        }

        $model->setState('filter.ishop_fields', $this->input->get('ishop_fields', [], 'array'));
        $model->setState('filter.manufacturers', ArrayHelper::toInteger((array) $this->input->get('manufacturers', [], 'array')));
        $model->setState('filter.warehouses', ArrayHelper::toInteger((array) $this->input->get('warehouses', [], 'array')));
        $model->setState('filter.manufacturer_id', $this->input->getInt('manufacturer_id', 0));
        $model->setState(
            'filter.warehouse_id',
            $this->input->exists('warehouse_id') ? $this->input->getInt('warehouse_id', 0) : false
        );

        $items = (array) $model->getItems();
        $category = $model->getCategory();
        $pagination = $model->getPagination();
        $params = $app->getParams();
        $total = $pagination ? (int) $pagination->total : count($items);
        $nextLimitstart = $limitstart + $limit;

        $currency = strtoupper($params->get('defaultCurrency', 'BYN'));

        return [
            'context'        => 'category',
            'html'           => $this->renderProducts($items, $params),
            'currency'       => $currency,
            'total'          => $total,
            'limit'          => $limit,
            'limitstart'     => $limitstart,
            'nextLimitstart' => $nextLimitstart,
            'hasMore'        => $nextLimitstart < $total,
            'analyticsItems' => $this->buildAnalyticsItems(
                $items,
                $category ? (string) $category->title : '',
                $limitstart
            ),
            'itemList'       => [
                'id'   => $category ? (string) $category->id : '0',
                'name' => $category ? (string) $category->title : '',
            ],
        ];
    }

    /**
     * Получает безопасное поле сортировки категории.
     *
     * @return string
     *
     * @since 1.0.5
     */
    private function getCategoryOrdering(): string
    {
        $ordering = $this->input->getString('filter_order', 'a.price');
        $allowed = [
            'id', 'a.id',
            'title', 'a.title',
            'alias', 'a.alias',
            'checked_out', 'a.checked_out',
            'checked_out_time', 'a.checked_out_time',
            'catid', 'a.catid', 'category_title',
            'state', 'a.state',
            'access', 'a.access', 'access_level',
            'created', 'a.created',
            'created_by', 'a.created_by',
            'modified', 'a.modified',
            'ordering', 'a.ordering',
            'featured', 'a.featured',
            'language', 'a.language',
            'hits', 'a.hits',
            'price', 'a.price',
            'min_price', 'a.min_price',
            'max_price', 'a.max_price',
            'good_price', 'a.good_price',
            'ishop_fields', 'a.ishop_fields',
            'manufacturers', 'a.manufacturers', 'a.manufacturer_id',
            'warehouses', 'a.warehouses', 'a.warehouse_id',
            'rating', 'a.rating',
            'publish_up', 'a.publish_up',
            'publish_down', 'a.publish_down',
            'author', 'a.author',
            'filter_tag',
        ];

        return in_array($ordering, $allowed, true) ? $ordering : 'a.price';
    }

    /**
     * Получает безопасное направление сортировки.
     *
     * @param   string  $key      Имя параметра запроса.
     * @param   string  $default  Значение по умолчанию.
     *
     * @return string
     *
     * @since 1.0.5
     */
    private function getDirection(string $key, string $default = 'ASC'): string
    {
        $direction = strtoupper($this->input->getCmd($key, $default));

        return in_array($direction, ['ASC', 'DESC'], true) ? $direction : $default;
    }

    /**
     * Загружает товары, соответствующие текущей странице результатов Finder.
     *
     * @param   int  $limit       Размер порции.
     * @param   int  $limitstart  Смещение списка.
     *
     * @return array<string, mixed>
     * @throws \Exception
     *
     * @since 1.0.5
     */
    private function loadFinderProducts(int $limit, int $limitstart): array
    {
        $app = Factory::getApplication();
        $input = $app->getInput();

        $input->set('option', 'com_finder');
        $input->set('view', 'search');
        $input->set('limit', $limit);
        $input->set('limitstart', $limitstart);

        /** @var SearchModel $finderModel */
        $finderModel = $app
            ->bootComponent('com_finder')
            ->getMVCFactory()
            ->createModel('Search', 'Site');

        $finderModel->getState();

        $finderResults = (array) $finderModel->getItems();
        $total = (int) $finderModel->getTotal();
        $ids = $this->extractProductIdsFromFinderResults($finderResults);
        $items = $ids ? $this->loadProductsByIds($ids) : [];
        $nextLimitstart = $limitstart + $limit;
        $query = $finderModel->getQuery();

        $params = $app->getParams();
        $currency = strtoupper($params->get('defaultCurrency', 'BYN'));

        return [
            'context'        => 'finder',
            'html'           => $this->renderProducts($items, $params),
            'currency'       => $currency,
            'total'          => $total,
            'limit'          => $limit,
            'limitstart'     => $limitstart,
            'nextLimitstart' => $nextLimitstart,
            'hasMore'        => $nextLimitstart < $total,
            'analyticsItems' => $this->buildAnalyticsItems($items, 'Search', $limitstart),
            'itemList'       => [
                'id'   => 'finder',
                'name' => $query && !empty($query->input) ? (string) $query->input : 'Search',
            ],
        ];
    }

    /**
     * Загружает товары iShop по списку ID с сохранением порядка Finder.
     *
     * @param   array<int, int>  $ids  Список ID товаров.
     *
     * @return array<int, object>
     * @throws \Exception
     *
     * @since 1.0.5
     */
    private function loadProductsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (!$ids) {
            return [];
        }

        $model = $this->getModel('Products', 'Site', ['ignore_request' => true]);

        $app = Factory::getApplication();
        $model->setState('params', $app->getParams());
        $model->setState('filter.warehouse_id', false);
        $model->setState('filter.published', 1);
        $model->setState('filter.access', 1);
        $model->setState('filter.language', Multilanguage::isEnabled());
        $model->setState('filter.products', $ids);
        $model->setState('list.ordering', 'FIELD(a.id, ' . implode(',', $ids) . ')');
        $model->setState('list.direction', '');
        $model->setState('list.limit', 0);

        return (array) $model->getItems();
    }

    /**
     * Получает ID товаров из результатов Finder, отбрасывая нетоварные записи.
     *
     * @param   array<int, object>  $results  Результаты Finder.
     *
     * @return array<int, int>
     *
     * @since 1.0.5
     */
    private function extractProductIdsFromFinderResults(array $results): array
    {
        $ids = [];

        foreach ($results as $result) {
            if (($result->context ?? '') !== 'com_ishop.product') {
                continue;
            }

            $id = isset($result->id) ? (int) $result->id : 0;

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Рендерит карточки товаров через активный site template layout.
     *
     * @param   array<int, object>  $items   Список товаров.
     * @param   mixed               $params  Параметры компонента.
     *
     * @return string
     *
     * @since 1.0.5
     */
    private function renderProducts(array $items, mixed $params): string
    {
        $this->prepareTemplateAssets();

        $html = '';

        foreach ($items as $item) {
            $html .= LayoutHelper::render('itheme.product.small', ['item' => $item, 'params' => $params]);
        }

        return $html;
    }

    /**
     * Регистрирует assets активного site template перед рендером layout-файлов.
     *
     * @return void
     *
     * @since 1.0.5
     */
    private function prepareTemplateAssets(): void
    {
        $app = Factory::getApplication();
        $template = $app->getTemplate(true);
        $registry = $app->getDocument()->getWebAssetManager()->getRegistry();

        if (!empty($template->parent)) {
            $registry->addTemplateRegistryFile($template->parent, $app->getClientId());
        }

        $registry->addTemplateRegistryFile($template->template, $app->getClientId());
    }

    /**
     * Формирует payload товаров для ecommerce-аналитики.
     *
     * @param   array<int, object>  $items         Список товаров.
     * @param   string              $categoryName  Название списка.
     * @param   int                 $offset        Смещение текущей порции.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 1.0.5
     */
    private function buildAnalyticsItems(array $items, string $categoryName, int $offset): array
    {
        $result = [];

        foreach ($items as $i => $product) {
            $result[] = [
                'item_id'       => (int) $product->id,
                'item_name'     => (string) $product->fullname,
                'discount'      => (float) $product->discount_size,
                'index'         => $offset + $i,
                'item_brand'    => (string) $product->manufacturer_title,
                'item_category' => $categoryName !== '' ? $categoryName : (string) ($product->category_title ?? ''),
                'price'         => ((float) $product->sale_price > 0) ? (float) $product->sale_price : (float) $product->price,
                'quantity'      => 1,
            ];
        }

        return $result;
    }
}
