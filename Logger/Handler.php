<?php
namespace Experro\Connect\Logger;

use Monolog\Logger;
use Magento\Framework\Logger\Handler\Base;

class Handler extends Base
{
    protected $fileName = '/var/log/experro_connect.log';
    protected $loggerType = Logger::DEBUG;
}
