# Organization Settings with Team Management - Implementation Plan

## Overview
Add team management capabilities to Organizations, allowing users to invite team members with role-based access control (Owner, Administrator, Auditor, Viewer). Includes invitation system where users join organizations without roles initially, then roles are assigned by owners/administrators. Ownership can be transferred between users.

## Key Architecture Principles

1. **Organization ID References**: All features (AWS accounts, Scans, future features) reference `organization_id`, NOT `user_id`. This ensures ownership can be transferred without breaking relationships.

2. **Ownership Transfer**: Organization ownership is tracked via `organizations.user_id` which can be updated to transfer ownership to another team member.

3. **Owner Role**: "Owner" is a visible role in the system (not hidden), stored in `organization_members` table with `role='owner'`. The owner is also tracked in `organizations.user_id` for quick lookup.

4. **Default Organization Creation**:
   - **New users (not invited)**: Automatically get a default organization created on signup
   - **Invited users**: Do NOT get a default organization, only added to the organization they were invited to

5. **Multi-Organization Membership**: Users can belong to multiple organizations (either created by them or via invites).

## User Stories

### User Story 1: Organization Settings Access
As a user, I want to manage settings for Organizations by clicking on a Settings cog icon. This allows me to add/modify settings and invite team members.

### User Story 2: Organization Roles
As teemops product owner I want to have 4 types of roles available with different permissions. The 4 roles are Owner, Administrator, Auditor, Viewer. These are not able to be modified currently.

**Roles:**
- **Owner**: The person who owns the organization (can be transferred). Has all Administrator permissions plus can delete organization and transfer ownership. Visible role in the system.
- **Administrator**: Can add/invite users, change organization settings, run scans, add AWS accounts, view everything
- **Auditor**: Can run scans against any AWS account in the org, view reports, insights, scans
- **Viewer**: Can view recent scans, reports and insights

**Rules:**
- Users can only have 1 role per organization
- Roles cannot be modified (fixed set)
- Ownership can be transferred to another team member (changes `organizations.user_id` and updates member role)

### User Story 3: Organization Invites
As an Organization Owner I want to invite team members to my organization.

**Expected Behavior:**
- Invite user via email
- User receives invite and can accept
- If user doesn't have account: They sign up to teemops
- If user has account: They login
- User automatically becomes part of the organization after accepting
- **User is NOT assigned any roles or permissions after signup** - this is a separate user story
- User invites expire after 24 hours

### User Story 4: Organization Member Management
As an organization owner or administrator I want to be able to manage users who have been invited so that I can add them to a role or remove them from a role.

**Expected Behavior:**
- Consistent with all design patterns followed so far for the UI
- Once a user has been invited and accepted the invitation to join an organisation, an email will be sent to the organization owner or administrator who invited them so they can manage them as a user
- View a table or list of all users who are invited and accepted invites
- Be able to assign a user with a role
- A user can only have one role

## Database Schema Changes

### 1. Create `organization_members` table (pivot table)
**Migration**: `create_organization_members_table.php`
- `id` (uuid, primary key)
- `organization_id` (uuid, foreign key -> organizations.id)
- `user_id` (bigint, foreign key -> users.id)
- `role` (enum: 'owner', 'administrator', 'auditor', 'viewer', nullable) - NULL means no role assigned yet
- `created_at` (timestamp)
- `updated_at` (timestamp)
- Unique constraint on (organization_id, user_id)
- Indexes on organization_id, user_id, role

**Note**: 
- Role can be NULL for users who have accepted invitation but haven't been assigned a role yet
- Owner role is stored here AND in `organizations.user_id` for quick lookup
- Only one user can have 'owner' role per organization (enforced in application logic)

### 2. Create `organization_invitations` table
**Migration**: `create_organization_invitations_table.php`
- `id` (uuid, primary key)
- `organization_id` (uuid, foreign key -> organizations.id)
- `email` (string)
- `token` (string, unique) - UUID for invitation link
- `invited_by` (bigint, foreign key -> users.id)
- `expires_at` (timestamp) - 24 hours from creation
- `accepted_at` (timestamp, nullable)
- `created_at` (timestamp)
- `updated_at` (timestamp)
- Indexes on organization_id, email, token, expires_at

**Note**: Invitations do NOT include a role - roles are assigned after acceptance via member management.

