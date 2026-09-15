<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : (($this->page - 1) * $this->perPage) + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** Bootstrap 5 pagination markup, preserving the current query string. */
    public function links(string $path = ''): string
    {
        if (!$this->hasPages()) {
            return '';
        }
        $path = $path !== '' ? $path : (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $query = $_GET ?? [];
        $last = $this->lastPage();
        $current = $this->page;

        $link = static function (int $page) use ($path, $query): string {
            $query['page'] = $page;
            return e($path . '?' . http_build_query($query));
        };

        $html = '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0 flex-wrap">';
        $html .= '<li class="page-item' . ($current <= 1 ? ' disabled' : '') . '">'
            . '<a class="page-link" href="' . $link(max(1, $current - 1)) . '">&laquo;</a></li>';

        $start = max(1, $current - 2);
        $end = min($last, $current + 2);
        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $link(1) . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $html .= '<li class="page-item' . ($i === $current ? ' active' : '') . '">'
                . '<a class="page-link" href="' . $link($i) . '">' . $i . '</a></li>';
        }
        if ($end < $last) {
            if ($end < $last - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="' . $link($last) . '">' . $last . '</a></li>';
        }

        $html .= '<li class="page-item' . ($current >= $last ? ' disabled' : '') . '">'
            . '<a class="page-link" href="' . $link(min($last, $current + 1)) . '">&raquo;</a></li>';
        return $html . '</ul></nav>';
    }

    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'meta' => [
                'total' => $this->total,
                'page' => $this->page,
                'per_page' => $this->perPage,
                'last_page' => $this->lastPage(),
            ],
        ];
    }
}
