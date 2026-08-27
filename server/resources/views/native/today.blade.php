<native:top-bar title="Today" background-color="#0a0a0a" text-color="#EDEDEC" />
<native:scroll-view class="bg-theme-bg">
    <native:column class="p-4 gap-3">
        @if ($screen->offline)
            <native:row class="p-2 gap-2 items-center rounded-sm border-theme-warning border-2">
                <native:icon name="cloud_off" color="#B7730F" size="18" />
                <native:text class="text-theme-warning">Offline — data may be stale</native:text>
            </native:row>
        @endif

        @if ($screen->state === 'unauthorized')
            <native:column class="p-5 gap-3 rounded-md border-theme-border border-4 bg-theme-surface">
                <native:icon name="lock" color="#DE3005" size="30" />
                <native:text class="text-xl font-bold text-theme-text">Welcome to Kinevo</native:text>
                <native:text class="text-theme-muted">Sign in to sync your day onto this device.</native:text>
                <native:pressable ref="sign-in" a11y-label="Sign in" a11y-hint="Signs in with your Kinevo account" class="p-3 rounded-sm bg-theme-primary border-theme-border border-2" @tap="signIn">
                    <native:text class="text-theme-on-primary font-bold">Sign in</native:text>
                </native:pressable>
                <native:text class="text-sm text-theme-muted">Token is stored on this device only.</native:text>
            </native:column>
        @elseif ($screen->state === 'loading')
            <native:text class="text-theme-muted">Loading today…</native:text>
        @elseif ($screen->state === 'offline')
            <native:column class="p-4 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="text-theme-text">Backend unreachable — you are offline.</native:text>
                <native:pressable ref="retry-offline" a11y-label="Retry" class="p-3 rounded-sm bg-theme-primary border-theme-border border-2" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @elseif ($screen->state === 'conflict' || $screen->state === 'error')
            <native:column class="p-4 gap-2 rounded-md border-theme-danger border-2 bg-theme-surface">
                <native:row class="gap-2 items-center">
                    <native:icon name="error" color="#D20812" size="20" />
                    <native:text class="text-theme-text">{{ $screen->error }}</native:text>
                </native:row>
                <native:pressable ref="retry-error" a11y-label="Retry" a11y-hint="Reloads the today view" class="p-3 rounded-sm bg-theme-primary border-theme-border border-2" @tap="reload">
                    <native:text class="text-theme-on-primary font-bold">Retry</native:text>
                </native:pressable>
            </native:column>
        @else
            <native:text class="text-sm font-bold text-theme-muted">NOW</native:text>
            @forelse ($screen->events as $event)
                <native:column class="p-4 gap-1 rounded-md border-theme-border border-4 bg-theme-surface" :key="$event['id'] ?? $loop->index">
                    <native:text class="text-lg font-bold text-theme-text">{{ $event['title'] ?? 'Untitled' }}</native:text>
                    <native:text class="text-sm text-theme-muted">{{ $event['start_at'] ?? ($event['window'] ?? '') }}</native:text>
                </native:column>
            @empty
                <native:column class="p-3 rounded-md border-theme-border border-2 bg-theme-surface">
                    <native:text class="text-theme-text">Nothing scheduled right now.</native:text>
                </native:column>
            @endforelse

            <native:text class="text-sm font-bold text-theme-muted">NEXT / LATER</native:text>
            @forelse ($screen->emptySlots as $slot)
                <native:column class="p-3 gap-1 rounded-md border-theme-border border-2 bg-theme-surface" :key="$slot['start'] ?? $loop->index">
                    <native:text class="text-theme-text">Free window</native:text>
                    <native:text class="text-sm text-theme-muted">{{ $slot['start'] ?? '' }} → {{ $slot['end'] ?? '' }}</native:text>
                </native:column>
            @empty
                <native:text class="text-theme-muted">No free slots.</native:text>
            @endforelse

            <native:text class="text-sm font-bold text-theme-muted">CAPACITY</native:text>
            <native:row class="p-3 gap-2 items-center rounded-md border-theme-border border-2 bg-theme-surface">
                <native:text class="font-bold text-theme-success">{{ $screen->capacity['status'] ?? 'ok' }}</native:text>
                <native:text class="text-theme-muted">{{ $screen->capacity['available_minutes'] ?? 0 }} min free today</native:text>
            </native:row>
        @endif
    </native:column>
</native:scroll-view>
<native:fab icon="add" ref="new-task" @tap="goCapture" />
<native:bottom-nav background-color="#0a0a0a" active-color="#DE3005" text-color="#EDEDEC">

    <native:bottom-nav-item id="today" label="Today" url="/" icon="today" active />

    <native:bottom-nav-item id="tasks" label="Tasks" url="/tasks" icon="list_alt" />

    <native:bottom-nav-item id="capture" label="Capture" url="/capture" icon="add" />

    <native:bottom-nav-item id="workspaces" label="Workspace" url="/workspaces" icon="folder" />

    <native:bottom-nav-item id="more" label="More" url="/more" icon="more_vert" />
</native:bottom-nav>