<native:top-bar title="Kinevo" background-color="#0a0a0a" text-color="#EDEDEC" />
<native:scroll-view class="bg-theme-bg">
    <native:column class="p-4 gap-3">
        <native:column class="p-4 gap-2 rounded-md border-theme-danger border-2 bg-theme-surface">
            @if ($screen->offline)
                <native:row class="gap-2 items-center">
                    <native:icon name="cloud_off" color="#8A5A00" size="20" />
                    <native:text class="text-theme-warning font-bold">Offline</native:text>
                </native:row>
            @endif
            <native:text class="text-theme-text">{{ $message }}</native:text>
        </native:column>
    </native:column>
</native:scroll-view>
<native:bottom-nav background-color="#0a0a0a" active-color="#DE3005" text-color="#EDEDEC">

    <native:bottom-nav-item id="today" label="Today" url="/" icon="today" />

    <native:bottom-nav-item id="tasks" label="Tasks" url="/tasks" icon="list_alt" />

    <native:bottom-nav-item id="capture" label="Capture" url="/capture" icon="add" />

    <native:bottom-nav-item id="workspaces" label="Workspace" url="/workspaces" icon="folder" />

    <native:bottom-nav-item id="more" label="More" url="/more" icon="more_vert" active />
</native:bottom-nav>