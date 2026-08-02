<?php

declare(strict_types=1);

namespace AmpacheDiscogs\Tests;

use AmpacheDiscogs\Discogs;
use WpOrg\Requests\Response;
use WpOrg\Requests\Response\Headers;

/**
 * Captures the request Discogs would have sent and replays canned responses.
 * Overrides the network and the sleep, so the suite neither calls the api nor waits.
 */
class RecordingDiscogs extends Discogs
{
    /** @var list<array<string, string>> the headers sent alongside each url */
    public array $headers = [];

    /** @var list<int> every sleep the client asked for, in microseconds */
    public array $sleeps = [];

    /** @var list<string> every url passed to the transport, in order */
    public array $urls = [];

    /** @var list<array{status: int, body: string, headers: array<string, string>}> */
    private array $queue;

    /**
     * @param list<array{status: int, body: string, headers: array<string, string>}> $queue
     *        responses to replay in order; the last one repeats once the queue is drained
     */
    public function __construct(
        string $key,
        string $secret,
        array $queue = [],
    ) {
        parent::__construct($key, $secret);

        $this->queue = ($queue === [])
            ? [['status' => 200, 'body' => '{"id":1}', 'headers' => []]]
            : $queue;
    }

    /**
     * The most recent url split into its path and its decoded query parameters.
     * @return array{path: string, query: array<string, string>}
     */
    public function lastRequest(): array
    {
        $url = $this->urls[count($this->urls) - 1];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        /** @var array<string, string> $query */
        return [
            'path' => (string) parse_url($url, PHP_URL_PATH),
            'query' => $query,
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    protected function _http_get(string $url, array $headers): Response
    {
        $this->urls[]    = $url;
        $this->headers[] = $headers;

        $canned = $this->queue[count($this->urls) - 1] ?? $this->queue[count($this->queue) - 1];

        $responseHeaders = new Headers();
        foreach ($canned['headers'] as $name => $value) {
            $responseHeaders[$name] = $value;
        }

        $response              = new Response();
        $response->body        = $canned['body'];
        $response->status_code = $canned['status'];
        $response->success     = $canned['status'] >= 200 && $canned['status'] < 300;
        $response->headers     = $responseHeaders;

        return $response;
    }

    protected function _sleep(int $microseconds): void
    {
        $this->sleeps[] = $microseconds;
    }
}
