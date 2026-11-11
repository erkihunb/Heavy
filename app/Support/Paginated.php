<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A page of rows in the shape the frontend's Paginated<T> type and the
 * Pagination component expect (the same as a paginated JsonResource).
 */
final class Paginated
{
    /**
     * @template T
     *
     * @param  LengthAwarePaginator<int, T>  $paginator  already mapped to the rows to send
     * @return array{data: list<T>, links: array<string, string|null>, meta: array<string, int|null>}
     */
    public static function present(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => array_values($paginator->items()),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
