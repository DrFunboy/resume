<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions as BaseExceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ExceptionHandler
{
    protected int $jsonFlags = JSON_UNESCAPED_SLASHES ^ JSON_UNESCAPED_UNICODE;

    public function __invoke(BaseExceptions $exceptions): BaseExceptions
    {
        $this->renderUnauthorized($exceptions);
        $this->renderNotFound($exceptions);

        return $exceptions;
    }

    protected function renderUnauthorized(BaseExceptions $exceptions): void
    {
        $exceptions->renderable(
            fn (AuthenticationException $e, ?Request $request = null) => $this->response(
                message: __('Unauthorized'),
                code: 401,
                asJson: $request?->expectsJson() ?? false
            )
        );
    }

    protected function renderNotFound(BaseExceptions $exceptions): void
    {
        $exceptions->renderable(
            fn (NotFoundHttpException $e, ?Request $request = null) => $this->response(
                message: __('Not Found'),
                code: 404,
                asJson: $request?->expectsJson() ?? false
            )
        );
    }

    protected function response(string $message, int $code, bool $asJson): Response
    {
        if ($asJson) {
            return response()->json(compact('message'), $code, options: $this->jsonFlags);
        }

        $this->registerErrorViewPaths();

        return response()->view($this->view($code), status: $code);
    }

    protected function view(int $code): string
    {
        return view()->exists('errors::' . $code) ? 'errors::' . $code : 'errors::400';
    }

    protected function registerErrorViewPaths(): void
    {
        $paths = (array)config('view.paths');
        View::replaceNamespace(
            'errors',
            collect($paths)
                ->map(fn (mixed $path) => ((string)$path . '/errors'))
                ->push($this->vendorViews())
                ->all()
        );
    }

    protected function vendorViews(): string
    {
        return __DIR__ . '/../../vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views';
    }
}
