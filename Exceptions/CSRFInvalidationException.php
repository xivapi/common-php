<?php

namespace App\Common\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class CSRFInvalidationException extends HttpException
{
    use ExceptionTrait;

    const CODE    = 400;
    const MESSAGE = 'Could not confirm the CSRF token from SSO Provider. Please try again.';
}