### 3. Update `organizations` table
- Keep `user_id` as the organization owner (can be updated to transfer ownership)
- `user_id` references the current owner (for quick lookup and ownership transfer)
- Owner is also stored in `organization_members` table with `role='owner'`
- When ownership is transferred:
  1. Update `organizations.user_id` to new owner
  2. Update old owner's role in `organization_members` (remove 'owner' role, assign new role or remove member)
  3. Update new owner's role in `organization_members` to 'owner' (or create member record if needed)

## Backend Implementation

### Models

#### 1. Create `OrganizationMember` model
**File**: `app/Models/OrganizationMember.php`
- Relationships: `belongsTo(Organization)`, `belongsTo(User)`
- Fillable: organization_id, user_id, role
- Casts: role as string

#### 2. Create `OrganizationInvitation` model
**File**: `app/Models/OrganizationInvitation.php`
- Relationships: `belongsTo(Organization)`, `belongsTo(User, 'invited_by')`
- Fillable: organization_id, email, token, invited_by, expires_at
- Methods:
  - `isExpired()`: Check if invitation has expired (expires_at < now)
  - `isAccepted()`: Check if invitation was accepted (accepted_at is not null)
  - `accept(User $user)`: Accept invitation - creates OrganizationMember record with role=NULL, sets accepted_at, sends notification email to inviter

#### 3. Update `Organization` model
**File**: `app/Models/Organization.php`
- Add relationship: `hasMany(OrganizationMember)`
- Add relationship: `hasMany(OrganizationInvitation)`
- Add method: `members()` - returns all members (including owner)
- Add method: `owner()` - returns the owner User (via user_id)
- Add method: `isOwner(User $user)`: Check if user is owner (user_id === $user->id)
- Add method: `getMemberRole(User $user)`: Get user's role - returns role from OrganizationMember record (can be 'owner', 'administrator', 'auditor', 'viewer', or NULL)
- Add method: `transferOwnership(User $newOwner)`: Transfer ownership to new user (updates user_id and member roles)
- Update `user()` relationship: Keep existing but note it's for ownership (can be transferred)

#### 4. Update `User` model
**File**: `app/Models/User.php`
- Add relationship: `belongsToMany(Organization)` via `organization_members` (as member)
- Keep existing relationship: `hasMany(Organization)` (as owner)
- Update method: `organizations()` - returns all organizations (owned + member of) - merge both relationships
- Add method: `ownedOrganizations()` - returns organizations where user is owner
- Add method: `memberOrganizations()` - returns organizations where user is member

### Permission System

#### 1. Create `OrganizationPermission` service
**File**: `app/Services/OrganizationPermission.php`
- Methods:
  - `canManageSettings(User $user, Organization $org)`: Only administrators
  - `canInviteMembers(User $user, Organization $org)`: Only administrators
  - `canManageMembers(User $user, Organization $org)`: Only administrators
  - `canRunScans(User $user, Organization $org)`: Administrators and auditors
  - `canAddAwsAccounts(User $user, Organization $org)`: Only administrators
  - `canViewFindings(User $user, Organization $org)`: All roles
  - `canViewInsights(User $user, Organization $org)`: All roles
  - `getUserRole(User $user, Organization $org)`: Returns role string ('owner', 'administrator', 'auditor', 'viewer', or null)
  - `hasRole(User $user, Organization $org)`: Returns true if user has any role (including owner)
  - `hasNoRole(User $user, Organization $org)`: Returns true if user is member but has no role assigned yet
  - `canTransferOwnership(User $user, Organization $org)`: Only owner can transfer ownership
  - `canDeleteOrganization(User $user, Organization $org)`: Only owner can delete organization

#### 2. Update `SetOrganizationContext` middleware
**File**: `app/Http/Middleware/SetOrganizationContext.php`
- Update to check both ownership and membership
- Use `OrganizationPermission` service to get user role
- Attach role to request context
- Allow access if user is owner OR member

### API Endpoints

#### Team Management Endpoints
**File**: `app/Http/Controllers/Api/OrganizationMembersController.php`

1. **List Members**
   - `GET /api/organizations/{orgId}/members`
   - Returns: List of members with user info and roles
   - Permission: Only members with Administrator Role or Owner Role can list

2. **Invite Member**
   - `POST /api/organizations/{orgId}/members/invite`
   - Body: `{ email: string }` (no role - role assigned later)
   - Permission: Only administrators and owner
   - Creates invitation record with 24-hour expiration and sends email to invitee
   - Note: Invited users do NOT get a default organization created on signup

