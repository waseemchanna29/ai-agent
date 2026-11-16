<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFor;
use App\Models\User;
use App\Models\Worklog;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $stats = $user->isAdmin() ? [
            'Users'        => User::count(),
            'Project For'  => ProjectFor::count(),
            'Projects'     => Project::count(),
            'Hours (this month)' => (float) Worklog::whereBetween('work_date', [
                now()->startOfMonth(), now()->endOfMonth(),
            ])->sum('hours_spent'),
        ] : [];

        $recent = Worklog::with(['user:id,name', 'projectFor:id,name', 'project:id,name', 'task:id,title'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->latest('work_date')->latest('id')
            ->limit(10)->get();

        return view('dashboard.index', compact('stats', 'recent'));
    }
}