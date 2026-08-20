import { readToken } from '../api/token';

/**
 * Download the selected schedule range as a valid iCalendar (.ics) document
 * (FR-30 / TASK-143). Uses a raw fetch (the JSON API client cannot parse the
 * `text/calendar` response) and triggers a browser download of
 * `kinevo-schedule.ics`. Only exportable fields are exposed (NFR-03).
 */
export async function downloadScheduleIcs(from: string, to: string): Promise<void> {
    const token = readToken();
    const params = new URLSearchParams({ from, to });

    const response = await fetch(`/api/v1/schedule/export/ics?${params.toString()}`, {
        headers: token ? { Authorization: `Bearer ${token}` } : {},
    });

    if (!response.ok) {
        let message = `Export failed (${response.status}).`;
        try {
            const body = (await response.json()) as Record<string, unknown>;
            if (typeof body.error === 'string') {
                message = body.error;
            } else if (body.errors && typeof body.errors === 'object') {
                const values = Object.values(body.errors as Record<string, unknown[]>);
                const first = values[0]?.[0];
                if (typeof first === 'string') {
                    message = first;
                }
            }
        } catch {
            // non-JSON error body
        }
        throw new Error(message);
    }

    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'kinevo-schedule.ics';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
}