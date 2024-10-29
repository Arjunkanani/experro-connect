<?php
/**
 * Experro
 * Copyright (C) 2024 Experro <support@experro.com>
 *
 * @category Experro
 * @package Experro_ProductsApi
 * @copyright Copyright (c) 2024 Experro (http://www.experro.com/)
 * @author Experro <support@experro.com>
 */

namespace Experro\Connect\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * @api
 * @since 1.0.0
 */
interface UserdefinedProductAttributeInterface extends ExtensibleDataInterface
{
    /**
     * @return int|null
     * @since 1.0.0
     */
    public function getAttributeId();

    /**
     * @param int $attributeId
     * @return $this
     * @since 1.0.0
     */
    public function setAttributeId($attributeId);

    /**
     * Get attribute code
     *
     * @return string
     * @since 1.0.0
     */
    public function getAttributeCode();

    /**
     * Set attribute code
     *
     * @param string $attributeCode
     * @return $this
     * @since 1.0.0
     */
    public function setAttributeCode($attributeCode);
}
