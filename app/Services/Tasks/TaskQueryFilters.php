<?php

namespace App\Services\Tasks;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TaskQueryFilters
{
    public static function apply(Builder $query, Request $request): Builder
    {
        $search = trim((string) $request->query('search'));

        return $query
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('stakeholder', 'like', "%{$search}%")
                        ->orWhereHas('stakeholders', fn (Builder $stakeholderQuery) => $stakeholderQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('owner', fn (Builder $ownerQuery) => $ownerQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('creator', fn (Builder $creatorQuery) => $creatorQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('owner_user_id'), fn (Builder $query) => $query->where('owner_user_id', (int) $request->query('owner_user_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->when($request->filled('priority'), fn (Builder $query) => $query->where('priority', $request->query('priority')))
            ->when($request->filled('stakeholder'), function (Builder $query) use ($request) {
                $stakeholder = $request->query('stakeholder');
                $query->where(function (Builder $inner) use ($stakeholder) {
                    $inner
                        ->where('stakeholder', 'like', '%' . $stakeholder . '%')
                        ->orWhereHas('stakeholders', fn (Builder $stakeholderQuery) => $stakeholderQuery->where('name', 'like', '%' . $stakeholder . '%'));
                });
            })
            ->when($request->filled('stakeholder_user_id'), fn (Builder $query) => $query->whereHas(
                'stakeholders',
                fn (Builder $stakeholderQuery) => $stakeholderQuery->whereKey((int) $request->query('stakeholder_user_id')),
            ))
            ->when($request->query('visibility') === 'private', fn (Builder $query) => $query
                ->whereDoesntHave('stakeholders')
                ->where(fn (Builder $legacyQuery) => $legacyQuery->whereNull('stakeholder')->orWhere('stakeholder', '')))
            ->when($request->query('visibility') === 'shared', fn (Builder $query) => $query
                ->where(fn (Builder $sharedQuery) => $sharedQuery
                    ->whereHas('stakeholders')
                    ->orWhere(fn (Builder $legacyQuery) => $legacyQuery->whereNotNull('stakeholder')->where('stakeholder', '<>', ''))))
            ->when($request->filled('created_by_user_id'), fn (Builder $query) => $query->where('created_by_user_id', (int) $request->query('created_by_user_id')))
            ->when($request->filled('due_date_from'), fn (Builder $query) => $query->whereDate('due_date', '>=', $request->query('due_date_from')))
            ->when($request->filled('due_date_to'), fn (Builder $query) => $query->whereDate('due_date', '<=', $request->query('due_date_to')))
            ->when($request->boolean('overdue'), fn (Builder $query) => $query
                ->whereDate('due_date', '<', today())
                ->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED]))
            ->when($request->boolean('has_subtasks'), fn (Builder $query) => $query->whereHas('subtasks'))
            ->when($request->query('created_scope') === 'mine', fn (Builder $query) => $query->whereColumn('created_by_user_id', 'owner_user_id'))
            ->when($request->query('created_scope') === 'third_party', fn (Builder $query) => $query->whereColumn('created_by_user_id', '<>', 'owner_user_id'));
    }
}
