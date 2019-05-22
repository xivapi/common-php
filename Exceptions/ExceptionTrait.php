<?php

namespace App\Common\Exceptions;

trait ExceptionTrait
{
    public function __construct($message = self::MESSAGE, $code = 0)
    {
        parent::__construct($message, $code);
    }
}
