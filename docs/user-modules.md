IMPORTANT ARCHITECTURE REQUIREMENT — USER-BASED ACCESS CONTROL

The entire application must be user/module based.

Every module, record, project, company, website, article, transaction, activity, report, notification, etc. must respect the currently authenticated user's access permissions.

There must be one global exception:

SUPER ADMIN

The Super Admin has unrestricted access to the entire system and must be able to see, create, update, delete, manage, and configure everything across all users, companies, projects, and modules.

Do NOT implement this only at the frontend/UI level.

The access-control rules MUST be enforced at the backend, database query/service/policy level, and API level.

==================================================
1. GLOBAL ACCESS MODEL
==================================================

The system should follow this general rule:

SUPER ADMIN
    ↓
Full access to everything

NORMAL USER
    ↓
Only their authorized data
    ↓
Only their authorized modules/actions
    ↓
Only their authorized companies/projects/websites/articles/etc.

The exact existing user/role structure must be inspected first.

Do NOT create a duplicate users/authentication system.

Reuse the existing users table and authentication system.

==================================================
2. SUPER ADMIN
==================================================

Identify the existing Super Admin role/user mechanism.

If the application already has:

- role
- is_admin
- is_super_admin
- permissions
- roles/permissions package
- authorization policies

reuse the existing mechanism.

Do NOT create another authentication or role system unnecessarily.

Super Admin must be able to:

- See all users
- See all companies
- See all projects
- See all websites
- See all articles
- See all expenses
- See all credits
- See all transactions
- See all reports
- See all activity logs
- See all PostHog-related activity/recordings allowed by the system
- See all notifications
- Manage all settings
- Manage user access
- Manage company/user mappings
- Manage projects
- Manage articles
- Manage AI article generation
- Access all admin modules

Super Admin must NOT be restricted by normal user ownership filters.

==================================================
3. NORMAL USER
==================================================

A normal user must only see records they are authorized to access.

Do NOT assume that a user can see all records simply because they are logged in.

Every query must apply the appropriate access scope.

For example:

User A should NOT automatically see:

- User B's projects
- User B's articles
- User B's companies
- User B's websites
- User B's activity
- User B's reports
- User B's transactions

unless the existing business rules explicitly grant access.

==================================================
4. USER → COMPANY → PROJECT → DATA
==================================================

Use the application's existing relationships.

The intended access hierarchy is:

User
 ↓
Companies/User-Company Access
 ↓
Projects
 ↓
Websites
 ↓
Articles
 ↓
Expenses / Credits / Transactions
 ↓
Reports / Activity / Notifications

A user should only access data belonging to companies/projects they are authorized to access.

However:

SUPER ADMIN bypasses these restrictions and can access everything.

==================================================
5. USER-BASED QUERY FILTERING
==================================================

Every module must apply authorization before returning data.

Examples:

Projects:

Normal User:
    return only authorized projects

Super Admin:
    return all projects

Articles:

Normal User:
    return only articles belonging to authorized projects

Super Admin:
    return all articles

Companies:

Normal User:
    return only companies assigned/authorized to that user

Super Admin:
    return all companies

Websites:

Normal User:
    return only websites belonging to authorized projects/companies/users

Super Admin:
    return all websites

Reports:

Normal User:
    return reports generated from authorized data only

Super Admin:
    can generate reports across the entire system.

==================================================
6. BACKEND ENFORCEMENT
==================================================

This is CRITICAL.

Do NOT implement access control only by hiding menu items or buttons.

For every module verify:

- List
- View
- Create
- Edit
- Update
- Delete
- Export
- Import
- Download
- API
- AJAX
- Search
- Filter
- Reports
- Bulk actions
- Notifications
- Background jobs
- Queue jobs
- Scheduled jobs
- Direct URL access

A user must not be able to bypass permissions by manually changing:

/projects/123

to:

/projects/456

or by modifying:

project_id
user_id
company_id

in an API request.

The backend must verify authorization every time.

==================================================
7. POLICY / SERVICE / QUERY ARCHITECTURE
==================================================

Use the application's existing authorization architecture if available.

If Laravel Policies/Gates are already used, extend them.

If there is an existing permission service, reuse it.

Do not duplicate authorization logic in every controller.

Use centralized authorization wherever practical.

For example:

UserAccessService
Policy
Gate
Query Scope

or the equivalent existing architecture.

The important requirement is:

Authorization should be centralized, reusable, and difficult to bypass.

