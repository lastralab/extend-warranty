<?php
/**
 * Extend Warranty
 *
 * @author      Extend Magento Team <magento@guidance.com>
 * @category    Extend
 * @package     Warranty
 * @copyright   Copyright (c) 2021 Extend Inc. (https://www.extend.com/)
 */
namespace Extend\Warranty\Observer;

/**
 * Class QuoteRemoveItem
 *
 * QuoteRemoveItem Observer
 */
class QuoteRemoveItem implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Extend\Warranty\Helper\Tracking
     */
    protected $_trackingHelper;

    /**
     * QuoteRemoveItem constructor.
     * @param \Extend\Warranty\Helper\Tracking $trackingHelper
     */
    public function __construct(
        \Extend\Warranty\Helper\Tracking $trackingHelper
    ) {
        $this->_trackingHelper = $trackingHelper;
    }

    /**
     * Observer execute
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Quote\Model\Quote\Item $quoteItem */
        $quoteItem = $observer->getData('quote_item');
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $quoteItem->getQuote();

        //if the item being removed is a warranty offer, send tracking for the offer removed, if tracking enabled
        if ($quoteItem->getProductType() === \Extend\Warranty\Model\Product\Type::TYPE_CODE) {
            if ($this->_trackingHelper->isTrackingEnabled()) {
                $warrantySku = (string)$quoteItem->getOptionByCode('associated_product')->getValue();
                $planId = (string)$quoteItem->getOptionByCode('warranty_id')->getValue();
                $trackingData = [
                    'eventName' => 'trackOfferRemovedFromCart',
                    'productId' => $warrantySku,
                    'planId'    => $planId,
                ];

                $this->_trackingHelper->setTrackingData($trackingData);

                $trackProduct = true;
                $items = $quote->getAllItems();
                foreach ($items as $item) {
                    if ($item->getSku() === $warrantySku) {
                        $trackProduct = false;
                        break;
                    }
                }

                if ($trackProduct) {
                    $trackingData = [
                        'eventName' => 'trackProductRemovedFromCart',
                        'productId' => $warrantySku,
                    ];

                    $this->_trackingHelper->setTrackingData($trackingData);
                }
            }
            return;
        }

        //this is a regular product, check if there is an associated warranty item
        /** @var \Magento\Quote\Model\Quote\Item $warrantyItem */
        $warrantyItem = $this->_trackingHelper->getWarrantyItemForQuoteItem($quoteItem);
        if (!$warrantyItem && $this->_trackingHelper->isTrackingEnabled()) {
            //there is no associated warranty item. Just track the product removal
            $sku = $quoteItem->getSku();
            $trackingData = [
                'eventName' => 'trackProductRemovedFromCart',
                'productId' => $sku,
            ];
            $this->_trackingHelper->setTrackingData($trackingData);
            return;
        }

        //there is an associated warranty item, remove it
        //the removal will dispatch this event again where the offer removal will be tracked above

        $removeWarranty = true;
        $items = $quote->getAllItems();
        foreach ($items as $item) {
            if ($item->getSku() === $quoteItem->getSku()) {
                $removeWarranty = false;
                break;
            }
        }

        if ($warrantyItem && $removeWarranty) {
            $quote->removeItem($warrantyItem->getItemId());
        }
    }
}
