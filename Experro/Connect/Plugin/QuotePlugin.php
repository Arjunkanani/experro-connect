<?php
/**
 * Experro
 * Copyright (C) 2024 Experro <support@experro.com>
 *
 * @category Experro
 * @package Experro_Connect
 * @copyright Copyright (c) 2024 Experro (http://www.experro.com/)
 * @author Experro <support@experro.com>
 */

namespace Experro\Connect\Plugin;

use Magento\Quote\Api\Data\CartInterface;


class QuotePlugin{

    /**
     * @var \Magento\Quote\Api\Data\CartItemExtensionFactory
     */
    protected $cartItemExtension;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @param \Magento\Quote\Api\Data\CartItemExtensionFactory $cartItemExtension
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     */
    public function __construct(
        \Magento\Quote\Api\Data\CartItemExtensionFactory $cartItemExtension, 
        \Magento\Catalog\Api\ProductRepositoryInterfaceFactory $productRepository        ) {
        $this->cartItemExtension = $cartItemExtension;
        $this->productRepository = $productRepository;
    }

    /**
     * Add attribute values
     *
     * @param   \Magento\Quote\Api\CartRepositoryInterface $subject,
     * @param   $quote
     * @return  $quoteData
     */
    public function afterGet(
    \Magento\Quote\Api\CartRepositoryInterface $subject, $quote
    ) {
        $quoteData = $this->setAttributeValue($quote);
        return $quoteData;
    }

    /**
     * Add attribute values
     *
     * @param   \Magento\Quote\Api\CartRepositoryInterface $subject,
     * @param   $quote
     * @return  $quoteData
     */
    public function afterGetActiveForCustomer(
    \Magento\Quote\Api\CartRepositoryInterface $subject, $quote
    ) {
        $quoteData = $this->setAttributeValue($quote);
        return $quoteData;
    }

    /**
     * set value of attributes
     *
     * @param   $product,
     * @return  $extensionAttributes
     */
    private function setAttributeValue($quote) {
        $data = [];
        
        if ($quote->getItemsCount()) {
            foreach ($quote->getAllItems() as $item) { 
                $data = [];
                $extensionAttributes = $item->getExtensionAttributes();
                if ($extensionAttributes === null) {
                    $extensionAttributes = $this->cartItemExtension->create();
                }
                $productData = $this->productRepository->create()->get($item->getSku());                
                $extensionAttributes->setImage($productData->getThumbnail());
                $extensionAttributes->setUrl($productData->getUrlKey());
                
                $options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
                

                if (isset($options['options']) && !empty($options['options'])) {
                    $extensionAttributes->setAttributesInfo($options['options']);
                }
                
                if (isset($options['attributes_info']) && !empty($options['attributes_info'])) {
                    $extensionAttributes->setAttributesInfo($options['attributes_info']);
                }

                if ($item->getproductType() == 'configurable') {
                    $extensionAttributes->setVariantId($productData->getId());
                    $extensionAttributes->setId($item->getProduct()->getId());
                }else{
                    $extensionAttributes->setId($productData->getId());
                }

                $item->setExtensionAttributes($extensionAttributes);
            }
        }

        return $quote;
    }
}