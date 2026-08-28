<native:top-bar title="Note" background-color="#0a0a0a" text-color="#EDEDEC" />
<native:scroll-view class="bg-theme-bg">
    <native:column class="p-4 gap-3">
        @if ($screen->offline)
            <native:row class="p-2 gap-2 items-center rounded-sm border-theme-warning border-2">
                <native:icon name="cloud_off" color="#B7730F" size="18" />
                <native:text class="text-theme-warning">Offline — links wait until reachable</native:text>
            </native:row>
        @endif

        @if ($screen->state === 'unauthorized')
            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-theme-text">Sign in on the Today tab first.</native:text>
            </native:column>
        @elseif ($screen->state === 'loading')
            <native:text class="text-theme-muted">Loading note…</native:text>
        @elseif ($screen->state === 'conflict')
            <native:column class="p-4 gap-2 rounded-md border-theme-warning border-2 bg-theme-surface">
                <native:row class="gap-2 items-center">
                    <native:icon name="sync_problem" color="#B7730F" size="20" />
                    <native:text class="text-theme-warning font-bold">Changed elsewhere</native:text>
                </native:row>
                <native:text class="text-theme-text">{{ $screen->error }}</native:text>
                <native:pressable ref="nd-reload-conflict" a11y-label="Reload note" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Reload</native:text>
                </native:pressable>
            </native:column>
        @elseif ($screen->state === 'offline' || $screen->state === 'error')
            <native:column class="p-4 gap-2 rounded-md border-theme-danger border-2 bg-theme-surface">
                <native:row class="gap-2 items-center">
                    <native:icon name="error" color="#D20812" size="20" />
                    <native:text class="text-theme-text">{{ $screen->error }}</native:text>
                </native:row>
                <native:pressable ref="nd-retry" a11y-label="Retry loading note" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @else
            @if ($screen->notice !== '')
                <native:text class="text-sm text-theme-success">{{ $screen->notice }}</native:text>
            @endif

            <native:column class="p-4 gap-2 rounded-md border-theme-border border-4 bg-theme-surface">
                <native:text class="text-lg font-bold text-theme-text">{{ $screen->note['title'] ?? 'Untitled note' }}</native:text>
                <native:text class="text-sm text-theme-muted">v{{ $screen->note['version'] ?? 0 }} · Editing stays on the web app.</native:text>
            </native:column>

            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-sm font-bold text-theme-muted">CONTENT</native:text>
                <native:text class="text-theme-text">{{ ($screen->note['plain_text_cache'] ?? $screen->note['markdown_cache'] ?? '') !== '' ? ($screen->note['plain_text_cache'] ?? $screen->note['markdown_cache']) : 'No content.' }}</native:text>
            </native:column>

            <native:text class="text-sm font-bold text-theme-muted">LINKS ({{ count($screen->links) }})</native:text>
            @forelse ($screen->links as $link)
                <native:row class="p-4 gap-2 items-center justify-between rounded-md border-theme-border border-2 bg-theme-surface" :key="$link['id'] ?? $loop->index">
                    <native:text class="text-theme-text">{{ $link['target_type'] ?? '' }} #{{ $link['target_id'] ?? '' }}</native:text>
                    <native:pressable ref="nd-unlink-{{ $link['id'] ?? $loop->index }}" a11y-label="Remove link" class="p-2 rounded-sm bg-theme-surface border-theme-border border-2" @tap="removeLink({{ $link['id'] ?? 0 }})">
                        <native:text class="text-theme-danger font-bold">Remove</native:text>
                    </native:pressable>
                </native:row>
            @empty
                <native:column class="p-3 rounded-md border-theme-border border-2 bg-theme-surface">
                    <native:text class="text-theme-muted">No links yet — link a task or goal below.</native:text>
                </native:column>
            @endforelse

            <native:text class="text-sm font-bold text-theme-muted">LINK A TASK</native:text>
            <native:column class="p-2 gap-1 rounded-md border-theme-border border-2 bg-theme-surface">
                @forelse ($screen->tasks as $task)
                    <native:pressable ref="nd-link-task-{{ $task['id'] ?? $loop->index }}" a11y-label="Link to task {{ $task['title'] ?? 'Untitled' }}" class="p-4 rounded-sm bg-transparent" @tap="addTaskLink({{ $task['id'] ?? 0 }})">
                        <native:text class="text-theme-text">{{ $task['title'] ?? 'Untitled' }}</native:text>
                    </native:pressable>
                @empty
                    <native:text class="text-theme-muted px-4">No tasks to link.</native:text>
                @endforelse
            </native:column>

            <native:text class="text-sm font-bold text-theme-muted">LINK A GOAL</native:text>
            <native:column class="p-2 gap-1 rounded-md border-theme-border border-2 bg-theme-surface">
                @forelse ($screen->goals as $goal)
                    <native:pressable ref="nd-link-goal-{{ $goal['id'] ?? $loop->index }}" a11y-label="Link to goal {{ $goal['title'] ?? 'Untitled' }}" class="p-4 rounded-sm bg-transparent" @tap="addGoalLink({{ $goal['id'] ?? 0 }})">
                        <native:text class="text-theme-text">{{ $goal['title'] ?? 'Untitled' }}</native:text>
                    </native:pressable>
                @empty
                    <native:text class="text-theme-muted px-4">No goals to link.</native:text>
                @endforelse
            </native:column>

            <native:pressable ref="nd-back" a11y-label="Back to notes" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="backToNotes">
                <native:text class="text-theme-on-primary font-bold">Back to Notes</native:text>
            </native:pressable>
        @endif
    </native:column>
</native:scroll-view>
<native:bottom-nav background-color="#0a0a0a" active-color="#DE3005" text-color="#EDEDEC">
    <native:bottom-nav-item id="today" label="Today" url="/" icon="today" />
    <native:bottom-nav-item id="tasks" label="Tasks" url="/tasks" icon="list_alt" />
    <native:bottom-nav-item id="capture" label="Capture" url="/capture" icon="add" />
    <native:bottom-nav-item id="workspaces" label="Workspace" url="/workspaces" icon="folder" />
    <native:bottom-nav-item id="more" label="More" url="/more" icon="more_vert" />
</native:bottom-nav>