<native:top-bar title="Goals" background-color="#0a0a0a" text-color="#EDEDEC" />
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
            <native:text class="text-theme-muted">Loading goals…</native:text>
        @elseif ($screen->state === 'offline')
            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-theme-text">Backend unreachable.</native:text>
                <native:pressable ref="retry-goals" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @elseif ($screen->state === 'error')
            <native:column class="p-4 gap-2 rounded-md border-theme-danger border-2 bg-theme-surface">
                <native:row class="gap-2 items-center">
                    <native:icon name="error" color="#D20812" size="20" />
                    <native:text class="text-theme-text">{{ $screen->error }}</native:text>
                </native:row>
                <native:pressable ref="retry-goals-error" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @elseif ($screen->state === 'entitlement')
            <native:column class="p-4 gap-2 rounded-md border-theme-warning border-2 bg-theme-surface">
                <native:row class="gap-2 items-center">
                    <native:icon name="workspace_premium" color="#B7730F" size="20" />
                    <native:text class="text-theme-warning font-bold">Plan limit</native:text>
                </native:row>
                <native:text class="text-theme-text">{{ $screen->error }}</native:text>
                <native:text class="text-sm text-theme-muted">Upgrade on the web app, then come back.</native:text>
                <native:pressable ref="retry-goals-entitlement" a11y-label="Retry after upgrade" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @else
            @forelse ($screen->goals as $goal)
                <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface" :key="$goal['id'] ?? $loop->index">
                    <native:pressable ref="goal-open-{{ $goal['id'] ?? $loop->index }}" a11y-label="Open goal {{ $goal['title'] ?? 'Untitled' }}" class="p-2 rounded-sm bg-transparent" @tap="openDetail({{ $goal['id'] ?? 0 }})">
                        <native:column class="gap-1">
                            <native:text class="text-theme-text font-bold">{{ $goal['title'] ?? 'Untitled' }}</native:text>
                            <native:text class="text-sm text-theme-success">{{ $goal['status'] ?? (($goal['state'] ?? '') ?: '') }}</native:text>
                        </native:column>
                    </native:pressable>
                    <native:row class="gap-2">
                        <native:pressable ref="goal-detail-{{ $goal['id'] ?? $loop->index }}" a11y-label="Open goal detail" class="p-2 rounded-sm bg-theme-surface border-theme-border border-2" @tap="openDetail({{ $goal['id'] ?? 0 }})">
                            <native:text class="text-theme-text font-bold">Detail</native:text>
                        </native:pressable>
                        <native:pressable ref="goal-ai-{{ $goal['id'] ?? $loop->index }}" a11y-label="Propose AI breakdown" class="p-2 rounded-sm bg-theme-primary border-theme-border border-2" @tap="proposeBreakdown({{ $goal['id'] ?? 0 }})">
                            <native:text class="text-theme-on-primary font-bold">AI breakdown</native:text>
                        </native:pressable>
                        <native:pressable ref="goal-proposals-{{ $goal['id'] ?? $loop->index }}" a11y-label="Review AI breakdown proposals" class="p-2 rounded-sm bg-theme-primary border-theme-border border-2" @tap="openBreakdown({{ $goal['id'] ?? 0 }})">
                            <native:text class="text-theme-on-primary font-bold">Proposals</native:text>
                        </native:pressable>
                    </native:row>
                </native:column>
            @empty
                <native:column class="p-3 rounded-md border-theme-border border-2 bg-theme-surface">
                    <native:text class="text-theme-muted">No goals yet.</native:text>
                </native:column>
            @endforelse
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