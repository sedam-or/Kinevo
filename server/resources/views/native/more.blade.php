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

        <native:text class="text-sm font-bold text-theme-muted">COMING TO THIS TAB</native:text>
        <native:column class="p-3 gap-2 rounded-md border-theme-border border-2 bg-theme-surface">
            <native:row class="gap-3 items-center">
                <native:icon name="assessment" color="#2C5FA8" size="20" />
                <native:text class="text-theme-text">Review</native:text>
            </native:row>
            <native:row class="gap-3 items-center">
                <native:icon name="notifications" color="#2C5FA8" size="20" />
                <native:text class="text-theme-text">Notifications</native:text>
            </native:row>
            <native:row class="gap-3 items-center">
                <native:icon name="settings" color="#2C5FA8" size="20" />
                <native:text class="text-theme-text">Settings</native:text>
            </native:row>
        </native:column>

        @if ($screen->authed)
            <native:pressable ref="sign-out" a11y-label="Sign out" a11y-hint="Removes the token from this device" class="p-3 rounded-sm border-theme-danger border-2" @tap="signOut">
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