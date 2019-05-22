<?php

namespace App\Common\Exceptions;

class GeneralJsonException extends \Exception
{
    use ExceptionTrait;

    const CODE    = 500;
    const MESSAGE = 'General Json Exception';
}
