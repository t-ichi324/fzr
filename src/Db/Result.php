<?php
namespace Fzr\Db;

use Fzr\Collection;

/**
 * ページネーション付き結果セット
 */
class Result {
    protected array $rows;
    protected int $total;
    protected int $page;
    protected int $perPage;
    protected int $lastPage;

    public function __construct(array $rows, int $total, int $page, int $perPage) {
        $this->rows = $rows;
        $this->total = $total;
        $this->page = $page;
        $this->perPage = $perPage;
        $this->lastPage = max(1, (int)ceil($total / $perPage));
    }

    public function rows(): array { return $this->rows; }
    public function total(): int { return $this->total; }
    public function page(): int { return $this->page; }
    public function perPage(): int { return $this->perPage; }
    public function lastPage(): int { return $this->lastPage; }
    public function hasNext(): bool { return $this->page < $this->lastPage; }
    public function hasPrev(): bool { return $this->page > 1; }
    public function isEmpty(): bool { return empty($this->rows); }
    public function count(): int { return count($this->rows); }

    /** Collection化 */
    public function toCollection(): Collection {
        return new Collection($this->rows);
    }

    /** ページネーションリンク生成 */
    public function links(string $baseUrl = '?', string $pageParam = 'page', int $range = 5): string {
        if ($this->lastPage <= 1) return '';
        $html = '<nav class="pagination">';
        if ($this->hasPrev()) $html .= '<a href="' . $baseUrl . $pageParam . '=' . ($this->page - 1) . '">&laquo;</a> ';
        $start = max(1, $this->page - $range);
        $end = min($this->lastPage, $this->page + $range);
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i === $this->page) ? ' class="active"' : '';
            $html .= '<a href="' . $baseUrl . $pageParam . '=' . $i . '"' . $active . '>' . $i . '</a> ';
        }
        if ($this->hasNext()) $html .= '<a href="' . $baseUrl . $pageParam . '=' . ($this->page + 1) . '">&raquo;</a>';
        $html .= '</nav>';
        return $html;
    }
}
