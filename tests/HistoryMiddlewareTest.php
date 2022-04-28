<?php

/*
 * This file is part of the CsaGuzzleBundle package
 *
 * (c) Charles Sarrazin <charles@sarraz.in>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Csa\Tests\GuzzleHttp\Middleware\History;

use Csa\GuzzleHttp\Middleware\History\History;
use Csa\GuzzleHttp\Middleware\History\HistoryMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class HistoryMiddlewareTest extends TestCase
{
    public function testMiddleware(): void
    {
        $response = new Response(204);
        $mocks = \array_fill(0, 2, $response);
        $mock = new MockHandler($mocks);
        $handler = HandlerStack::create($mock);

        $storage = new History();

        $handler->push(new HistoryMiddleware($storage));

        $client = new Client(['handler' => $handler]);

        $client->get('http://foo.bar');
        $client->get('http://foo.bar');

        $this->assertCount(2, $storage);
        $storage->rewind();
        $req = $storage->current();
        $res = $storage[$req];
        $this->assertSame($response, $res['response']);
        $this->assertArrayHasKey('options', $res);
        $this->assertArrayHasKey('info', $res);
        $this->assertArrayHasKey('error', $res);
    }

    public function testHistoryShouldHaveOneEntryIfRequestChangesBeforeEntryInMiddleware(): void
    {
        $response = new Response(204);
        $mocks = \array_fill(0, 2, $response);
        $mock = new MockHandler($mocks);
        $handler = HandlerStack::create($mock);

        $storage = new History();

        $handler->push(function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $request = $request->withAddedHeader('x-time', \time());

                return $handler($request, $options);
            };
        });
        $handler->push(new HistoryMiddleware($storage));

        $client = new Client(['handler' => $handler, 'on_stats' => [$storage, 'addStats']]);
        $client->get('http://foo.bar');

        $this->assertCount(1, $storage);
    }

    public function testHistoryShouldHaveTwoEntriesIfRequestChangesAfterEntryInMiddleware(): void
    {
        $response = new Response(204);
        $mocks = \array_fill(0, 2, $response);
        $mock = new MockHandler($mocks);
        $handler = HandlerStack::create($mock);

        $storage = new History();

        $handler->push(new HistoryMiddleware($storage));
        $handler->push(function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $request = $request->withAddedHeader('x-time', \time());

                return $handler($request, $options);
            };
        });

        $client = new Client(['handler' => $handler, 'on_stats' => [$storage, 'addStats']]);
        $client->get('http://foo.bar');

        $this->assertCount(2, $storage);
    }
}
