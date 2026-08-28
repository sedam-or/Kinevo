<native:top-bar title="Tasks" background-color="#0a0a0a" text-color="#EDEDEC" />
<native:scroll-view class="bg-theme-bg">
    <native:column class="p-4 gap-3">
        @if ($screen->offline)
            <native:row class="p-2 gap-2 items-center rounded-sm border-theme-warning border-2">
                <native:icon name="cloud_off" color="#B7730F" size="18" />
                <native:text class="text-theme-warning">Offline</native:text>
            </native:row>
        @endif

        @if ($screen->state === 'unauthorized')
            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-theme-text">Sign in on the Today tab first.</native:text>
            </native:column>
        @elseif ($screen->state === 'loading')
            <native:text class="text-theme-muted">Loading tasks…</native:text>
        @elseif ($screen->state === 'offline')
            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-theme-text">Backend unreachable.</native:text>
                <native:pressable ref="retry-tasks" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @elseif ($screen->state === 'error')
            <native:column class="p-4 gap-2 rounded-md border-theme-danger border-2 bg-theme-surface">
                <native:row class="gap-2 items-center">
                    <native:icon name="error" color="#D20812" size="20" />
                    <native:text class="text-theme-text">{{ $screen->error }}</native:text>
                </native:row>
                <native:pressable ref="retry-tasks-error" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @else
            @forelse ($screen->tasks as $task)
                <native:row class="p-4 gap-2 items-center justify-between rounded-md border-theme-border border-2 bg-theme-surface" :key="$task['id'] ?? $loop->index">
                    <native:column class="gap-1">
                        <native:pressable ref="task-open-{{ $task['id'] ?? $loop->index }}" a11y-label="Open task {{ $task['title'] ?? 'Untitled' }}" @tap="openTask({{ $task['id'] ?? 0 }})">
                            <native:text class="text-theme-text font-bold">{{ $task['title'] ?? 'Untitled' }}</native:text>
                            <native:text class="text-sm text-theme-success">{{ $task['status'] ?? (($task['state'] ?? '') ?: '') }}</native:text>
                        </native:pressable>
                    </native:column>
                    <native:row class="gap-2">
                        @if (($task['status'] ?? '') === 'in_progress')
                            <native:pressable ref="task-start-{{ $task['id'] ?? $loop->index }}" a11y-label="Pause task" class="p-2 rounded-sm bg-theme-surface border-theme-border border-2" @tap="execute({{ $task['id'] ?? 0 }}, 'scheduled')">
                                <native:text class="text-theme-text font-bold">Pause</native:text>
                            </native:pressable>
                        @else
                            <native:pressable ref="task-start-{{ $task['id'] ?? $loop->index }}" a11y-label="Start task" class="p-2 rounded-sm bg-theme-surface border-theme-border border-2" @tap="execute({{ $task['id'] ?? 0 }}, 'in_progress')">
                                <native:text class="text-theme-text font-bold">Start</native:text>
                            </native:pressable>
                        @endif
                        <native:pressable ref="task-done-{{ $task['id'] ?? $loop->index }}" a11y-label="Mark task done" class="p-2 rounded-sm bg-theme-primary border-theme-border border-2" @tap="execute({{ $task['id'] ?? 0 }}, 'completed')">
                            <native:text class="text-theme-on-primary font-bold">Mark done</native:text>
                        </native:pressable>
                    </native:row>
                </native:row>
            @empty
                <native:column class="p-3 rounded-md border-theme-border border-2 bg-theme-surface">
                    <native:text class="text-theme-muted">No tasks yet.</native:text>
                </native:column>
            @endforelse
        @endif
    </native:column>
</native:scroll-view>
<native:bottom-nav background-color="#0a0a0a" active-color="#DE3005" text-color="#EDEDEC">

    <native:bottom-nav-item id="today" label="Today" url="/" icon="today" />

    <native:bottom-nav-item id="tasks" label="Tasks" url="/tasks" icon="list_alt" active />

    <native:bottom-nav-item id="capture" label="Capture" url="/capture" icon="add" />

    <native:bottom-nav-item id="workspaces" label="Workspace" url="/workspaces" icon="folder" />

    <native:bottom-nav-item id="more" label="More" url="/more" icon="more_vert" />
</native:bottom-nav>