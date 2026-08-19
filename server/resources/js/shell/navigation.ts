export interface NavItem {
    key: string;
    label: string;
    icon: string;
}

export const NAV_ITEMS: NavItem[] = [
    { key: 'today', label: 'Today', icon: 'today' },
    { key: 'week', label: 'Week', icon: 'week' },
    { key: 'calendar', label: 'Calendar', icon: 'calendar' },
    { key: 'schedule', label: 'Schedule', icon: 'schedule' },
    { key: 'goals', label: 'Goals / Roadmap', icon: 'goals' },
    { key: 'tasks', label: 'Tasks', icon: 'tasks' },
    { key: 'knowledge', label: 'Knowledge', icon: 'knowledge' },
    { key: 'analytics', label: 'Analytics', icon: 'analytics' },
    { key: 'settings', label: 'Settings', icon: 'settings' },
];

export type ShellView = (typeof NAV_ITEMS)[number]['key'];

export function isShellView(value: string): value is ShellView {
    return NAV_ITEMS.some((item) => item.key === value);
}

export const PRIMARY_VIEW: ShellView = 'today';
