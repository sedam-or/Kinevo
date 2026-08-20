export type WorkLifeBand = 'no_data' | 'work_leaning' | 'balanced' | 'recharge_leaning';

export interface WorkLifeDay {
    date: string;
    productive_minutes: number;
    recharge_minutes: number;
    work_ratio: number;
    recharge_ratio: number;
    band: WorkLifeBand;
}

export interface WorkLifeAnalyticsResponse {
    from: string;
    to: string;
    productive_minutes: number;
    recharge_minutes: number;
    total_minutes: number;
    work_ratio: number;
    recharge_ratio: number;
    band: WorkLifeBand;
    days: WorkLifeDay[];
    disclaimer: string;
}