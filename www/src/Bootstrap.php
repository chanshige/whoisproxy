<?php

declare(strict_types=1);

namespace Chanshige\WhoisProxy;

use Chanshige\WhoisProxy\Module\App;
use BEAR\Sunday\Extension\Application\AppInterface;
use Error;
use ErrorException;
use Exception;

use function assert;

/**
 * @psalm-import-type Globals from \BEAR\Sunday\Extension\Router\RouterInterface
 * @psalm-import-type Server from \BEAR\Sunday\Extension\Router\RouterInterface
 */
final class Bootstrap
{
    /**
     * @psalm-param Globals $globals
     * @psalm-param Server  $server
     * @phpstan-param array<string, mixed> $globals
     * @phpstan-param array<string, mixed> $server
     *
     * @return 0|1
     */
    public function __invoke(string $context, array $globals, array $server): int
    {
        $app = Injector::getInstance($context)->getInstance(AppInterface::class);
        assert($app instanceof App);
        if ($app->httpCache->isNotModified($server)) {
            $app->httpCache->transfer();

            return 0;
        }

        $request = $app->router->match($globals, $server);
        try {
            $response = $app->resource->{$request->method}->uri($request->path)($request->query);
            $response->transfer($app->responder, $server);

            return 0;
        } catch (Error $e) {
            $error = new ErrorException($e->getMessage(), $e->getCode(), E_ERROR, $e->getFile(), $e->getLine());
            $app->error->handle($error, $request)->transfer();

            return 1;
        } catch (Exception $e) {
            $app->error->handle($e, $request)->transfer();

            return 1;
        }
    }
}
