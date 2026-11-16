<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Worklog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>

<body class="bg-gray-100 py-8 min-h-screen">

    <div class="mx-auto px-4 max-w-4xl" x-data="worklogForm(@js($tree), {{ $user->id }})">

        {{-- User info --}}

        <div class="bg-white shadow mb-6 p-5 rounded-xl">
            <div class="flex justify-between items-center mb-3">
                <h1 class="font-bold text-xl">Worklog Entry</h1>
                
                @if (auth()->check() && auth()->user()->isAdmin())
                    <a href="{{ route('dashboard') }}"
                        class="bg-indigo-600 px-4 py-1.5 rounded-lg text-white text-sm">Dashboard</a>
                @elseif ($user->isAdmin())
                    <a href="{{ route('login') }}"
                        class="bg-gray-800 px-4 py-1.5 rounded-lg text-white text-sm">Login</a>
                @endif
            </div>
            <div class="gap-3 grid grid-cols-1 sm:grid-cols-3 text-sm">
                <div><span class="text-gray-500">Name</span>
                    <div class="font-medium">{{ $user->name }}</div>
                </div>
                <div><span class="text-gray-500">Email</span>
                    <div class="font-medium">{{ $user->email }}</div>
                </div>
                <div><span class="text-gray-500">Designation</span>
                    <div class="font-medium">{{ $user->designation }}</div>
                </div>
            </div>
        </div>

        {{-- Messages --}}
        <template x-if="success">
            <div class="bg-green-100 mb-4 p-3 rounded-lg text-green-800" x-text="success"></div>
        </template>
        <template x-if="Object.keys(errors).length">
            <div class="bg-red-100 mb-4 p-3 rounded-lg text-red-800 text-sm">
                <ul class="pl-5 list-disc">
                    <template x-for="(msgs, key) in errors" :key="key">
                        <li x-text="msgs[0]"></li>
                    </template>
                </ul>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-5 bg-white shadow p-5 rounded-xl">

            {{-- Header fields --}}
            <div class="gap-4 grid grid-cols-1 sm:grid-cols-3">
                <div>
                    <label class="block mb-1 font-medium text-sm">Date</label>
                    <input type="date" x-model="workDate" :max="today" class="p-2 border rounded-lg w-full"
                        required>
                </div>
                <div>
                    <label class="block mb-1 font-medium text-sm">Project For</label>
                    <select x-model="projectForId" @change="onProjectForChange" class="p-2 border rounded-lg w-full"
                        required>
                        <option value="">Select...</option>
                        <template x-for="pf in tree" :key="pf.id">
                            <option :value="pf.id" x-text="pf.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-medium text-sm">Project</label>
                    <select x-model="projectId" @change="onProjectChange" :disabled="!projectForId"
                        class="disabled:bg-gray-100 p-2 border rounded-lg w-full" required>
                        <option value="">Select...</option>
                        <template x-for="p in projects" :key="p.id">
                            <option :value="p.id" x-text="p.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            {{-- Repeating entries --}}
            <div class="space-y-4">
                <template x-for="(entry, index) in entries" :key="entry.key">
                    <div class="space-y-3 bg-gray-50 p-4 border rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold text-sm" x-text="'Entry #' + (index + 1)"></span>
                            <button type="button" @click="removeEntry(index)" x-show="entries.length > 1"
                                class="text-red-600 text-sm hover:underline">Remove</button>
                        </div>

                        <div class="gap-4 grid grid-cols-1 sm:grid-cols-3">
                            {{-- Task + add --}}
                            <div class="sm:col-span-2">
                                <label class="block mb-1 font-medium text-sm">Task Title</label>
                                <div class="flex gap-2">
                                    <select x-model="entry.task_id" :disabled="!projectId"
                                        class="flex-1 disabled:bg-gray-100 p-2 border rounded-lg" required>
                                        <option value="">Select task...</option>
                                        <template x-for="t in tasks" :key="t.id">
                                            <option :value="t.id" x-text="t.title"></option>
                                        </template>
                                    </select>
                                    <button type="button" @click="entry.adding = !entry.adding" :disabled="!projectId"
                                        class="bg-blue-600 disabled:opacity-40 px-3 rounded-lg text-white text-sm">
                                        + Add task
                                    </button>
                                </div>

                                <div x-show="entry.adding" x-cloak class="flex gap-2 mt-2">
                                    <input type="text" x-model="entry.newTitle" placeholder="New task title"
                                        @keydown.enter.prevent="addTask(entry)" class="flex-1 p-2 border rounded-lg">
                                    <button type="button" @click="addTask(entry)"
                                        class="bg-green-600 px-3 rounded-lg text-white text-sm">Save</button>
                                </div>
                            </div>

                            {{-- Hours --}}
                            <div>
                                <label class="block mb-1 font-medium text-sm">Hours Spent</label>
                                <input type="number" step="0.25" min="0.25" max="24"
                                    x-model="entry.hours_spent" class="p-2 border rounded-lg w-full" required>
                            </div>
                        </div>

                        <div>
                            <label class="block mb-1 font-medium text-sm">Task Details</label>
                            <textarea rows="3" x-model="entry.details" class="p-2 border rounded-lg w-full"></textarea>
                        </div>
                    </div>
                </template>

                <button type="button" @click="addEntry" class="font-medium text-blue-600 text-sm hover:underline">+ Add
                    another entry</button>
            </div>

            <div class="flex justify-between items-center pt-4 border-t">
                <div class="text-sm">Total hours: <strong x-text="totalHours"></strong></div>
                <button type="submit" :disabled="loading"
                    class="bg-indigo-600 disabled:opacity-50 px-6 py-2 rounded-lg text-white">
                    <span x-text="loading ? 'Saving...' : 'Submit Worklog'"></span>
                </button>
            </div>
        </form>
    </div>

    <style>
        [x-cloak] {
            display: none !important
        }
    </style>

    <script>
        function worklogForm(tree, userId) {
            let keySeq = 0;
            const newEntry = () => ({
                key: ++keySeq,
                task_id: '',
                hours_spent: '',
                details: '',
                adding: false,
                newTitle: ''
            });
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            };

            return {
                tree,
                userId,
                today: new Date().toISOString().slice(0, 10),
                workDate: new Date().toISOString().slice(0, 10),
                projectForId: '',
                projectId: '',
                entries: [newEntry()],
                errors: {},
                success: '',
                loading: false,

                get projects() {
                    const pf = this.tree.find(p => p.id == this.projectForId);
                    return pf ? pf.projects : [];
                },
                get currentProject() {
                    return this.projects.find(p => p.id == this.projectId);
                },
                get tasks() {
                    return this.currentProject ? this.currentProject.tasks : [];
                },
                get totalHours() {
                    return this.entries.reduce((s, e) => s + (parseFloat(e.hours_spent) || 0), 0);
                },

                onProjectForChange() {
                    this.projectId = '';
                    this.entries.forEach(e => e.task_id = '');
                },
                onProjectChange() {
                    this.entries.forEach(e => e.task_id = '');
                },
                addEntry() {
                    this.entries.push(newEntry());
                },
                removeEntry(i) {
                    this.entries.splice(i, 1);
                },
                resetForm() {
                    this.workDate = this.today;
                    this.projectForId = '';
                    this.projectId = '';
                    this.entries = [newEntry()];
                },
                async addTask(entry) {
                    const title = entry.newTitle.trim();
                    if (!title || !this.projectId) return;

                    const res = await fetch('{{ route('task.store') }}', {
                        method: 'POST',
                        headers,
                       body: JSON.stringify({ project_id: this.projectId, title, user_id: this.userId }),
                    });
                    const data = await res.json();

                    if (!res.ok) {
                        this.errors = data.errors || {
                            error: [data.message || 'Could not add task']
                        };
                        return;
                    }
                    this.errors = {};

                    // add to the dropdown list (avoid duplicates) and auto-select it
                    if (!this.currentProject.tasks.some(t => t.id === data.id)) {
                        this.currentProject.tasks.push({
                            id: data.id,
                            project_id: data.project_id,
                            title: data.title
                        });
                    }
                    entry.task_id = data.id;
                    entry.newTitle = '';
                    entry.adding = false;
                },

                async submit() {
                    this.loading = true;
                    this.errors = {};
                    this.success = '';

                    const payload = {
                        user_id: this.userId,
                        work_date: this.workDate,
                        project_for_id: this.projectForId,
                        project_id: this.projectId,
                        worklog_entries: this.entries.map(e => ({
                            task_id: e.task_id,
                            hours_spent: e.hours_spent,
                            details: e.details,
                        })),
                    };

                    try {
                        const res = await fetch('{{ route('worklog.store') }}', {
                            method: 'POST',
                            headers,
                            body: JSON.stringify(payload),
                        });
                        const data = await res.json();

                        if (res.status === 422) {
                            this.errors = data.errors;
                        } else if (!res.ok) {
                            this.errors = {
                                error: [data.message || 'Something went wrong']
                            };
                        } else {
                            this.success = data.message;
                            this.resetForm();
                            window.scrollTo({
                                top: 0,
                                behavior: 'smooth'
                            });
                        }
                    } catch (e) {
                        this.errors = {
                            error: ['Network error. Please try again.']
                        };
                    } finally {
                        this.loading = false;
                    }
                },
            };
        }
    </script>
</body>

</html>
