<?php
/**
 * Extend Warranty
 *
 * @author      Extend Magento Team <magento@guidance.com>
 * @category    Extend
 * @package     Warranty
 * @copyright   Copyright (c) 2021 Extend Inc. (https://www.extend.com/)
 */

declare(strict_types=1);

namespace Extend\Warranty\Model\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Extend\Warranty\Api\SyncInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Class Sync
 */
class Sync implements SyncInterface
{
    /**
     * Product Repository Interface
     *
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * Search Criteria Builder
     *
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * Batch size
     *
     * @var int
     */
    private $batchSize;

    /**
     * Count of batches
     *
     * @var int
     */
    private $countOfBatches = 0;

    /**
     * Sync constructor
     *
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param int $batchSize
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        int $batchSize = self::DEFAULT_BATCH_SIZE
    ) {
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->batchSize = $batchSize;
    }

    /**
     * Get batch size
     *
     * @return int
     */
    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * Get products
     *
     * @param int $batchNumber
     * @param array $filters
     * @return array
     */
    public function getProducts(int $batchNumber = 1, array $filters = []): array
    {
        $this->searchCriteriaBuilder->addFilter(ProductInterface::TYPE_ID, Type::TYPE_CODE, 'neq');

        foreach ($filters as $field => $value) {
            if ($field === ProductInterface::UPDATED_AT) {
                $this->searchCriteriaBuilder->addFilter($field, $value, 'gt');
            } else {
                $this->searchCriteriaBuilder->addFilter($field, $value);
            }
        }

        $batchSize = $this->getBatchSize();
        $this->searchCriteriaBuilder->setPageSize($batchSize);
        $this->searchCriteriaBuilder->setCurrentPage($batchNumber);

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchResults = $this->productRepository->getList($searchCriteria);

        $this->setCountOfBatches($searchResults->getTotalCount());

        return $searchResults->getItems();
    }

    /**
     * Get count of batches to process
     *
     * @return int
     */
    public function getCountOfBatches(): int
    {
        return $this->countOfBatches;
    }

    /**
     * Set batch size
     *
     * @param int $batchSize
     */
    public function setBatchSize(int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    /**
     * Set count of batches to process
     *
     * @param int $countOfProducts
     */
    public function setCountOfBatches(int $countOfProducts): void
    {
        $batchSize = $this->getBatchSize();
        $this->countOfBatches = (int)ceil($countOfProducts/$batchSize);
    }
}
