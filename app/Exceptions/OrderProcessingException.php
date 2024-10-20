<?php

namespace App\Exceptions;

use Exception;

class OrderProcessingException extends Exception
{
    protected $message;

    public function __construct($message = "Error processing the order.", $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render($request)
    {
        if ($request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage()
            ], 400);
        }

        return false;
    }
}