3. **Update Member Role**
   - `PUT /api/organizations/{orgId}/members/{memberId}`
   - Body: `{ role: string }` (can be 'owner', 'administrator', 'auditor', 'viewer')
   - Permission: Only owner (administrators cannot change roles, only owner can)
   - Cannot remove owner role (must transfer ownership first)
   - If assigning 'owner' role, automatically transfers ownership (updates organizations.user_id)

4. **Remove Member**
   - `DELETE /api/organizations/{orgId}/members/{memberId}`
   - Permission: Only administrators
   - Cannot remove owner
   - Cannot remove self if only administrator

5. **Accept Invitation**
   - `POST /api/organizations/invitations/{token}/accept`
   - Permission: Authenticated user (email must match invitation email, or user can sign up first)
   - Creates OrganizationMember record with role=NULL
   - Marks invitation as accepted (sets accepted_at)
   - Sends notification email to the inviter (owner/administrator who sent the invite)
   - If user doesn't exist yet, they must sign up first, then accept invitation

6. **List Invitations**
   - `GET /api/organizations/{orgId}/invitations`
   - Permission: Only administrators and owner
   - Returns: Pending invitations

7. **Cancel Invitation**
   - `DELETE /api/organizations/{orgId}/invitations/{invitationId}`
   - Permission: Only administrators and owner

