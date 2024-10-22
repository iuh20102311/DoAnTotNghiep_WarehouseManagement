<?php

namespace App\Utils;

trait PaginationTrait
{
    private function paginateResults($query, $perPage = 10, $page = 1)
    {
        $total = $query->count();
        $results = $query->forPage($page, $perPage)->get();
        return new CustomPaginator($results, $total, $perPage, $page, [
            'path' => $_SERVER['REQUEST_URI'],
        ]);
    }
}