==================================================
8. SUPER ADMIN BYPASS
==================================================

Every authorization layer must correctly recognize Super Admin.

Conceptually:

if ($user->isSuperAdmin()) {
    return true;
}

But use the application's actual existing role implementation.

Super Admin should bypass normal ownership/company/project restrictions.

Do NOT duplicate Super Admin checks in hundreds of controllers if a centralized policy/gate mechanism can handle this.

==================================================
9. USER ACTIVITY LOG
==================================================

The existing User Activity Log must also follow user-based access.

Normal User:

Can only see their own activity unless the existing business rules explicitly allow access to shared activity.

Super Admin:

Can see the complete activity log across all users.

Activity log filters should support:

- User
- Activity Type
- Company
- Project
- Entity
- Date
- Search

Super Admin can filter across the entire system.

Normal users must not be able to access another user's activity through direct URL/API manipulation.

==================================================
10. POSTHOG RECORDINGS
==================================================

The existing PostHog recording functionality must also follow user access rules.

Normal user:

Only recordings associated with their authorized application activity/user identity should be visible.

Super Admin:

Can see all valid matched recordings.

IMPORTANT:

Continue following the existing requirement:

Only show PostHog recordings that can be matched to a known application user/activity.

Never show anonymous/unmatched recordings.

Do not weaken the existing PostHog matching requirement.

==================================================
11. AI ARTICLE GENERATION
==================================================

The new AI article-generation feature must also use the same global user-based access system.

Current requirement:

Only:

admin@posticy.com

is eligible for automatic AI article generation.

But this eligibility must be evaluated through the application's user/role/access architecture.

Do NOT create a completely separate authorization system just for AI articles.

For example:

AI Auto Article
    ↓
Check authenticated/project owner
    ↓
Check user eligibility
    ↓
Check role/permission
    ↓
Check Super Admin
    ↓
Generate if authorized

SUPER ADMIN should have access to manage/test/trigger the AI article functionality regardless of normal-user restrictions.

For now, automatic scheduled generation should remain limited according to the previously defined requirement, but the architecture must support enabling it for all users later.

==================================================
12. PROJECT OWNERSHIP
==================================================

Every project must have a clear relationship to its authorized user/company.

Inspect the existing database first.

Do not assume the relationship is:

project.user_id

if the application currently uses another structure.

If a project belongs to a company and users are mapped to companies, respect that existing hierarchy.

The final authorization should determine:

Can this user access this project?

If Super Admin:

YES.

Otherwise:

Only if the user has legitimate access through the application's configured relationships.

==================================================
13. COMPANY ACCESS
==================================================

If the application uses user-company mapping:

User A
    → Company A
    → Company B

User B
    → Company B

Then:

User A can access Company A + Company B data.

User B can access only Company B data.

Super Admin:

Can access Company A + Company B + every other company.

Do not rely only on frontend company selectors.

Backend queries must enforce this.

==================================================
14. ARTICLE ACCESS
==================================================

Articles must inherit the access rules of their associated project/company/user.

Normal User:

Can only access authorized articles.

Super Admin:

Can access all articles.

Direct article URLs must also be protected.

Example:

/articles/123

Changing the ID to:

/articles/456

must not expose an unauthorized article.

==================================================
15. WEBSITE ACCESS
==================================================

Apply the same rules to websites.

Normal users can only access websites they are authorized to manage/view.

Super Admin can access all websites.

Apply this to:

- Website list
- Website details
- Add website
- Edit website
- Delete website
- Verification
- Hunter integration
- Website metrics
- Exports
- API endpoints
- Bulk operations

==================================================
16. REPORTS
==================================================

Reports must NEVER expose unauthorized data.

Normal User:

Reports are generated only from data they are authorized to access.

Super Admin:

Can generate:

- User reports
- Company reports
- Project reports
- Website reports
- Article reports
- Financial reports
- Activity reports
- System-wide reports

==================================================
17. NOTIFICATIONS
==================================================

Notifications must respect user access.

A user must never receive notification data belonging to another unauthorized user/company/project.

Super Admin can manage system-wide notifications where applicable.

==================================================
18. API / AJAX SECURITY
==================================================

Audit all existing API and AJAX endpoints.

Do not assume that protecting the web page automatically protects the API.

Every endpoint must verify:

1. Authentication
2. User role/permission
3. Resource ownership/access
4. Company/project authorization
5. Super Admin bypass where appropriate

==================================================
19. BACKGROUND JOBS
==================================================

