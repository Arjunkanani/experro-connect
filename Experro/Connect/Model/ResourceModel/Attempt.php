<?php
namespace Experro\Connect\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Attempt extends AbstractDb
{
    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('experro_connect_attempt', 'id'); // 'experro_connect_attempt' is the table name, 'id' is the primary key
    }
}
