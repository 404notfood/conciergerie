export type ActivityNotification = {
    id: number;
    event: string;
    title: string;
    body: string;
    url: string;
    read_at: string | null;
    created_at: string;
};
export type NotificationInbox = {
    items: ActivityNotification[];
    unread: number;
    preferences: { email_events: string[]; browser_events: string[] };
    eventLabels: Record<string, string>;
};
export type Recurrence = {
    id: number;
    title: string;
    property_id: number;
    frequency: string;
    interval: number;
    starts_on: string;
    ends_on: string | null;
    next_on: string;
    time: string;
    duration: number;
    is_active: boolean;
    property: { id: number; name: string };
};
export type Occurrence = {
    id: number;
    status: string;
    scheduled_on: string;
    snapshot: {
        title: string;
        property_id: number;
        scheduled_at: string;
        duration: number;
        assigned_user_id: number | null;
    };
    recurrence: { id: number; title: string; is_active: boolean };
};
export type Availability = {
    id: number;
    user_id: number;
    starts_at: string;
    ends_at: string;
    note: string | null;
    user: { id: number; name: string };
};
