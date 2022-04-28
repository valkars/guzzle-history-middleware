<?php

/*
 * This file is part of the CsaGuzzleBundle package
 *
 * (c) Charles Sarrazin <charles@sarraz.in>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Csa\GuzzleHttp\Middleware\History;

use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\RequestInterface;

/**
 * History Middleware.
 *
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class HistoryMiddleware
{
    public function __construct(private readonly History $container)
    {
    }

    public function __invoke(callable $handler): \Closure
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $handler($request, $options)->then(
                function ($response) use ($request, $options) {
                    $this->container->mergeInfo($request, [
                        'response' => $response,
                        'error' => null,
                        'options' => $options,
                        'info' => [],
                    ]);

                    return $response;
                },
                function ($reason) use ($request, $options) {
                    $this->container->mergeInfo($request, [
                        'response' => null,
                        'error' => $reason,
                        'options' => $options,
                        'info' => [],
                    ]);

                    return new RejectedPromise($reason);
                }
            );
        };
    }
}
