<native:top-bar title="More" background-color="#0a0a0a" text-color="#EDEDEC" />
<native:scroll-view class="bg-theme-bg">
    <native:column class="p-4 gap-3">
        @if ($screen->offline)
            <native:row class="p-2 gap-2 items-center rounded-sm border-theme-warning border-2">
                <native:icon name="cloud_off" color="#B7730F" size="18" />
                <native:text class="text-theme-warning">Offline</native:text>
            </native:row>
        @endif

        <native:column class="p-4 gap-2 rounded-md border-theme-border border-4 bg-theme-surface">
            <native:row class="gap-3 items-center">
                <native:icon name="bolt" color="#DE3005" size="24" />
                <native:text class="text-lg font-bold text-theme-text">Kinevo Mobile</native:text>
            </native:row>
            <native:text class="text-sm text-theme-muted">App v{{ $screen->appVersion }}</native:text>
            @if ($screen->authed)
                <native:text class="text-sm text-theme-success">Signed in — token stored on this device</native:text>
            @else
                <native:text class="text-sm text-theme-muted">Signed out</native:text>
            @endif
        </native:column>

        <native:text class="text-sm font-bold text-theme-muted">GOALS &amp; REVIEW</native:text>
        <native:column class="p-2 gap-1 rounded-md border-theme-border border-2 bg-theme-surface">
            <native:pressable ref="nav-goals" a11y-label="Open Goals" class="p-4 rounded-sm bg-transparent" @tap="go('goals')">
                <native:row class="gap-3 items-center">
                    <native:icon name="flag" color="#2C5FA8" size="20" />
                    <native:text class="text-theme-text font-bold">Goals</native:text>
                </native:row>
            </native:pressable>
            <native:pressable ref="nav-review" a11y-label="Open Review" class="p-4 rounded-sm bg-transparent" @tap="go('review')">
                <native:row class="gap-3 items-center">
                    <native:icon name="assessment" color="#2C5FA8" size="20" />
                    <native:text class="text-theme-text font-bold">Review</native:text>
                </native:row>
            </native:pressable>
        </native:column>

        <native:text class="text-sm font-bold text-theme-muted">KNOWLEDGE</native:text>
        <native:column class="p-2 gap-1 rounded-md border-theme-border border-2 bg-theme-surface">
            <native:pressable ref="nav-notes" a11y-label="Open Notes" class="p-4 rounded-sm bg-transparent" @tap="go('notes')">
                <native:row class="gap-3 items-center">
                    <native:icon name="menu_book" color="#2C5FA8" size="20" />
                    <native:text class="text-theme-text font-bold">Notes</native:text>
                </native:row>
            </native:pressable>
            <native:pressable ref="nav-canvases" a11y-label="Open Canvases" class="p-4 rounded-sm bg-transparent" @tap="go('canvases')">
                <native:row class="gap-3 items-center">
                    <native:icon name="draw" color="#2C5FA8" size="20" />
                    <native:text class="text-theme-text font-bold">Canvases</native:text>
                </native:row>
            </native:pressable>
        </native:column>

        <native:text class="text-sm font-bold text-theme-muted">INBOX</native:text>
        <native:column class="p-2 gap-1 rounded-md border-theme-border border-2 bg-theme-surface">
            <native:pressable ref="nav-notifications" a11y-label="Open Notifications" class="p-4 rounded-sm bg-transparent" @tap="go('notifications')">
                <native:row class="gap-3 items-center">
                    <native:icon name="notifications" color="#2C5FA8" size="20" />
                    <native:text class="text-theme-text font-bold">Notifications</native:text>
                </native:row>
            </native:pressable>
        </native:column>

        @if ($screen->authed)
            <native:pressable ref="sign-out" a11y-label="Sign out" a11y-hint="Removes the token from this device" class="p-4 rounded-sm border-theme-danger border-2" @tap="signOut">
                <native:text class="text-theme-danger font-bold">Sign out</native:text>
            </native:pressable>
        @endif
    </native:column>
</native:scroll-view>
<native:bottom-nav background-color="#0a0a0a" active-color="#DE3005" text-color="#EDEDEC">

    <native:bottom-nav-item id="today" label="Today" url="/" icon="today" />

    <native:bottom-nav-item id="tasks" label="Tasks" url="/tasks" icon="list_alt" />

    <native:bottom-nav-item id="capture" label="Capture" url="/capture" icon="add" />

    <native:bottom-nav-item id="workspaces" label="Workspace" url="/workspaces" icon="folder" />

    <native:bottom-nav-item id="more" label="More" url="/more" icon="more_vert" active />
</native:bottom-nav>