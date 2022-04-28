<?php
/**
 * Extend Warranty
 *
 * @author      Extend Magento Team <magento@guidance.com>
 * @category    Extend
 * @package     Warranty
 * @copyright   Copyright (c) 2022 Extend Inc. (https://www.extend.com/)
 */

namespace Extend\Warranty\Plugin\Checkout\CustomerData;

use Magento\Framework\UrlInterface;
use Magento\Checkout\CustomerData\AbstractItem;
use Extend\Warranty\Model\Product\Type;
use Extend\Warranty\Helper\Api\Data as DataHelper;
use Extend\Warranty\Helper\Tracking as TrackingHelper;

/**
 * Class AbstractItemPlugin
 */
class AbstractItemPlugin
{
    /**
     * Url builder
     *
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * Data Helper
     *
     * @var DataHelper
     */
    private $dataHelper;

    /**
     * Tracking Helper
     *
     * @var TrackingHelper
     */
    private $trackingHelper;

    /**
     * @param UrlInterface $urlBuilder
     * @param DataHelper $dataHelper
     * @param TrackingHelper $trackingHelper
     */
    public function __construct(
        UrlInterface $urlBuilder,
        DataHelper $dataHelper,
        TrackingHelper $trackingHelper
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->dataHelper = $dataHelper;
        $this->trackingHelper = $trackingHelper;
    }

    /**
     * Set 'isWarranty' flag for product image
     * Set data for render add warranty button on minicart
     *
     * @param AbstractItem $subject
     * @param array $result
     * @param $item
     * @return array
     */
    public function afterGetItemData(AbstractItem $subject, array $result, $item): array
    {
        $result['product_image']['isWarranty'] = isset($result['product_type']) && $result['product_type'] === Type::TYPE_CODE;

        if ($this->isShoppingCartOffersEnabled() && !$this->hasWarranty($item)) {
            $result['product_can_add_warranty'] = true;
            $result['warranty_add_url'] = $this->getWarrantyAddUrl();
            $result['product_parent_id'] = $this->getParentId($item);
            $result['product_is_tracking_enabled'] = $this->isTrackingEnabled();
        } else {
            $result['product_can_add_warranty'] = false;
        }

        return $result;
    }

    /**
     * Check if has warranty in cart
     *
     * @param $item
     * @return bool
     */
    private function hasWarranty($item): bool
    {
        $hasWarranty = false;
        $quote = $item->getQuote();
        $sku = $item->getSku();
        $items = $quote->getAllVisibleItems();
        foreach ($items as $item) {
            if ($item->getProductType() === Type::TYPE_CODE) {
                $associatedProduct = $item->getOptionByCode('associated_product');
                if ($associatedProduct && $associatedProduct->getValue() === $sku) {
                    $hasWarranty = true;
                }
            }
        }

        return $hasWarranty;
    }

    /**
     * @return string
     */
    private function getWarrantyAddUrl(): string
    {
        return $this->urlBuilder->getUrl('warranty/cart/add');
    }

    /**
     * @param $item
     * @return string
     */
    private function getParentId($item): string
    {
        return $item->getOptionByCode('simple_product') ? $item->getProductId() : '';
    }

    /**
     * Check if shopping cart offers enabled
     *
     * @return bool
     */
    private function isShoppingCartOffersEnabled(): bool
    {
        return $this->dataHelper->isShoppingCartOffersEnabled();
    }

    /**
     * Check if tracking enabled
     *
     * @return bool
     */
    private function isTrackingEnabled(): bool
    {
        return $this->trackingHelper->isTrackingEnabled();
    }
}