<native:top-bar title="AI breakdown" background-color="#0a0a0a" text-color="#EDEDEC" />
<native:scroll-view class="bg-theme-bg">
    <native:column class="p-4 gap-3">
        @if ($screen->offline)
            <native:row class="p-2 gap-2 items-center rounded-sm border-theme-warning border-2">
                <native:icon name="cloud_off" color="#8A5A00" size="18" />
                <native:text class="text-theme-warning">Offline — decisions wait until reachable</native:text>
            </native:row>
        @endif

        @if ($screen->state === 'unauthorized')
            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-theme-text">Sign in on the Today tab first.</native:text>
            </native:column>
        @elseif ($screen->state === 'loading')
            <native:text class="text-theme-muted">Loading proposals…</native:text>
        @elseif ($screen->state === 'entitlement')
            <native:column class="p-4 gap-2 rounded-md border-theme-warning border-2 bg-theme-surface">
                <native:row class="gap-2 items-center">
                    <native:icon name="workspace_premium" color="#8A5A00" size="20" />
                    <native:text class="text-theme-warning font-bold">Plan limit</native:text>
                </native:row>
                <native:text class="text-theme-text">{{ $screen->error }}</native:text>
                <native:pressable ref="bd-ent-retry" a11y-label="Retry proposals" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @elseif ($screen->state === 'offline' || $screen->state === 'error')
            <native:column class="p-4 gap-2 rounded-md border-theme-danger border-2 bg-theme-surface">
                <native:row class="gap-2 items-center">
                    <native:icon name="error" color="#D20812" size="20" />
                    <native:text class="text-theme-text">{{ $screen->error }}</native:text>
                </native:row>
                <native:pressable ref="bd-retry" a11y-label="Retry proposals" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @else
            @if ($screen->notice !== '')
                <native:text class="text-sm text-theme-success">{{ $screen->notice }}</native:text>
            @endif

            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-theme-text">Reviewing an AI proposal creates real milestones only after you accept. Nothing auto-commits.</native:text>
            </native:column>

            @forelse ($screen->proposals as $proposal)
                <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface" :key="$proposal['id'] ?? $loop->index">
                    <native:text class="text-sm text-theme-muted">Proposal #{{ $proposal['id'] ?? '—' }}</native:text>
                    <native:row class="gap-2 items-center">
                        <native:icon name="auto_awesome" color="#2C5FA8" size="20" />
                        <native:text class="text-theme-text font-bold">Milestones proposed</native:text>
                    </native:row>
                    @forelse ($screen->previewTitles($proposal['payload'] ?? []) as $title)
                        <native:text class="text-theme-text">• {{ $title }}</native:text>
                    @empty
                        <native:text class="text-theme-muted">No milestone titles in this proposal.</native:text>
                    @endforelse
                    <native:row class="gap-2">
                        <native:pressable ref="bd-accept-{{ $proposal['id'] ?? $loop->index }}" a11y-label="Accept proposal and create milestones" a11y-hint="Creates the proposed milestones" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="accept({{ $proposal['id'] ?? 0 }})">
                            <native:text class="text-theme-on-primary font-bold">Accept</native:text>
                        </native:pressable>
                        <native:pressable ref="bd-reject-{{ $proposal['id'] ?? $loop->index }}" a11y-label="Reject proposal" class="p-4 rounded-sm bg-theme-surface border-theme-border border-2" @tap="reject({{ $proposal['id'] ?? 0 }})">
                            <native:text class="text-theme-text font-bold">Reject</native:text>
                        </native:pressable>
                    </native:row>
                </native:column>
            @empty
                <native:column class="p-3 rounded-md border-theme-border border-2 bg-theme-surface">
                    <native:text class="text-theme-muted">No pending proposals for this goal.</native:text>
                </native:column>
            @endforelse

            <native:pressable ref="bd-back" a11y-label="Back to goals" class="p-4 rounded-sm bg-theme-primary border-theme-border border-2" @tap="backToGoals">
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