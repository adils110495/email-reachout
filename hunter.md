Create **four new modules** in the application based on the modules marked in the provided screenshot.

The four modules to create are:

1. **Dashboard**
2. **Finder**
3. **Verifier**
4. **Bulks**

Use the provided screenshot as the visual and structural reference.

### 1. Dashboard

Create a complete Dashboard module similar to the screenshot.

* Dashboard should be the main landing page.
* Include the same type of clean left sidebar navigation.
* Show useful overview cards/sections for the application's data and activity.
* Keep the layout clean, professional, and consistent with the reference UI.
* Dashboard should dynamically display real application data rather than static dummy content.

### 2. Finder

Create a complete **Finder** module.

The Finder should allow users to search/find leads and email addresses.

Include:

* Search interface
* Search/filter fields
* Company/domain search
* Person/contact search
* Lead results
* Name
* Job title
* Company
* Domain
* Email
* Location
* Other relevant lead information
* Pagination
* Loading states
* Empty states
* Error states

All results should be dynamically loaded from the application's existing backend/API/database.

### 3. Verifier

Create a complete **Verifier** module.

The module should allow users to verify email addresses.

Include:

* Email input
* Single email verification
* Verification result
* Valid/Invalid/Risky/Unknown status
* Verification details
* Verification history where applicable
* Proper loading and error states

If bulk verification is supported by the existing backend, structure the module so it can support multiple email verification as well.

### 4. Bulks

Create a complete **Bulks** module for bulk operations.

Include:

* Bulk upload/import
* CSV file upload
* Bulk email verification/finding where applicable
* Processing status
* Total records
* Processed records
* Successful records
* Failed records
* Progress indicator
* Results table
* Download/export results
* Bulk operation history

The module should be fully dynamic and connected to the existing backend/database.

### Common UI Requirements

Use the screenshot as the design reference for the overall application UI.

Maintain:

* Left sidebar navigation
* Icons
* Typography
* Spacing
* Borders
* Buttons
* Active navigation state
* Page containers
* Tables
* Cards
* Form controls
* Responsive behavior

The four modules should feel like **one unified application**, not four separately designed pages.

### Navigation

Add these four modules to the sidebar:

**Dashboard**
**Finder**
**Verifier**
**Bulks**

Make the active module visually highlighted exactly like the reference.

Create proper dynamic routes for each module, for example:

* `/dashboard`
* `/finder`
* `/verifier`
* `/bulks`

Use the project's existing routing architecture instead of hardcoding navigation.

### Important

Before implementing, inspect the existing project structure, backend, database, authentication, API integrations, and current UI components.

**Do not create fake/static functionality.** Connect each module to the existing application architecture and make the data/functionality dynamic.

Also ensure the implementation is:

* Production-ready
* Responsive
* Consistent with the existing theme
* Optimized for performance
* Secure
* Properly validated
* Free from layout-breaking issues
* Compatible with the existing modules and navigation

Use the uploaded screenshot as the primary visual reference for the sidebar and module structure.
