<?php

namespace App\Common\Exceptions;

class CompanionMarketServerException extends \Exception
{
    use ExceptionTrait;
    
    const CODE    = 400;
    const MESSAGE = 'Invalid server provided for request.';
}
