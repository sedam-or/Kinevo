<native:top-bar title="Task" background-color="#0a0a0a" text-color="#EDEDEC" />
<native:scroll-view class="bg-theme-bg">
    <native:column class="p-4 gap-3">
        @if ($screen->state === 'unauthorized')
            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-theme-text">Sign in on the Today tab first.</native:text>
                <native:pressable ref="td-back-auth" class="p-3 rounded-sm bg-theme-primary" @tap="backToTasks">
                    <native:text class="text-theme-on-primary font-bold">Back to Tasks</native:text>
                </native:pressable>
            </native:column>
        @elseif ($screen->state === 'loading')
            <native:text class="text-theme-muted">Loading task…</native:text>
        @elseif ($screen->state === 'offline')
            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:row class="gap-2 items-center">
                    <native:icon name="cloud_off" color="#B7730F" size="18" />
                    <native:text class="text-theme-text">Backend unreachable.</native:text>
                </native:row>
                <native:pressable ref="td-retry-offline" class="p-3 rounded-sm bg-theme-primary" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @elseif ($screen->state === 'error')
            <native:column class="p-4 gap-2 rounded-md border-theme-danger border-2 bg-theme-surface">
                <native:text class="text-theme-text">{{ $screen->error }}</native:text>
                <native:pressable ref="td-retry-error" class="p-3 rounded-sm bg-theme-primary" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @elseif ($screen->state === 'entitlement')
            <native:column class="p-4 gap-2 rounded-md border-theme-warning border-2 bg-theme-surface">
                <native:text class="text-theme-text font-bold">Plan limit reached</native:text>
                <native:text class="text-theme-muted">This action needs an upgrade. Open billing on the web to continue.</native:text>
            </native:column>
        @elseif ($screen->state === 'conflict')
            <native:column class="p-4 gap-2 rounded-md border-theme-warning border-2 bg-theme-surface">
                <native:text class="text-theme-text">{{ $screen->error }}</native:text>
                <native:pressable ref="td-reload-conflict" class="p-3 rounded-sm bg-theme-primary" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Reload</native:text>
                </native:pressable>
            </native:column>
        @else
            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-lg font-bold text-theme-text">{{ $screen->task['title'] ?? 'Untitled' }}</native:text>
                <native:text class="text-sm text-theme-muted">Status: {{ $screen->task['status'] ?? 'unknown' }} · Progress: {{ $screen->task['progress'] ?? 0 }}%</native:text>
                @if (($screen->task['description'] ?? '') !== '')
                    <native:text class="text-theme-text">{{ $screen->task['description'] }}</native:text>
                @endif
                @if ($screen->notice !== '')
                    <native:text class="text-sm text-theme-success">{{ $screen->notice }}</native:text>
                @endif
                @if ($screen->error !== '')
                    <native:text class="text-sm text-theme-danger">{{ $screen->error }}</native:text>
                @endif
            </native:column>

            <native:row class="gap-2">
                @if (($screen->task['status'] ?? '') === 'in_progress')
                    <native:pressable ref="td-complete" a11y-label="Complete task" class="p-3 rounded-md bg-theme-primary border-theme-border border-2" @tap="complete">
                        <native:text class="text-theme-on-primary font-bold">Complete</native:text>
                    </native:pressable>
                @else
                    <native:pressable ref="td-start" a11y-label="Start task" class="p-3 rounded-md bg-theme-primary border-theme-border border-2" @tap="start">
                        <native:text class="text-theme-on-primary font-bold">Start</native:text>
                    </native:pressable>
                @endif
                <native:pressable ref="td-partial" a11y-label="Log partial completion" class="p-3 rounded-md bg-theme-surface border-theme-border border-2" @tap="partialComplete">
                    <native:text class="text-theme-text font-bold">Partial</native:text>
                </native:pressable>
            </native:column>

            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-sm font-bold text-theme-muted">TIMER</native:text>
                @php
                    $execStatus = $screen->execution['status'] ?? '';
                    $execActive = ($screen->execution['id'] ?? 0) > 0 && in_array($execStatus, ['running', 'paused'], true);
                @endphp
                @if ($execActive)
                    <native:text class="text-theme-text">Session #{{ $screen->execution['id'] }} — {{ $screen->execution['status'] ?? 'active' }}</native:text>
                    @if (($screen->execution['status'] ?? '') === 'paused')
                        <native:row class="gap-2">
                            <native:pressable ref="td-timer-resume" a11y-label="Resume timer" class="p-3 rounded-sm bg-theme-primary" @tap="timerResume">
                                <native:text class="text-theme-on-primary font-bold">Resume</native:text>
                            </native:pressable>
                            <native:pressable ref="td-timer-complete" a11y-label="Complete timer session" class="p-3 rounded-sm bg-theme-surface border-theme-border border-2" @tap="timerComplete">
                                <native:text class="text-theme-text font-bold">Finish</native:text>
                            </native:pressable>
                        </native:row>
                    @else
                        <native:row class="gap-2">
                            <native:pressable ref="td-timer-pause" a11y-label="Pause timer" class="p-3 rounded-sm bg-theme-surface border-theme-border border-2" @tap="timerPause">
                                <native:text class="text-theme-text font-bold">Pause</native:text>
                            </native:pressable>
                            <native:pressable ref="td-timer-complete" a11y-label="Complete timer session" class="p-3 rounded-sm bg-theme-primary" @tap="timerComplete">
                                <native:text class="text-theme-on-primary font-bold">Finish</native:text>
                            </native:pressable>
                        </native:row>
                    @endif
                @else
                    <native:pressable ref="td-timer-start" a11y-label="Start focus timer" class="p-3 rounded-sm bg-theme-primary" @tap="timerStart">
                        <native:text class="text-theme-on-primary font-bold">Start timer</native:text>
                    </native:pressable>
                @endif
            </native:column>

            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-sm font-bold text-theme-muted">SUBTASKS</native:text>
                @forelse ($screen->subtasks as $sub)
                    <native:row class="gap-2 items-center justify-between" :key="$sub['id'] ?? $loop->index">
                        <native:text class="text-theme-text">{{ ($sub['done'] ?? $sub['completed'] ?? false) ? '☑' : '☐' }} {{ $sub['title'] ?? 'Subtask' }}</native:text>
                        <native:pressable ref="td-sub-{{ $sub['id'] ?? $loop->index }}" a11y-label="Toggle subtask {{ $sub['title'] ?? '' }}" class="p-2 rounded-sm bg-theme-surface border-theme-border border-2" @tap="toggleSubtask({{ $sub['id'] ?? 0 }})">
                            <native:text class="text-theme-text font-bold">Toggle</native:text>
                        </native:pressable>
                    </native:row>
                @empty
                    <native:text class="text-theme-muted">No subtasks.</native:text>
                @endforelse
            </native:column>

            <native:pressable ref="td-back" a11y-label="Back to tasks" class="p-3 rounded-md bg-theme-surface border-theme-border border-2" @tap="backToTasks">
                <native:text class="text-theme-text font-bold">Back to Tasks</native:text>
            </native:pressable>
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
