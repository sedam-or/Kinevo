<native:top-bar title="Goal" background-color="#0a0a0a" text-color="#EDEDEC" />
<native:scroll-view class="bg-theme-bg">
    <native:column class="p-4 gap-3">
        @if ($screen->offline)
            <native:row class="p-2 gap-2 items-center rounded-sm border-theme-warning border-2">
                <native:icon name="cloud_off" color="#B7730F" size="18" />
                <native:text class="text-theme-warning">Offline — data may be stale</native:text>
            </native:row>
        @endif

        @if ($screen->state === 'unauthorized')
            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-theme-text">Sign in on the Today tab first.</native:text>
                <native:pressable ref="goal-back-auth" a11y-label="Back to goals" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="backToGoals">
                    <native:text class="text-theme-on-primary font-bold">Back to Goals</native:text>
                </native:pressable>
            </native:column>
        @elseif ($screen->state === 'loading')
            <native:text class="text-theme-muted">Loading goal…</native:text>
        @elseif ($screen->state === 'offline' || $screen->state === 'error')
            <native:column class="p-4 gap-2 rounded-md border-theme-danger border-2 bg-theme-surface">
                <native:row class="gap-2 items-center">
                    <native:icon name="error" color="#D20812" size="20" />
                    <native:text class="text-theme-text">{{ $screen->error }}</native:text>
                </native:row>
                <native:pressable ref="goal-retry" a11y-label="Retry loading goal" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @else
            <native:column class="p-4 gap-2 rounded-md border-theme-border border-4 bg-theme-surface">
                <native:text class="text-lg font-bold text-theme-text">{{ $screen->goal['title'] ?? 'Untitled' }}</native:text>
                <native:text class="text-sm text-theme-success">{{ $screen->goal['status'] ?? (($screen->goal['state'] ?? '') ?: '') }}</native:text>
                @if (! empty($screen->goal['description']))
                    <native:text class="text-sm text-theme-muted">{{ $screen->goal['description'] }}</native:text>
                @endif
                <native:row class="gap-2 items-center">
                    <native:text class="text-sm text-theme-muted">Progress</native:text>
                    <native:text class="font-bold text-theme-text">{{ $screen->goal['progress'] ?? 0 }}%</native:text>
                </native:row>
                @if (! empty($screen->goal['target_date']))
                    <native:text class="text-sm text-theme-muted">Target: {{ $screen->goal['target_date'] }}</native:text>
                @endif
            </native:column>

            <native:pressable ref="goal-breakdown" a11y-label="Open AI breakdown proposals" a11y-hint="Review AI-proposed milestones before they land" class="p-4 rounded-sm bg-theme-surface border-theme-border border-2" @tap="openBreakdown">
                <native:row class="gap-2 items-center">
                    <native:icon name="auto_awesome" color="#2C5FA8" size="20" />
                    <native:text class="text-theme-text font-bold">AI breakdown</native:text>
                </native:row>
            </native:pressable>

            <native:text class="text-sm font-bold text-theme-muted">MILESTONES ({{ count($screen->milestones) }})</native:text>
            @forelse ($screen->milestones as $milestone)
                <native:row class="p-4 gap-2 items-center justify-between rounded-md border-theme-border border-2 bg-theme-surface" :key="$milestone['id'] ?? $loop->index">
                    <native:column class="gap-1">
                        <native:text class="text-theme-text font-bold">{{ $milestone['title'] ?? 'Untitled' }}</native:text>
                        <native:text class="text-sm text-theme-success">{{ $milestone['status'] ?? '' }}</native:text>
                    </native:column>
                    @if (! empty($milestone['target_date']))
                        <native:text class="text-sm text-theme-muted">{{ $milestone['target_date'] }}</native:text>
                    @endif
                </native:row>
            @empty
                <native:column class="p-3 rounded-md border-theme-border border-2 bg-theme-surface">
                    <native:text class="text-theme-muted">No milestones yet — propose an AI breakdown above.</native:text>
                </native:column>
            @endforelse

            <native:pressable ref="goal-back" a11y-label="Back to goals" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="backToGoals">
                <native:text class="text-theme-on-primary font-bold">Back to Goals</native:text>
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