<?php

namespace App\Http\Controllers;

abstract class Controller
{

    protected function responseMessage($message, $code = 200)
    {
        return response()->json(['message' => $message], $code);
    }

}
