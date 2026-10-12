export type Person = { id: number; name: string; email: string };
export type Member = {
    id: number;
    concierge_id: number;
    user_id: number;
    role: 'manager' | 'worker' | 'client';
    client_id: number | null;
    team: string | null;
    company: string | null;
    user: Person;
    concierge?: { id: number; name: string };
};
export type ClientRecord = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    kind: string;
};
export type PropertyRecord = {
    id: number;
    client_id: number;
    name: string;
    address: string;
    kind: string;
    instructions: string | null;
    client?: ClientRecord;
};
export type PhotoRecord = {
    id: number;
    stage: string;
    captured_at: string | null;
    created_at: string;
};
export type ReportRecord = {
    id: number;
    notes: string;
    answers: boolean[];
    status: string;
    feedback: string | null;
    reviewed_at: string | null;
    author: Person;
    reviewer: Person | null;
    photos: PhotoRecord[];
};
export type MissionRecord = {
    id: number;
    property_id: number;
    title: string;
    kind: string;
    scheduled_at: string;
    duration: number;
    status: string;
    version: number;
    checklist: string[];
    instructions: string | null;
    accessible_code?: string | null;
    assigned_user_id: number | null;
    property: PropertyRecord;
    assignee: Person | null;
    reports: ReportRecord[];
};
export type IncidentRecord = {
    id: number;
    mission_id: number;
    description: string;
    status: string;
    client_message: string | null;
    shared_at: string | null;
    shared_photo_ids: number[] | null;
    author: Person;
    mission: MissionRecord;
    created_at: string;
};
export type RequestRecord = {
    id: number;
    title: string;
    description: string;
    status: string;
    decision: string | null;
    property: PropertyRecord;
    author: Person;
    created_at: string;
};
export type TemplateRecord = { id: number; name: string; checklist: string[] };
export type StayRecord = {
    id: number;
    title: string;
    starts_on: string;
    ends_on: string;
    source: string;
    kind: string;
    property: PropertyRecord;
};
export type MessageRecord = {
    id: number;
    client_id: number;
    body: string;
    author_id: number;
    author: Person;
    created_at: string;
};
export type ActivityRecord = {
    id: number;
    description: string;
    user: Person;
    created_at: string;
};
export type WorkspaceProps = {
    concierge: { id: number; name: string };
    membership: Member;
    workspaces: Member[];
    missions: MissionRecord[];
    properties: PropertyRecord[];
    clients: ClientRecord[];
    members: Member[];
    templates: TemplateRecord[];
    stays: StayRecord[];
    incidents: IncidentRecord[];
    requests: RequestRecord[];
    messages: MessageRecord[];
    activities: ActivityRecord[];
};
