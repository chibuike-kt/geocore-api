<?php

namespace App\Exceptions;

use Exception;

class InvalidGeometryException extends Exception
{
  public function __construct(string $message = 'Invalid geometry provided.')
  {
    parent::__construct($message);
  }
}