Background jobs must also respect authorization context.

This is especially important for:

- AI article generation
- Notifications
- Reports
- PostHog processing
- Email
- WhatsApp
- Scheduled tasks
- Imports/exports

Do not allow a job to accidentally process another user's data.

For system-wide Super Admin operations, explicitly allow system-wide processing.

==================================================
20. DATABASE QUERIES
==================================================

Audit existing queries for missing authorization filters.

Look for patterns such as:

Project::all()
Article::all()
Website::all()
UserActivity::all()

and determine whether they should be scoped based on the current user.

Do not blindly replace every query.

Super Admin must continue to receive the complete dataset.

Use reusable scopes/services/policies where appropriate.

==================================================
21. FRONTEND
==================================================

The frontend should also reflect permissions.

Hide modules/actions the current user cannot access.

For example:

Normal User:
- Hide unauthorized modules/actions.

Super Admin:
- Show everything they are allowed to manage.

But remember:

Frontend visibility is ONLY for UX.

Backend authorization remains mandatory.

==================================================
22. USER MODULE
==================================================

The User Management module itself must be permission-controlled.

Super Admin can:

- View all users
- Create users
- Edit users
- Delete/deactivate users
- Assign roles
- Assign companies
- Assign permissions
- View user activity
- Manage user access

Normal users should only see the user-management functionality explicitly allowed by their permissions.

Do not allow a normal user to escalate their own permissions.

==================================================
23. GLOBAL AUTHORIZATION RULE
==================================================

Implement one consistent rule across the entire application:

IF current user is SUPER ADMIN:
    allow unrestricted system access.

ELSE:
    apply normal user permissions + company/project/resource access rules.

This must be the foundation for every module.

==================================================
24. DO NOT BREAK EXISTING FUNCTIONALITY
==================================================

Before changing authorization:

1. Inspect existing roles.
2. Inspect existing permissions.
3. Inspect existing user relationships.
4. Inspect existing company relationships.
5. Inspect existing project relationships.
6. Inspect existing policies/gates/middleware.
7. Inspect existing controllers/services/repositories.
8. Inspect existing API authorization.
9. Inspect existing frontend permission checks.

Then integrate with the existing architecture.

Do NOT replace a working authorization system unnecessarily.

==================================================
25. TESTING REQUIREMENTS
==================================================

Add tests for at least:

1. Super Admin can see all users.

2. Super Admin can see all companies.

3. Super Admin can see all projects.

4. Super Admin can see all articles.

5. Super Admin can see all websites.

6. Super Admin can see all activity logs.

7. Normal User only sees authorized companies.

8. Normal User only sees authorized projects.

9. Normal User cannot access another user's project by changing the URL ID.

10. Normal User cannot access another user's article directly.

11. Normal User cannot access unauthorized website records.

12. Normal User cannot access another user's activity log.

13. Normal User cannot bypass restrictions through API/AJAX.

14. Super Admin can access restricted records.

15. AI article generation respects user eligibility.

16. AI article generation never exposes another user's project.

==================================================
26. IMPLEMENTATION PROCESS
==================================================

Do NOT start modifying files immediately.

First inspect the codebase and provide me with:

1. Current authentication system
2. Current user/role structure
3. Current permission system
4. Current company/user relationship
5. Current project/user/company relationship
6. Current article/project relationship
7. Existing Policies/Gates/Middleware
8. Existing API authorization
9. Existing activity-log authorization
10. Existing PostHog authorization
11. Existing AI article architecture

Then explain:

- What already exists
- What can be reused
- What needs modification
- What minimum migrations are required
- How Super Admin will bypass restrictions
- How normal users will be scoped

Only after this inspection should you implement the changes.

==================================================
FINAL NON-NEGOTIABLE RULE
==================================================

The entire application must be USER/ROLE/ACCESS based.

SUPER ADMIN = FULL SYSTEM ACCESS.

NORMAL USER = ONLY AUTHORIZED DATA + AUTHORIZED MODULES/ACTIONS.

This rule must apply consistently to:

Users
Companies
Projects
Websites
Articles
Expenses
Credits
Transactions
Reports
Activity Logs
PostHog
Notifications
AI Article Generation
APIs
AJAX
Exports
Imports
Background Jobs
Scheduled Jobs
Settings

Do not implement this as a collection of isolated checks.

Build/reuse a centralized authorization architecture so the same access rules are consistently applied throughout the application.

Do not break existing functionality or change the existing desktop UI unnecessarily.