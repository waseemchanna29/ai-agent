<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorklogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'        => ['required', 'exists:users,id'],
            'work_date'      => ['required', 'date', 'before_or_equal:today'],
            'project_for_id' => ['required', 'exists:project_fors,id'],

            // project must belong to the selected "project for"
            'project_id'     => ['required', Rule::exists('projects', 'id')
                                    ->where('project_for_id', $this->input('project_for_id'))],

            'worklog_entries'               => ['required', 'array', 'min:1'],
            'worklog_entries.*.task_id'     => ['required', Rule::exists('tasks', 'id')
                                                    ->where('project_id', $this->input('project_id'))],
            'worklog_entries.*.hours_spent' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'worklog_entries.*.details'     => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'worklog_entries.*.task_id'     => 'task',
            'worklog_entries.*.hours_spent' => 'hours spent',
            'worklog_entries.*.details'     => 'task details',
        ];
    }
}