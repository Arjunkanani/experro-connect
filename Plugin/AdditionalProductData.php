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

use Magento\Swatches\Model\ResourceModel\Swatch\CollectionFactory as SwatchCollectionFactory;
use Magento\CatalogInventory\Api\StockStateInterface as StockItem;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku as SalableQuantityDataBySku;
use Magento\CatalogInventory\Model\Stock\StockItemRepository as StockItemRepository;
use Magento\Catalog\Model\CategoryFactory as CategoryFactory;
use Magento\Eav\Model\Entity\Attribute as Attribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as EavCollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;


class AdditionalProductData{
    
    /**
     * @var StockRegistryInterface
    */
    private $stockItem;

    /** @var SwatchCollectionFactory */
    private $swatchCollectionFactory;
    /**
     * @var GetSalableQuantityDataBySku
    */
    private $salableQuantityData;
    /**
     * @var StockItemRepository
    */
    private $stockItemRepository;
    /**
     * @var CategoryFactory
    */
    private $categoryFactory;
    /**
     * @var Attribute
    */
    private $attribute;
    /**
     * @var Config
    */
    private $eavConfig;

    /**
     * @var EavCollectionFactory
     */
    private $eavCollectionFactory;
    protected $stockRegistry;

    public function __construct(
        StockItem $stockItem,
        SalableQuantityDataBySku $salableQuantityDataBySku,
        StockItemRepository $stockItemRepository,
        CategoryFactory $categoryFactory,
        Attribute $attribute,
        EavConfig $eavConfig,
        EavCollectionFactory $eavCollectionFactory,
        SwatchCollectionFactory $swatchCollectionFactory,
        StockRegistryInterface $stockRegistry        
    )
    {
        $this->stockItem = $stockItem;
        $this->salableQuantityData = $salableQuantityDataBySku;
        $this->stockItemRepository = $stockItemRepository;
        $this->categoryFactory = $categoryFactory;
        $this->attribute = $attribute;
        $this->eavConfig = $eavConfig;
        $this->eavCollectionFactory = $eavCollectionFactory;
        $this->swatchCollectionFactory = $swatchCollectionFactory;
        $this->stockRegistry = $stockRegistry;
    }

