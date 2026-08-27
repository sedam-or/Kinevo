<native:top-bar title="Review" background-color="#0a0a0a" text-color="#EDEDEC" />
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
            </native:column>
        @elseif ($screen->state === 'loading')
            <native:text class="text-theme-muted">Loading review…</native:text>
        @elseif ($screen->state === 'offline' || $screen->state === 'error')
            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-theme-text">{{ $screen->error ?: 'Review unavailable — retry.' }}</native:text>
                <native:pressable ref="retry-review" class="p-3 rounded-sm bg-theme-primary border-theme-border border-2" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @else
            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-sm font-bold text-theme-muted">TODAY</native:text>
                <native:row class="gap-2 items-center">
                    <native:text class="font-bold text-theme-success">{{ $screen->capacity['status'] ?? 'ok' }}</native:text>
                    <native:text class="text-theme-muted">{{ $screen->capacity['available_minutes'] ?? 0 }} min free today</native:text>
                </native:row>
            </native:column>

            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-sm font-bold text-theme-muted">GOALS ({{ count($screen->goals) }})</native:text>
                @forelse ($screen->goals as $goal)
                    <native:row class="gap-2 items-center justify-between" :key="$goal['id'] ?? $loop->index">
                        <native:text class="text-theme-text">{{ $goal['title'] ?? 'Untitled' }}</native:text>
                        <native:text class="text-sm text-theme-success">{{ $goal['status'] ?? (($goal['state'] ?? '') ?: '') }}</native:text>
                    </native:row>
                @empty
                    <native:text class="text-theme-muted">No goals yet.</native:text>
                @endforelse
            </native:column>
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