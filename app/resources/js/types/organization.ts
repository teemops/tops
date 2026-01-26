export interface OrganizationMember {
    id: string | null; // null for owner (no member record)
    user_id: string;
    organization_id: string;
    role: 'owner' | 'administrator' | 'auditor' | 'viewer' | null; // null = no role assigned yet
    user: {
        id: string;
        name: string;
        email: string;
    };
    created_at: string;
}

export interface OrganizationInvitation {
    id: string;
    organization_id: string;
    email: string;
    token: string;
    expires_at: string;
    created_at: string;
    accepted_at: string | null;
    invited_by: {
        id: string;
        name: string;
    };
    organization?: {
        id: string;
        name: string;
    };
}
