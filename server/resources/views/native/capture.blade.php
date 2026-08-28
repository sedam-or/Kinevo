<native:top-bar title="Capture" background-color="#0a0a0a" text-color="#EDEDEC" />
<native:scroll-view class="bg-theme-bg">
    <native:column class="p-4 gap-3">
        @if ($screen->offline)
            <native:row class="p-2 gap-2 items-center rounded-sm border-theme-warning border-2">
                <native:icon name="cloud_off" color="#B7730F" size="18" />
                <native:text class="text-theme-warning">Offline — captures queue for sync</native:text>
            </native:row>
        @endif

        <native:text class="text-sm font-bold text-theme-muted">QUICK CAPTURE</native:text>
        <native:text class="text-theme-text">Drop a task into your schedule with one tap.</native:text>
        <native:text_input
            ref="capture-draft"
            a11y-label="Task title"
            placeholder="What needs doing?"
            keyboard="text"
            class="p-3 rounded-md border-theme-border border-2 bg-theme-surface"
            @change="setTitle"
        />
        <native:pressable ref="capture-draft-submit" a11y-label="Capture typed task" a11y-hint="Adds the typed task to your day" class="p-3 rounded-md bg-theme-primary border-theme-border border-2" @tap="captureDraft">
            <native:text class="text-theme-on-primary font-bold">Capture</native:text>
        </native:pressable>
        @if ($screen->status === 'saved' || $screen->status === 'queued' || $screen->status === 'error')
            <native:text class="text-sm {{ $screen->status === 'error' ? 'text-theme-danger' : 'text-theme-success' }}">{{ $screen->message }}</native:text>
        @endif

        <native:pressable ref="capture-plan" a11y-label="Plan tomorrow" a11y-hint="Adds Plan tomorrow to your day" class="p-3 gap-2 rounded-md border-theme-border border-2 bg-theme-surface" @tap="captureNow">
            <native:row class="gap-3 items-center">
                <native:icon name="edit_calendar" color="#DE3005" size="20" />
                <native:text class="text-theme-text font-bold">Plan tomorrow</native:text>
            </native:row>
        </native:pressable>
        <native:pressable ref="capture-review" a11y-label="Review the week" a11y-hint="Adds Review the week to your day" class="p-3 gap-2 rounded-md border-theme-border border-2 bg-theme-surface" @tap="captureReview">
            <native:row class="gap-3 items-center">
                <native:icon name="assessment" color="#DE3005" size="20" />
                <native:text class="text-theme-text font-bold">Review the week</native:text>
            </native:row>
        </native:pressable>
        <native:pressable ref="capture-note" a11y-label="Capture a reading note" a11y-hint="Adds Capture a reading note to your day" class="p-3 gap-2 rounded-md border-theme-border border-2 bg-theme-surface" @tap="captureNote">
            <native:row class="gap-3 items-center">
                <native:icon name="book" color="#DE3005" size="20" />
                <native:text class="text-theme-text font-bold">Capture a reading note</native:text>
            </native:row>
        </native:pressable>

        @if ($screen->status === 'saving')
            <native:text class="text-theme-muted">Saving…</native:text>
        @elseif ($screen->status === 'saved')
            <native:row class="p-2 gap-2 items-center rounded-sm border-theme-success border-2">
                <native:icon name="check_circle" color="#1D8A4E" size="18" />
                <native:text class="text-theme-success">{{ $screen->message }}</native:text>
            </native:row>
        @elseif ($screen->status === 'queued')
            <native:row class="p-2 gap-2 items-center rounded-sm border-theme-warning border-2">
                <native:icon name="sync" color="#B7730F" size="18" />
                <native:text class="text-theme-warning">{{ $screen->message }}</native:text>
            </native:row>
        @elseif ($screen->status === 'error')
            <native:row class="p-2 gap-2 items-center rounded-sm border-theme-danger border-2">
                <native:icon name="error" color="#D20812" size="18" />
                <native:text class="text-theme-danger">{{ $screen->message }}</native:text>
            </native:row>
        @endif
    </native:column>
</native:scroll-view>
<native:fab icon="add" ref="capture-fab" @tap="captureNow" />
<native:bottom-nav background-color="#0a0a0a" active-color="#DE3005" text-color="#EDEDEC">

    <native:bottom-nav-item id="today" label="Today" url="/" icon="today" />

    <native:bottom-nav-item id="tasks" label="Tasks" url="/tasks" icon="list_alt" />

    <native:bottom-nav-item id="capture" label="Capture" url="/capture" icon="add" active />

    <native:bottom-nav-item id="workspaces" label="Workspace" url="/workspaces" icon="folder" />

    <native:bottom-nav-item id="more" label="More" url="/more" icon="more_vert" />
</native:bottom-nav>