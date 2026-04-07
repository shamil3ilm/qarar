<?php

declare(strict_types=1);

namespace App\Services\TaskBoard;

use App\Models\TaskBoard\TaskBoard;
use App\Models\TaskBoard\TaskBoardColumn;
use App\Models\TaskBoard\TaskBoardMember;
use App\Models\TaskBoard\TaskBoardTemplate;
use App\Models\TaskBoard\TaskLabel;
use Illuminate\Support\Facades\DB;

class TaskBoardService
{
    /**
     * Create a new task board.
     */
    public function create(array $data, int $userId): TaskBoard
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['created_by'] = $data['created_by'] ?? $userId;
            $data['board_type'] = $data['board_type'] ?? TaskBoard::TYPE_KANBAN;
            $data['visibility'] = $data['visibility'] ?? TaskBoard::VISIBILITY_PRIVATE;

            $columns = $data['columns'] ?? [];
            unset($data['columns']);

            $board = TaskBoard::create($data);

            // Add creator as owner member
            TaskBoardMember::create([
                'board_id' => $board->id,
                'user_id' => $board->created_by,
                'role' => TaskBoardMember::ROLE_OWNER,
                'joined_at' => now(),
            ]);

            // Create default columns if none provided
            if (empty($columns)) {
                $this->createDefaultColumns($board);
            } else {
                foreach ($columns as $index => $column) {
                    TaskBoardColumn::create([
                        'board_id' => $board->id,
                        'name' => $column['name'],
                        'color' => $column['color'] ?? null,
                        'position' => $index,
                        'wip_limit' => $column['wip_limit'] ?? null,
                        'is_done_column' => $column['is_done_column'] ?? false,
                        'is_default' => $index === 0,
                    ]);
                }
            }

            return $board->load(['members.user', 'columns']);
        });
    }

    /**
     * Add a member to a board.
     */
    public function addMember(TaskBoard $board, int $userId, string $role = 'member'): TaskBoardMember
    {
        return DB::transaction(function () use ($board, $userId, $role) {
            $existing = $board->members()->where('user_id', $userId)->first();

            if ($existing) {
                throw new \InvalidArgumentException('User is already a member of this board.');
            }

            return TaskBoardMember::create([
                'board_id' => $board->id,
                'user_id' => $userId,
                'role' => $role,
                'joined_at' => now(),
            ]);
        });
    }

    /**
     * Remove a member from a board.
     */
    public function removeMember(TaskBoard $board, int $userId): bool
    {
        return DB::transaction(function () use ($board, $userId) {
            $member = $board->members()->where('user_id', $userId)->first();

            if (!$member) {
                throw new \InvalidArgumentException('User is not a member of this board.');
            }

            if ($member->isOwner()) {
                throw new \InvalidArgumentException('Cannot remove the board owner.');
            }

            return (bool) $member->delete();
        });
    }

    /**
     * Add a column to a board.
     */
    public function addColumn(TaskBoard $board, array $data): TaskBoardColumn
    {
        return DB::transaction(function () use ($board, $data) {
            $maxPosition = $board->columns()->max('position') ?? -1;

            return TaskBoardColumn::create([
                'board_id' => $board->id,
                'name' => $data['name'],
                'color' => $data['color'] ?? null,
                'position' => $data['position'] ?? ($maxPosition + 1),
                'wip_limit' => $data['wip_limit'] ?? null,
                'is_done_column' => $data['is_done_column'] ?? false,
                'is_default' => $data['is_default'] ?? false,
            ]);
        });
    }

    /**
     * Reorder columns on a board.
     */
    public function reorderColumns(TaskBoard $board, array $columnOrder): void
    {
        DB::transaction(function () use ($board, $columnOrder) {
            foreach ($columnOrder as $position => $columnId) {
                $board->columns()
                    ->where('id', $columnId)
                    ->update(['position' => $position]);
            }
        });
    }

    /**
     * Create a board from a template.
     */
    public function createFromTemplate(TaskBoardTemplate $template, array $data, int $userId): TaskBoard
    {
        return DB::transaction(function () use ($template, $data, $userId) {
            $data['board_type'] = $template->board_type;
            $data['created_by'] = $data['created_by'] ?? $userId;

            $board = TaskBoard::create($data);

            // Add creator as owner
            TaskBoardMember::create([
                'board_id' => $board->id,
                'user_id' => $board->created_by,
                'role' => TaskBoardMember::ROLE_OWNER,
                'joined_at' => now(),
            ]);

            // Create columns from template
            if (is_array($template->columns)) {
                foreach ($template->columns as $index => $column) {
                    TaskBoardColumn::create([
                        'board_id' => $board->id,
                        'name' => $column['name'],
                        'color' => $column['color'] ?? null,
                        'position' => $index,
                        'wip_limit' => $column['wip_limit'] ?? null,
                        'is_done_column' => $column['is_done_column'] ?? false,
                        'is_default' => $index === 0,
                    ]);
                }
            }

            // Create labels from template
            if (is_array($template->labels)) {
                foreach ($template->labels as $label) {
                    TaskLabel::create([
                        'board_id' => $board->id,
                        'name' => $label['name'],
                        'color' => $label['color'],
                        'description' => $label['description'] ?? null,
                    ]);
                }
            }

            return $board->load(['members.user', 'columns', 'labels']);
        });
    }

    /**
     * Create default Kanban columns for a board.
     */
    protected function createDefaultColumns(TaskBoard $board): void
    {
        $defaults = [
            ['name' => 'To Do', 'color' => '#6B7280', 'is_default' => true, 'is_done_column' => false],
            ['name' => 'In Progress', 'color' => '#3B82F6', 'is_default' => false, 'is_done_column' => false],
            ['name' => 'In Review', 'color' => '#F59E0B', 'is_default' => false, 'is_done_column' => false],
            ['name' => 'Done', 'color' => '#10B981', 'is_default' => false, 'is_done_column' => true],
        ];

        foreach ($defaults as $index => $column) {
            TaskBoardColumn::create([
                'board_id' => $board->id,
                'name' => $column['name'],
                'color' => $column['color'],
                'position' => $index,
                'is_default' => $column['is_default'],
                'is_done_column' => $column['is_done_column'],
            ]);
        }
    }
}