8. **Transfer Ownership**
   - `POST /api/organizations/{orgId}/transfer-ownership`
   - Body: `{ user_id: string }` (new owner's user ID)
   - Permission: Only current owner
   - Updates `organizations.user_id` to new owner
   - Updates member roles (old owner loses 'owner' role, new owner gets 'owner' role)
   - Returns updated organization with new owner info

### Form Requests

1. **InviteMemberRequest**
   - File: `app/Http/Requests/InviteMemberRequest.php`
   - Validation: email (required, email)
   - Note: No role field - roles are assigned after acceptance

2. **UpdateMemberRoleRequest**
   - File: `app/Http/Requests/UpdateMemberRoleRequest.php`
   - Validation: role (required, in:owner,administrator,auditor,viewer)

3. **TransferOwnershipRequest**
   - File: `app/Http/Requests/TransferOwnershipRequest.php`
   - Validation: user_id (required, exists:users,id)
   - Business rule: User must be a member of the organization

### Email Notifications

1. **OrganizationInvitation** notification (to invitee)
   - File: `app/Notifications/OrganizationInvitation.php`
   - Sends invitation email with acceptance link
   - Link format: `/organizations/invitations/{token}/accept`
   - Includes organization name and inviter name
   - If user doesn't have account, directs them to sign up first

2. **MemberJoinedNotification** notification (to inviter)
   - File: `app/Notifications/MemberJoinedNotification.php`
   - Sent when user accepts invitation
   - Notifies the owner/administrator who sent the invite
   - Includes new member's name/email and link to member management page
   - Only sent to administrators/owner

### Routes

**File**: `app/routes/api.php`
- Add team management routes under organizations group
- All routes require `organization.context` middleware

### Organization Creation Logic Updates

**File**: `app/Http/Controllers/Auth/FirebaseAuthController.php`
- **Current behavior**: Always creates default organization for new users (line 180-185)
- **Update**: Check if user was invited before creating default organization
- **Logic**:
  - Check if user has any pending/accepted invitations
  - If user has invitations: Do NOT create default organization (they'll join invited orgs)
  - If user has NO invitations: Create default organization as before
- **After creating organization**: Also create OrganizationMember record with role='owner'

**File**: `app/Http/Controllers/Auth/RegisteredUserController.php` (if exists)
- Same logic: Check if user was invited, if not, create default organization
- After creating organization: Also create OrganizationMember record with role='owner'

**File**: `app/Http/Controllers/Api/OrganizationMembersController.php` (accept invitation)
- When user accepts invitation: Do NOT create default organization
- User only joins the organization they were invited to

## Frontend Implementation

### Composables

#### 1. Create `useOrganizationMembers` composable
**File**: `app/resources/js/composables/useOrganizationMembers.ts`
- Functions:
  - `fetchMembers(orgId: string)`: Get all members (including owner)
  - `inviteMember(orgId: string, email: string)`: Send invitation (no role)
  - `updateMemberRole(orgId: string, memberId: string, role: string)`: Update role (only owner can do this)
  - `removeMember(orgId: string, memberId: string)`: Remove member (only owner can do this)
  - `fetchInvitations(orgId: string)`: Get pending invitations
  - `cancelInvitation(orgId: string, invitationId: string)`: Cancel invitation
  - `transferOwnership(orgId: string, newOwnerUserId: string)`: Transfer ownership to another member (only owner can do this)

#### 2. Create `useOrganizationPermissions` composable
**File**: `app/resources/js/composables/useOrganizationPermissions.ts`
- Functions:
  - `canManageSettings()`: Check if user can manage settings
  - `canInviteMembers()`: Check if user can invite
  - `canRunScans()`: Check if user can run scans
  - `canAddAwsAccounts()`: Check if user can add AWS accounts
  - `getUserRole()`: Get current user's role in organization

### Components

#### 1. Update `OrganizationSelector` component
**File**: `app/resources/js/Components/OrganizationSelector.vue`
- Add settings icon (cog) next to organization name in dropdown
- Link to `/organizations/{orgId}/settings`
- Only show if user has administrator or owner role

#### 2. Update `Organizations/Settings.vue` page
**File**: `app/resources/js/Pages/Organizations/Settings.vue`
- Add tabs: "General" and "Team"
- General tab: Existing organization name update
- Team tab: New team management interface

#### 3. Create `TeamManagement` component
**File**: `app/resources/js/Pages/Organizations/Components/TeamManagement.vue`
- Members list table with:
  - User name, email, role badge (or "No role assigned" if role is null)
  - Actions: 
    - Assign role (dropdown for users with no role) - **Only owner can do this**
    - Change role (dropdown for users with role) - **Only owner can do this**
    - Remove (for non-owners) - **Only owner can do this**
    - Transfer ownership (button for owner only, shows modal to select new owner)
  - Show "Owner" badge for organization owner
  - Highlight users with no role (pending role assignment)
- Invite member section:
  - Form: Email input only, "Invite" button
  - Message: "Role will be assigned after user accepts invitation"
  - Permission: Administrators and owner can invite
- Pending invitations section:
  - List of pending invitations with cancel option
  - Shows expiration time (24 hours)
  - Permission: Administrators and owner can view/cancel
- Transfer ownership section (owner only):
  - Button to transfer ownership
  - Modal to select new owner from member list
  - Confirmation required

#### 4. Create `InviteMemberModal` component
**File**: `app/resources/js/Pages/Organizations/Components/InviteMemberModal.vue`
- Form: Email input only (no role selection - roles assigned later)
- Validation and error handling
- Success notification on invite sent
- Shows message that role will be assigned after user accepts invitation
- Permission: Administrators and owner can invite

#### 6. Create `TransferOwnershipModal` component
**File**: `app/resources/js/Pages/Organizations/Components/TransferOwnershipModal.vue`
- Shows list of organization members (excluding current owner)
- Dropdown/select to choose new owner
- Confirmation message explaining ownership transfer
- Warning about what happens to current owner's role
- Permission: Only owner can access

#### 5. Create `AcceptInvitation` page
**File**: `app/resources/js/Pages/Organizations/AcceptInvitation.vue`
- Route: `/organizations/invitations/{token}/accept`
- Shows invitation details (organization name, inviter name)
- If user not authenticated: Shows "Sign up" or "Login" buttons
- If user authenticated but email doesn't match: Shows error
- If user authenticated and email matches: Shows "Accept Invitation" button
- After acceptance: User joins organization with no role, redirects to dashboard
- Shows message that role will be assigned by organization administrator

### TypeScript Interfaces

**File**: `app/resources/js/types/organization.ts`
```typescript
interface OrganizationMember {
  id: string;
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

interface OrganizationInvitation {
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
  organization: {
    id: string;
    name: string;
  };
}
```

### Routes

**File**: `app/routes/web.php`
- Add route for invitation acceptance: `GET /organizations/invitations/{token}/accept`

## Authorization Updates

### Update Existing Controllers

1. **OrganizationsController**
   - Update `update()`: Check administrator or owner permission
   - Update `destroy()`: Only owner can delete
   - Add `transferOwnership()`: Transfer ownership to another member

2. **AwsAccountsController**
   - Update `store()`: Check administrator or owner permission
   - Update `init()`: Check administrator or owner permission
   - Verify all queries use `organization_id` (not `user_id`)

3. **ScansController**
   - Update `store()`: Check administrator, owner, or auditor permission
   - Verify all queries use `organization_id` (not `user_id`)

4. **All controllers**
   - Add permission checks using `OrganizationPermission` service
   - Return 403 Forbidden if permission denied

## UI/UX Updates

### Settings Icon
- Add cog icon to OrganizationSelector dropdown next to organization name
- Only visible for administrators
- Links to settings page

### Settings Page Layout
- Tab navigation: "General" and "Team"
- General tab: Existing organization name form
- Team tab: Team management interface

### Team Management UI
- Members table with role badges
- Invite form at top
- Pending invitations section below members
- Clear visual distinction between owner and members
- Role change dropdown for each member (administrators only)

## Business Rules

1. **Organization Owner**:
   - Has "Owner" role (visible to users)
   - Has all Administrator permissions plus can delete organization and transfer ownership
   - Tracked via `organizations.user_id` AND `organization_members.role='owner'`
   - Ownership can be transferred to another team member
   - When ownership is transferred:
     - `organizations.user_id` is updated to new owner
     - Old owner's role in `organization_members` is updated (removed or changed to another role)
     - New owner's role in `organization_members` is set to 'owner'
   - Can delete organization
   - Shown in member list with "Owner" badge
   - Only owner can transfer ownership or delete organization

2. **Member Roles**:
   - Administrator: Full access (invite, manage settings, run scans, add accounts)
   - Auditor: Can run scans, view reports/insights
   - Viewer: Can only view reports/insights

3. **Invitations**:
   - Expire after 24 hours (fixed)
   - One invitation per email per organization (cannot invite same email twice if pending)
   - If user already exists and is member, show error
   - If user exists but not member, invitation creates member record with role=NULL
   - Invitations do NOT include role - roles are assigned after acceptance
   - If user doesn't have account, they must sign up first, then accept invitation
   - After acceptance, notification email sent to inviter (owner/administrator)

4. **Member Management**:
   - Users can join organization with no role (role=NULL)
   - Users with no role cannot perform any actions (view-only access or no access - TBD)
   - **Only Owner** can assign/change roles to members (not administrators)
   - Cannot remove owner role (must transfer ownership first)
   - Cannot remove last administrator (must have at least one administrator besides owner)
   - Cannot remove self if only administrator (besides owner)
   - Owner cannot be removed (must transfer ownership first)
   - Users can only have one role per organization (cannot have multiple roles)
   - When assigning 'owner' role to a member, automatically transfers ownership

5. **Access Control**:
   - Users can only see organizations they own or are members of
   - Organization context middleware checks both ownership and membership
   - All API endpoints check permissions before allowing actions
   - Users with role=NULL (no role assigned) have minimal/no permissions (exact permissions TBD - may be view-only or no access)
   - Owner has all Administrator permissions plus delete organization and transfer ownership permissions

6. **Organization Creation**:
   - **New users (not invited)**: Automatically get default organization created on signup
   - **Invited users**: Do NOT get default organization - only added to the organization they were invited to
   - Users can belong to multiple organizations (created by them or via invites)

7. **Organization ID References**:
   - All features (AWS accounts, Scans, future features) reference `organization_id`, NOT `user_id`
   - This ensures ownership can be transferred without breaking relationships
   - Verify all existing code uses `organization_id` for relationships

## Testing Considerations

1. **Unit Tests**:
   - OrganizationPermission service methods
   - Organization model relationships
   - Invitation expiration logic

2. **Feature Tests**:
   - Invite member flow
   - Accept invitation flow
   - Role-based access control
   - Permission checks on all endpoints

3. **E2E Tests**:
   - Complete invite/accept flow
   - Role change flow
   - Permission enforcement in UI

## Migration Strategy

1. **Create new tables** (organization_members, organization_invitations)
2. **Migrate existing organizations**:
   - For each existing organization, create OrganizationMember record with role='owner' for the user_id
   - This ensures owner role is tracked in both places (organizations.user_id and organization_members)
3. **Verify organization_id usage**:
   - Audit all code to ensure AWS accounts, Scans, and other features use `organization_id` (not `user_id`)
   - Current state: AWS accounts and Scans already use `organization_id` ✓
   - Verify controllers and queries use `organization_id` for filtering
4. **Update middleware** to support both ownership and membership
5. **Deploy backend changes**
6. **Deploy frontend changes**
7. **Backward compatible**: Existing organizations continue to work, ownership tracked in both places

## Code Audit: Organization ID References

### Verify These Files Use `organization_id` (Not `user_id`):

**Models** (already verified):
- ✅ `AwsAccount` - uses `organization_id`
- ✅ `Scan` - uses `organization_id`

**Controllers** (need to verify):
- `AwsAccountsController` - verify all queries filter by `organization_id`
- `ScansController` - verify all queries filter by `organization_id`
- `OrganizationsController` - verify queries use `organization_id` for relationships

**Middleware**:
- `SetOrganizationContext` - verify it sets organization context correctly

**Database Migrations** (already verified):
- ✅ `aws_accounts` table - has `organization_id` foreign key
- ✅ `scans` table - has `organization_id` foreign key

**Action Items**:
1. Review all controllers that query AWS accounts or Scans
2. Ensure they filter by `organization_id` from request context (not `user_id`)
3. Update any queries that incorrectly use `user_id` to use `organization_id` instead

## Files to Create/Modify

### Backend
- `app/database/migrations/YYYY_MM_DD_create_organization_members_table.php`
- `app/database/migrations/YYYY_MM_DD_create_organization_invitations_table.php`
- `app/database/migrations/YYYY_MM_DD_migrate_existing_organization_owners.php` (data migration to create owner member records)
- `app/Models/OrganizationMember.php`
- `app/Models/OrganizationInvitation.php`
- `app/Services/OrganizationPermission.php`
- `app/Http/Controllers/Api/OrganizationMembersController.php`
- `app/Http/Requests/InviteMemberRequest.php`
- `app/Http/Requests/UpdateMemberRoleRequest.php`
- `app/Http/Requests/TransferOwnershipRequest.php`
- `app/Notifications/OrganizationInvitation.php` (to invitee)
- `app/Notifications/MemberJoinedNotification.php` (to inviter)
- `app/Models/Organization.php` (update - add transferOwnership method)
- `app/Models/User.php` (update - add memberOrganizations relationship)
- `app/Http/Middleware/SetOrganizationContext.php` (update - check membership)
- `app/Http/Controllers/Auth/FirebaseAuthController.php` (update - conditional org creation)
- `app/Http/Controllers/Auth/RegisteredUserController.php` (update - conditional org creation if exists)
- `app/routes/api.php` (add routes)

### Frontend
- `app/resources/js/composables/useOrganizationMembers.ts`
- `app/resources/js/composables/useOrganizationPermissions.ts`
- `app/resources/js/types/organization.ts`
- `app/resources/js/Pages/Organizations/Components/TeamManagement.vue`
- `app/resources/js/Pages/Organizations/Components/InviteMemberModal.vue`
- `app/resources/js/Pages/Organizations/Components/TransferOwnershipModal.vue`
- `app/resources/js/Pages/Organizations/AcceptInvitation.vue`
- `app/resources/js/Components/OrganizationSelector.vue` (update)
- `app/resources/js/Pages/Organizations/Settings.vue` (update)
- `app/routes/web.php` (add invitation route)

## Success Criteria

1. Users can access organization settings via cog icon (administrators and owner only)
2. Administrators/Owner can invite team members via email (no role in invitation)
3. Invited users receive email and can accept invitation (sign up first if needed)
4. **Invited users do NOT get a default organization** - only added to the organization they were invited to
5. **New users (not invited) automatically get a default organization** created on signup
6. After acceptance, user joins organization with no role (role=NULL)
7. Inviter receives notification email when user accepts invitation
8. Members are listed in team management showing their roles or "No role assigned"
9. Organization owner is shown with "Owner" badge
10. **Only Owner can assign roles** to members who have no role (not administrators)
11. **Only Owner can change member roles** (not administrators)
12. **Only Owner can remove members** (with business rule constraints)
13. **Only Owner can transfer ownership** to another team member
14. **Only Owner can delete organization**
15. Role-based permissions are enforced on all API endpoints
16. UI shows/hides features based on user role
17. Invitations expire after 24 hours
18. Users can belong to multiple organizations (created by them or via invites)
19. All features reference `organization_id` (not `user_id`) to support ownership transfer
20. All existing functionality continues to work with new permission system
