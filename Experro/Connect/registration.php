<?php
/**
 * Experro
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Experro.com license that is
 * available through the world-wide-web at this URL:
 * https://www.experro.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Experro
 * @package     Experro_Webhook
 * @copyright   Copyright (c) Experro (https://www.experro.com/)
 * @license     https://www.experro.com/LICENSE.txt
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Experro_Connect',
    __DIR__
);
