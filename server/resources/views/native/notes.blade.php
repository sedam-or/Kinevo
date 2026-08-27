<native:top-bar title="Notes" background-color="#0B0E14" text-color="#F8FAFC" />
<native:scroll-view>
    <native:column class="p-4 gap-3">
        @if ($screen->offline)
            <native:text class="text-warning">Offline</native:text>
        @endif

        @if ($screen->state === 'unauthorized')
            <native:text>Sign in on the Today tab first.</native:text>
        @elseif ($screen->state === 'loading')
            <native:text>Loading notes…</native:text>
        @elseif ($screen->state === 'offline')
            <native:text>Backend unreachable.</native:text>
            <native:pressable ref="retry-notes" @tap="reload"><native:text>Retry</native:text></native:pressable>
        @elseif ($screen->state === 'error')
            <native:text>{{ $screen->error }}</native:text>
            <native:pressable ref="retry-notes-error" @tap="reload"><native:text>Retry</native:text></native:pressable>
        @else
            @forelse ($screen->notes as $note)
                <native:column class="p-3 gap-1" :key="$note['id'] ?? $loop->index">
                    <native:text>{{ $note['title'] ?? 'Untitled note' }}</native:text>
                    <native:text class="text-sm">{{ $note['updated_at'] ?? '' }}</native:text>
                </native:column>
            @empty
                <native:text>No notes yet.</native:text>
            @endforelse
        @endif
    </native:column>
</native:scroll-view>
<native:bottom-nav active-color="#4F46E5" text-color="#0B0E14">

    <native:bottom-nav-item id="today" label="Today" url="/" icon="home" />

    <native:bottom-nav-item id="capture" label="Capture" url="/capture" icon="add" />

    <native:bottom-nav-item id="workspaces" label="Workspace" url="/workspaces" icon="folder" />

    <native:bottom-nav-item id="more" label="More" url="/more" icon="settings" />

    <native:bottom-nav-item id="notes" label="Notes" url="/notes" icon="list" active />
</native:bottom-nav>