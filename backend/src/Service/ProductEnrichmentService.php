<?php

namespace Ilange\Component\Ishop\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use RuntimeException;

/**
 * Доверенный путь обогащения данных о товарах
 * @since 1.0.37
 */
final class ProductEnrichmentService
{
    private array $deferredAfterSaveEvents = [];

    /**
     * @param ?DatabaseInterface $database Активное подключение внешней транзакции.
     * @param bool $deferAfterSave Отложить after-save events до внешнего commit.
     * @since 1.0.37
     */
    public function __construct(
        private readonly ?DatabaseInterface $database = null,
        private readonly bool $deferAfterSave = false
    ) {
    }

    /**
     * Сохраняет разрешенные enrichment-изменения через таблицу com_ishop и content events.
     * @since 1.0.37
     */
    public function save(int $productId, array $changes): void
    {
        $app = Factory::getApplication();
        $dispatcher = $app->getDispatcher();
        $table = $app->bootComponent('com_ishop')
            ->getMVCFactory()
            ->createTable('Product', 'Administrator');

        if ($this->database !== null) {
            $table->setDatabase($this->database);
        }

        if (!$table->load($productId)) {
            throw new RuntimeException('The product could not be loaded.');
        }
        if (!$table->bind($changes) || !$table->check()) {
            throw new RuntimeException('The product changes are invalid.');
        }

        $data = $table->getProperties(true);
        PluginHelper::importPlugin('content', null, true, $dispatcher);
        $before = new BeforeSaveEvent('onContentBeforeSave', [
            'context' => 'com_ishop.product',
            'subject' => $table,
            'isNew' => false,
            'data' => $data,
        ]);
        $result = $dispatcher->dispatch('onContentBeforeSave', $before)->getArgument('result', []);

        if (in_array(false, $result, true) || !$table->store(true)) {
            throw new RuntimeException('The product could not be saved.');
        }

        if ($this->deferAfterSave) {
            $this->deferredAfterSaveEvents[] = [$table, $data];
            return;
        }

        $this->dispatchAfterSave($table, $data);
    }

    /**
     * Отправляет накопленные after-save events после успешного внешнего commit.
     * @since 1.0.37
     */
    public function dispatchDeferredAfterSaveEvents(): void
    {
        foreach ($this->deferredAfterSaveEvents as [$table, $data]) {
            $this->dispatchAfterSave($table, $data);
        }

        $this->deferredAfterSaveEvents = [];
    }

    private function dispatchAfterSave(object $table, array $data): void
    {
        Factory::getApplication()->getDispatcher()->dispatch('onContentAfterSave', new AfterSaveEvent('onContentAfterSave', [
            'context' => 'com_ishop.product',
            'subject' => $table,
            'isNew' => false,
            'data' => $data,
        ]));
    }
}
