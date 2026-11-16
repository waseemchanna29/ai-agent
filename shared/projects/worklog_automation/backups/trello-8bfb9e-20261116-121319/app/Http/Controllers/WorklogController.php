<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorklogRequest;
use App\Jobs\SendWorklogToN8n;
use App\Models\ProjectFor;
use App\Models\User;
use App\Models\Worklog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorklogController extends Controller
{
    public function create(string $uuid, Request $request)
    {

        // $info = $request->validate([
        //     'uuid'        => ['required', 'string', 'max:255'],
        // ]);
        $user = User::where('uuid', $uuid)->first();
        if (!$user) {
            abort(404);
        }

        // Full tree: project_for -> projects -> tasks (one query set, no extra AJAX for dropdowns)
        $tree = ProjectFor::with([
            'projects:id,project_for_id,name',
            'projects.tasks:id,project_id,title',
        ])->orderBy('name')->get(['id', 'name']);

        return view('worklog.create', compact('user', 'tree'));
    }

    public function store(StoreWorklogRequest $request): JsonResponse
    {
        $data = $request->validated();

        $ids = DB::transaction(function () use ($data) {
            $ids = [];

            foreach ($data['worklog_entries'] as $entry) {
                $ids[] = Worklog::create([
                    'user_id'        => $data['user_id'],
                    'project_for_id' => $data['project_for_id'],
                    'project_id'     => $data['project_id'],
                    'work_date'      => $data['work_date'],
                    'task_id'        => $entry['task_id'],
                    'hours_spent'    => $entry['hours_spent'],
                    'details'        => $entry['details'] ?? null,
                ])->id;
            }

            return $ids;
        });

       // Webhook runs in the background; retries if n8n is down
        SendWorklogToN8n::dispatch($ids)->afterCommit();
        
        return response()->json([
            'message' => 'Worklog saved successfully.',
            'count'   => count($ids),
        ], 201);
    }
}
