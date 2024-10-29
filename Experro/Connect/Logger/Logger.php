<?php
namespace Experro\Connect\Logger;

use Monolog\Logger as MonoLogger;

class Logger extends MonoLogger
{
    public function __construct(
        \Experro\Connect\Logger\Handler $handler
    ) {
        parent::__construct('custom_logger', [$handler]);
    }
}
