<?php

/**
 * Acquia/CommerceManager/Model/Plugin/ProductPushOnCategoryAssignment.php
 *
 * Acquia Commerce Manager ProductPushOnCategoryAssignment Plugin
 *
 * All rights reserved. No unauthorized distribution or reproduction.
 */

namespace Acquia\CommerceManager\Model\Plugin;

use Acquia\CommerceManager\Helper\ProductBatch as BatchHelper;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\Category as CategoryEntity;
use Magento\Framework\Message\ManagerInterface as MessageManager;

class ProductPushOnCategoryAssignment
{

    /**
     * @var BatchHelper
     */
    private $batchHelper;

    /**
     * System Logger
     *
     * @var LoggerInterface $logger
     */
    protected $logger;

    /**
     * Message manager
     * @var MessageManager
     */
    protected $messageManager;

    /**
     * Constructor
     *
     * @param BatchHelper $batchHelper
     * @param LoggerInterface $logger
     * @param MessageManager $messageManager
     */
    public function __construct(
        BatchHelper $batchHelper,
        LoggerInterface $logger,
        MessageManager $messageManager
    ) {
        $this->batchHelper = $batchHelper;
        $this->logger = $logger;
        $this->messageManager = $messageManager;
    }

    /**
     * afterSave
     *
     * After Product Category save, update any affected products
     * in the sales rule index.
     *
     * @param CategoryEntity $subject
     * @param CategoryEntity $result
     *
     * @return CategoryEntity
     */
    public function afterSave(
        CategoryEntity $subject,
        CategoryEntity $result
    ) {
        // Dummy instruction to avoid code-sniff warning '$subject isn't used'.
        get_class($subject);

        $productIds = $result->getAffectedProductIds();

        if ($productIds) {
            $productIds = array_unique($productIds);

            // Get batch size from config.
            $batchSize = $this->batchHelper->getProductQueueBatchSize();

            // Do product push in batches.
            foreach (array_chunk($productIds, $batchSize, TRUE) as $chunk) {
                $batch = [];

                foreach ($chunk as $productId) {
                    $batch[$productId] = [
                        'product_id' => $productId,
                        'store_id' => null,
                    ];
                }

                if (!empty($batch)) {
                    // Push product ids in queue in batch.
                    $this->batchHelper->addBatchToQueue($batch);

                    $this->logger->info('Added products to queue for pushing in background.', [
                        'observer' => 'afterProductAssignmentToCategoryUpdated',
                        'batch' => $batch,
                    ]);
                }
            }

            if (!$this->batchHelper->getMessageQueueEnabled()) {
                $this->messageManager->addNotice(__('Your product assignments have been pushed to ACM for every impacted stores and are going to be queued there.'));
            }
            else {
                $this->messageManager->addNotice(__('Your product assignments have been pushed to ProductPush queue of Magento. Once processed they are going to be pushed to ACM for every impacted stores and queued there.'));
            }
        }

        return ($result);
    }

}
