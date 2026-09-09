<?php

declare(strict_types=1);

namespace App\Domain\Project\Controllers;

use App\Domain\Project\Data\TaskData;
use App\Domain\Project\Models\Task;
use App\Domain\Project\Tables\TaskTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController
{
    public function index(Request $request, TaskTable $table): Response
    {
        return Inertia::render('tasks/TaskList', [
            'items' => $table->getData($request)->through(fn (Task $task) => TaskData::fromModel($task)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('tasks/TaskForm', [
            'task' => TaskData::empty(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = TaskData::validateAndCreate($request->all());

        $task = Task::create($data->toUpdateArray());

        return redirect()->route('tasks.edit', $task)->with('toast', ['type' => 'success', 'message' => 'Task created.']);
    }

    public function edit(Task $task): Response
    {
        return Inertia::render('tasks/TaskForm', [
            'task' => TaskData::fromModel($task),
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $data = TaskData::validateAndCreate($request->all());

        $task->update($data->toUpdateArray());

        return back()->with('toast', ['type' => 'success', 'message' => 'Task saved.']);
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()->route('tasks.index')->with('toast', ['type' => 'success', 'message' => 'Task deleted.']);
    }
}
