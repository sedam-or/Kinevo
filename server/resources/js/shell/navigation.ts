export interface NavItem {
    key: string;
    label: string;
    icon: string;
}

export type NavGroupKey = 'EXECUTE' | 'PLAN' | 'KNOWLEDGE' | 'REVIEW' | 'SYSTEM';

export interface NavGroup {
    key: NavGroupKey;
    label: string;
    items: NavItem[];
}

export const NAV_GROUPS: NavGroup[] = [
    {
        key: 'EXECUTE',
        label: 'Execute',
        items: [
            { key: 'today', label: 'Today', icon: 'today' },
            { key: 'week', label: 'Week', icon: 'week' },
            { key: 'calendar', label: 'Calendar', icon: 'calendar' },
        ],
    },
    {
        key: 'PLAN',
        label: 'Plan',
        items: [
            { key: 'goals', label: 'Goals / Roadmap', icon: 'goals' },
            { key: 'tasks', label: 'Tasks', icon: 'tasks' },
        ],
    },
    {
        key: 'KNOWLEDGE',
        label: 'Knowledge',
        items: [
            { key: 'knowledge', label: 'Knowledge', icon: 'knowledge' },
            { key: 'canvas', label: 'Canvas', icon: 'canvas' },
        ],
    },
    {
        key: 'REVIEW',
        label: 'Review',
        items: [{ key: 'analytics', label: 'Analytics', icon: 'analytics' }],
    },
    {
        key: 'SYSTEM',
        label: 'System',
        items: [
            { key: 'schedule', label: 'Schedule', icon: 'schedule' },
            { key: 'settings', label: 'Settings', icon: 'settings' },
        ],
    },
];

/** Flat list of every navigation item, preserving the §9 primary order. */
export const NAV_ITEMS: NavItem[] = NAV_GROUPS.flatMap((group) => group.items);

export type ShellView = (typeof NAV_ITEMS)[number]['key'];

export function isShellView(value: string): value is ShellView {
    return NAV_ITEMS.some((item) => item.key === value);
}

export const PRIMARY_VIEW: ShellView = 'today';
