export function parseIso(iso: string): Date {
    const normalized = iso.replace(/\.(\d{3})\d+/, '.$1');
    return new Date(normalized);
}

export function formatTime(iso: string): string {
    return parseIso(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

export function formatDate(iso: string): string {
    return parseIso(iso).toLocaleDateString([], { month: 'short', day: 'numeric' });
}
