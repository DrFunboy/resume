<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    public static function error(
        string $message = null,
        array|object $errors = null,
        int $code = Response::HTTP_UNPROCESSABLE_ENTITY
    ): JsonResponse {
        $response = [
            'success' => false
        ];
        if (!empty($message)) {
            $response['message'] = $message;
        }
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $code);
    }

    public static function success(
        array|object $data = null,
        string $message = null,
        ?int $code = Response::HTTP_OK,
        array $params = null
    ): JsonResponse {
        if ($code === null) {
            $code = Response::HTTP_OK;
        }
        $response = [
            'success' => true
        ];
        if (!empty($message)) {
            $response['message'] = $message;
        }
        if ($data !== null) {
            $response['data'] = $data;
        }
        if ($params !== null) {
            $response = array_merge($response, $params);
        }
        return response()->json($response, $code);
    }
}