    /**
     * Changes in Experro get all product api response
     */
    public function afterGetAllProductDetails(
        \Experro\Connect\Api\GetAllProductDetailsInterface $subject,
        $products
    ) {
       
        foreach ($products->getItems() as $key => $product) {
            $productKeysData = [];
            foreach($product->getData() as $pk => $pv){
                $productKeysData[] = $pk;
            }
            // For userdefined product attribute
            $eavAttrCollection = $this->eavCollectionFactory->create()->addFieldToFilter(
                'is_user_defined',
                ['eq' => 1]
            );

            // Get the attribute's value label if applicable


            $eavAttrCollectionResult = [];
            foreach ($eavAttrCollection->getItems() as $attribute) {
                if(in_array($attribute->getAttributeCode(), $productKeysData)){

                    $attributeValue = $product->getData($attribute->getAttributeCode());
                    $valueLabel = $this->getAttributeValueLabel($attribute, $attributeValue);
                    $attributeType = $attribute->getIsUserDefined() ? 'user_defined' : 'system_defined';

                    // Only add to result if $valueLabel is not null
                    if ($valueLabel !== null) {
                        /** @var $attribute \Magento\Catalog\Model\ResourceModel\Eav\Attribute */
                        $eavAttrCollectionResult[] = [
                            'attribute_code' => $attribute->getAttributeCode(),
                            'attribute_id' => $attribute->getId(),
                            'label' => $attribute->getFrontendLabel(),
                            'value' => $valueLabel,
                            'type' => $attributeType
                        ];
                    }
                }
            }

            // Get Salable_qty value
            $notAllowedSalableType = array("configurable", "bundle", "grouped");
            if(!in_array($product->getTypeId(),$notAllowedSalableType)){
                $salableqty = $this->salableQuantityData->execute($product->getSku());
            }
            $stockStatus = "out-of-stock";
            // Get Product status value

            try {
                $stockItem = $this->stockRegistry->getStockItemBySku($product->getSku());
                $configurableProductStockStatus = $stockItem->getData('is_in_stock');

                if ($configurableProductStockStatus != 0) {
                    $stockStatus = "in-stock";
                }

                $productStock = $this->stockItemRepository->get($product->getId());
                $notifyStockQty = $productStock->getNotifyStockQty();
            } catch (\Exception $e) {
                $notifyStockQty = 0;
            }

            // get special price
            //$specialPrice = $product->getSpecialPrice();

            // get product default quantity
            $qty = $this->stockItem->getStockQty($product->getId(), $product->getStore()->getWebsiteId());
            $salableqty = $salableqty[0]['qty'] ?? $qty;

            $extensionattributes = $product->getExtensionAttributes();
            // add category name
            $categoryLinks = [];
            foreach ((array) $extensionattributes->getCategoryLinks() as $categoryLink) {
                $category = $this->categoryFactory->create()->load($categoryLink->getCategoryId());
                $categoryName = $category->getName();
                $categoryLinks[$categoryLink->getCategoryId()]['position'] = $categoryLink->getPosition();
                $categoryLinks[$categoryLink->getCategoryId()]['category_id'] = $categoryLink->getCategoryId();
                $categoryLinks[$categoryLink->getCategoryId()]['category_name'] = $categoryName;
            }
            if(!empty($categoryLinks)){
                $extensionattributes->setCategoryLinks($categoryLinks);
            }

            // add configurable value lable
            $configurableProductOptions = [];

            
            foreach ((array) $extensionattributes->getConfigurableProductOptions() as $productOptions) {
                $optionValues = $productOptions->getValues();
                $attributeId = $productOptions->getAttributeId();
                $attribute = $this->attribute->load($attributeId);
                $attributeCode = $attribute->getAttributeCode();
                $attribute =  $this->eavConfig->getAttribute('catalog_product', $attributeCode);   
                $attributeType = $attribute->getSwatchInputType();

                // Determine the actual attribute type
                $attributeType = $attribute->getData('swatch_input_type') ? $attribute->getData('swatch_input_type') : $attribute->getFrontendInput();
                
                $configurableProductOptions[$productOptions->getId()]['id'] = $productOptions->getId();
                $configurableProductOptions[$productOptions->getId()]['attribute_id'] = $attributeId;
                $configurableProductOptions[$productOptions->getId()]['attribute_code'] = $attributeCode;
                $configurableProductOptions[$productOptions->getId()]['attribute_type'] = $attributeType;
                $configurableProductOptions[$productOptions->getId()]['label'] = $attribute->getFrontendLabel();
                $configurableProductOptions[$productOptions->getId()]['position'] = $productOptions->getPosition();
                $configurableProductOptions[$productOptions->getId()]['product_id'] = $productOptions->getProductId();

                $configurableProductOptionsValues = [];
                foreach ((array) $optionValues as $k => $optionValue) {
                
                    $swatchData = $this->getAttributeOptionSwatchValues($optionValue->getValueIndex());
                    $configurableProductOptionsValues[$productOptions->getId()][$k]['value_index'] = $optionValue->getValueIndex();
                    $optionlabel =  $attribute->getSource()->getOptionText($optionValue->getValueIndex());
                    $configurableProductOptionsValues[$productOptions->getId()][$k]['value_label'] = $optionlabel; 
                    if(!empty($swatchData)){
                        $configurableProductOptionsValues[$productOptions->getId()][$k]['swatch_data'] = $swatchData; 
                    }   
                }
                $configurableProductOptions[$productOptions->getId()]['values'] = $configurableProductOptionsValues;
            }
            
            if(!empty($configurableProductOptions)){
                $extensionattributes->setConfigurableProductOptions($configurableProductOptions);
            }
            
            if(!empty($eavAttrCollectionResult)){
                $extensionattributes->setUserDefinedProductAttribute($eavAttrCollectionResult);
            }

            $extensionattributes->setDefaultQuantity($qty);
            $extensionattributes->setSaleableQuantity($salableqty);
            $extensionattributes->setStockStatus($stockStatus);
            $extensionattributes->setNotifyStockQty($notifyStockQty);
            //$extensionattributes->setSpecialPrice($specialPrice);
            
            $product->setExtensionAttributes($extensionattributes);
            
        }
        return $products;
    }

    private function getAttributeOptionSwatchValues(int $optionId)
    {
        $swatchValues = 0;
        $collection = $this->swatchCollectionFactory->create();
        $collection->addFieldToFilter('option_id', $optionId);

        foreach ($collection as $item) {
            $swatchValues = $item->getData('value');
        }

        return $swatchValues;
    }

    private function getAttributeValueLabel($attribute, $value)
    {
        $valueLabel = $value; // Default to the raw value if no label is found
        
        if ($attribute->getFrontendInput() === 'select' || $attribute->getFrontendInput() === 'multiselect') {
            $options = $attribute->getSource()->getAllOptions(false);
            
            foreach ($options as $option) {
                if ($option['value'] == $value) {
                    $valueLabel = $option['label'];
                    break;
                }
            }
        }
        
        return $valueLabel;
    }
}