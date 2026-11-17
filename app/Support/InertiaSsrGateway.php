<?php

namespace App\Support;

use Exception;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\Response;

/**
 * Asks the server-rendering process (config/inertia.php, INERTIA_SSR_ENABLED)
 * to draw a page, and gives up quickly.
 *
 * The package's own gateway waits as long as any HTTP request may (30 seconds).
 * Server rendering only makes the first paint earlier, so a process that is
 * down or stuck must not hold a page back: after a second without a
 * connection, or three without an answer, the page is sent without the
 * pre-drawn HTML and the browser draws it as it always did.
 */
final class InertiaSsrGateway extends HttpGateway
{
    private const int CONNECT_SECONDS = 1;

    private const int ANSWER_SECONDS = 3;

    /**
     * @param  array<string, mixed>  $page
     */
    public function dispatch(array $page): ?Response
    {
        if (! $this->shouldDispatch()) {
            return null;
        }

        try {
            $response = Http::connectTimeout(self::CONNECT_SECONDS)
                ->timeout(self::ANSWER_SECONDS)
                ->post($this->getUrl('/render'), $page)
                ->throw()
                ->json();
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException) {
                throw $e;
            }

            return null;
        }

        if (! is_array($response) || ! isset($response['head'], $response['body'])) {
            return null;
        }

        return new Response(
            implode("\n", (array) $response['head']),
            (string) $response['body'],
        );
    }
}
