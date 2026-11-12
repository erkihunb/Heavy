<?php

namespace App\Support\Rbac;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Where a member of staff may see applications: everywhere (a global role),
 * or the head office and/or a list of branches (their scope rows). Built by
 * User::branchScope(); applied to queries with apply() and to one record with
 * allows().
 *
 * Fail closed (EUISIS ADR 0003): no global role and no scope row means the
 * scope is empty and nothing matches.
 */
final readonly class BranchScope
{
    public const string HEAD_OFFICE_KEY = 'head-office';

    /**
     * @param  list<int>  $branchIds
     */
    private function __construct(
        public bool $everywhere,
        public bool $headOffice,
        public array $branchIds,
    ) {}

    public static function everywhere(): self
    {
        return new self(true, false, []);
    }

    /**
     * @param  list<int>  $branchIds
     */
    public static function of(bool $headOffice, array $branchIds): self
    {
        return new self(false, $headOffice, array_values(array_unique($branchIds)));
    }

    public static function nothing(): self
    {
        return new self(false, false, []);
    }

    /**
     * The scope as stored with a queued export: null for everywhere.
     *
     * @return array{head_office: bool, branch_ids: list<int>}|null
     */
    public function toArray(): ?array
    {
        return $this->everywhere ? null : ['head_office' => $this->headOffice, 'branch_ids' => $this->branchIds];
    }

    /**
     * The scope stored by toArray(); an export filed before scope rows
     * existed carries a single `branch_id` instead.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function fromFilters(array $filters): self
    {
        if (is_array($filters['scope'] ?? null)) {
            $ids = is_array($filters['scope']['branch_ids'] ?? null) ? $filters['scope']['branch_ids'] : [];

            return self::of((bool) ($filters['scope']['head_office'] ?? false), array_map(intval(...), array_values($ids)));
        }

        return isset($filters['branch_id']) ? self::of(false, [(int) $filters['branch_id']]) : self::everywhere();
    }

    public function isEmpty(): bool
    {
        return ! $this->everywhere && ! $this->headOffice && $this->branchIds === [];
    }

    /**
     * Whether a record filed at this branch (null: the head office) is inside the scope.
     */
    public function allows(?int $branchId): bool
    {
        if ($this->everywhere) {
            return true;
        }

        return $branchId === null ? $this->headOffice : in_array($branchId, $this->branchIds, true);
    }

    /**
     * Limits a query to records inside the scope, by the given branch column.
     * An empty scope matches nothing.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function apply(Builder $query, string $column = 'branch_id'): Builder
    {
        if ($this->everywhere) {
            return $query;
        }

        if ($this->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($column): void {
            if ($this->branchIds !== []) {
                $inner->whereIn($column, $this->branchIds);
            }

            if ($this->headOffice) {
                $inner->orWhereNull($column);
            }
        });
    }
}
