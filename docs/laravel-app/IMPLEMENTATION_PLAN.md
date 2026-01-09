# Implementation Plan for TODO.md

This document outlines the plan to implement all tasks from `docs/laravel-app/TODO.md`.

## Overview

The plan is organized into phases with clear priorities and dependencies. Each phase can be implemented incrementally.

---

## Phase 1: Documentation Cleanup & Reorganization (Priority: High)

### 1.1 Move Documentation to `docs/` Folder

**Tasks:**
- [ ] Identify all documentation files in root and `app/` folder
- [ ] Move root-level docs to appropriate `docs/` subdirectories
- [ ] Move `app/` docs to `docs/laravel-app/` or appropriate subdirectory
- [ ] Update all internal links and references
- [ ] Create index/README in `docs/` folder for navigation

**Files to Move:**
- Root level:
  - `ARCHITECTURE.md` → `docs/architecture.md`
  - `FEATURES_SPEC.md` → `docs/features/features-spec.md`
  - `ONBOARDING_FLOW.md` → `docs/features/onboarding-flow.md`
  - `PLANNING.md` → `docs/planning.md`
  - `QUICK_START.md` → `docs/quick-start.md`
  - `WIREFRAMES.md` → `docs/design/wireframes.md` (or remove if outdated)
  - `WIREFRAMES_SUMMARY.md` → `docs/design/wireframes-summary.md` (or remove if outdated)
  - `WIREFRAMES_ORGANIZATIONS.md` → `docs/design/wireframes-organizations.md` (or remove if outdated)
  - `wireframes.html` → `docs/design/wireframes.html` (or remove if outdated)

- From `app/` folder:
  - All `.md` files → `docs/laravel-app/`
  - Keep only essential reference docs in `app/` with links to `docs/`

**Structure:**
```
docs/
├── README.md (index)
├── architecture.md
├── planning.md
├── quick-start.md
├── laravel-app/
│   ├── README.md
│   ├── TODO.md
│   ├── setup.md
│   ├── database/
│   ├── api/
│   └── ...
├── features/
│   ├── features-spec.md
│   ├── onboarding-flow.md
│   └── ...
├── design/
│   ├── wireframes.md (if keeping)
│   └── ...
└── processes/
    └── (existing)
```

### 1.2 Remove Old Wireframes

**Tasks:**
- [ ] Review wireframe files to determine if they're still relevant
- [ ] If outdated, remove:
  - `WIREFRAMES.md`
  - `WIREFRAMES_SUMMARY.md`
  - `WIREFRAMES_ORGANIZATIONS.md`
  - `wireframes.html`
- [ ] If keeping, move to `docs/design/` and update references
- [ ] Update any documentation that references these files

### 1.3 Create Reference Docs in `app/` Folder

**Tasks:**
- [ ] Create `app/README.md` with:
  - Quick start guide
  - Links to detailed docs in `docs/laravel-app/`
  - Essential setup instructions
- [ ] Create `app/DOCS.md` (or similar) that links to:
  - Architecture: `docs/architecture.md`
  - Setup: `docs/laravel-app/setup.md`
  - API: `docs/laravel-app/api/`
  - Features: `docs/features/`
- [ ] Keep only essential reference docs in `app/` (e.g., `ENV_SETUP.md`, `DATABASE_CONFIG.md`)

---

## Phase 2: Update Architecture Documentation (Priority: High)

### 2.1 Update Architecture Documentation

**Tasks:**
- [ ] Review `ARCHITECTURE.md` (or `docs/architecture.md` after move)
- [ ] Remove all references to:
  - `backend/` folder (NestJS/Serverless)
  - `frontend/` folder (Nuxt 3)
  - Separate frontend/backend architecture
- [ ] Update to reflect:
  - Laravel monolith with Vue 3 (Inertia.js)
  - Single `app/` folder structure
  - Laravel backend with Vue frontend in same codebase
- [ ] Document:
  - Project structure (Laravel + Vue)
  - Technology stack (Laravel 11, Vue 3, Inertia, Tailwind, etc.)
  - How frontend and backend interact (Inertia.js)
  - API structure (if any separate API routes)

### 2.2 Update README Files

**Tasks:**
- [ ] Update root `README.md`:
  - Remove references to `frontend/` and `backend/` folders
  - Update project structure section
  - Update setup instructions for Laravel app
  - Update environment variables section
  - Update links to documentation
- [ ] Update `app/README.md`:
  - Laravel-specific setup
  - Vue/Inertia setup
  - Development workflow
  - Links to detailed docs

### 2.3 Update Progress Documentation

**Tasks:**
- [ ] Update `docs/PROGRESS.md`:
  - Remove references to old architecture
  - Update feature status to reflect Laravel app
  - Update technology stack references
  - Update practices compliance status
  - Remove outdated features/status

---

## Phase 3: Practices Integration (Priority: Medium)

### 3.1 Review Practices Documents

**Tasks:**
- [ ] Review all practices documents in `docs/processes/` and `docs/practices/`
- [ ] Identify which practices apply to Laravel monolith
- [ ] Note any practices that don't apply (e.g., separate frontend/backend concerns)
- [ ] Document any Laravel-specific adaptations needed

### 3.2 Integrate Practices into Development Process

**Tasks:**
- [ ] Update `docs/processes/feature-development.md`:
  - Ensure it works for Laravel monolith
  - Add Laravel-specific steps if needed
  - Update examples to use Laravel/Vue
- [ ] Update `docs/processes/practices-checklist.md`:
  - Remove frontend/backend separation concerns
  - Add Laravel-specific checks
  - Ensure monolith architecture is maintained
- [ ] Create `docs/laravel-app/PRACTICES.md`:
  - Summary of practices that apply
  - Laravel-specific adaptations
  - Link to full practices docs

### 3.3 Document Architecture Decisions

**Tasks:**
- [ ] Document decision to keep monolith (Laravel + Vue together)
- [ ] Add note that we will NOT split into separate apps
- [ ] Document when to consider changes (and when not to)
- [ ] Add to architecture documentation

---

## Phase 4: In-App Notifications System (Priority: Medium)

### 4.1 Design Notification System

**Tasks:**
- [ ] Design notification component structure
- [ ] Define notification types (info, warning, error, success)
- [ ] Define notification placement (top of screen)
- [ ] Design color schemes for each type
- [ ] Design animation/transition behavior
- [ ] Design auto-dismiss behavior

### 4.2 Implement Notification Component

**Tasks:**
- [ ] Create Vue notification component (`resources/js/Components/Notification.vue` or similar)
- [ ] Create notification store/composable for state management
- [ ] Add notification types with color schemes:
  - Info: Blue background
  - Warning: Yellow/Orange background
  - Error: Red background
  - Success: Green background
- [ ] Implement positioning (top of screen, centered or right-aligned)
- [ ] Implement animations (slide in/out)
- [ ] Implement auto-dismiss (configurable timeout)

### 4.3 Integrate with Layout

**Tasks:**
- [ ] Add notification component to `SidebarAppLayout.vue`
- [ ] Ensure notifications appear above all content
- [ ] Test with different notification types
- [ ] Test multiple notifications (stacking behavior)

### 4.4 Add Notification Helpers

**Tasks:**
- [ ] Create helper functions/composables:
  - `showNotification(message, type)`
  - `showSuccess(message)`
  - `showError(message)`
  - `showWarning(message)`
  - `showInfo(message)`
- [ ] Integrate with Inertia flash messages
- [ ] Add to global error handling

---

## Phase 5: User Signup Flow Improvements (Priority: Medium)

### 5.1 Email Verification Success Message

**Tasks:**
- [ ] Update `VerifyEmailController` to set flash message on successful verification
- [ ] Add success notification when user verifies email
- [ ] Show notification at top of screen after redirect to dashboard
- [ ] Message: "Your email has been verified successfully!"

### 5.2 Registration Flow Messages

**Tasks:**
- [ ] Add notification after registration:
  - "Registration successful! Please check your email to verify your account."
- [ ] Add notification when verification email is sent:
  - "Verification email sent! Please check your inbox."
- [ ] Update `RegisteredUserController` to set appropriate messages

### 5.3 OAuth Registration Messages

**Tasks:**
- [ ] Add success notification after OAuth signup:
  - "Welcome! Your account has been created and verified."
- [ ] Update `FirebaseAuthController` to set flash message

### 5.4 Update User Stories

**Tasks:**
- [ ] Add user stories to `FEATURES_SPEC.md`:
  - "As a user, when I sign up and receive a verification email, I need to see a message at the top of the screen confirming the email was sent."
  - "As a user, when I verify my email, I need to see a success message at the top of the screen."
  - "As a user, when I sign up via OAuth, I need to see a welcome message."
- [ ] Document expected behavior
- [ ] Add acceptance criteria

---

## Phase 6: Feature Documentation Updates (Priority: Low)

### 6.1 Update FEATURES_SPEC.md

**Tasks:**
- [ ] Review all features in `FEATURES_SPEC.md`
- [ ] Update any references to old architecture
- [ ] Ensure all features reflect Laravel app structure
- [ ] Add any missing features from TODO
- [ ] Ensure user stories follow template

### 6.2 Update User Stories

**Tasks:**
- [ ] Convert any incomplete user stories to full format
- [ ] Add user stories from TODO.md:
  - Email verification messages
  - OAuth welcome messages
- [ ] Ensure all user stories have acceptance criteria

---

## Implementation Order

### Week 1: Documentation Cleanup
1. Phase 1.1: Move documentation files
2. Phase 1.2: Remove old wireframes
3. Phase 1.3: Create reference docs

### Week 2: Architecture Updates
4. Phase 2.1: Update architecture docs
5. Phase 2.2: Update README files
6. Phase 2.3: Update progress docs

### Week 3: Practices & Features
7. Phase 3: Practices integration
8. Phase 4: Notifications system
9. Phase 5: User signup flow improvements

### Week 4: Final Updates
10. Phase 6: Feature documentation updates

---

## Dependencies

- **Phase 1** must be completed before Phase 2 (docs need to be in right place)
- **Phase 4** (Notifications) should be completed before Phase 5 (needed for messages)
- **Phase 3** can be done in parallel with other phases

---

## Success Criteria

- [ ] All documentation is organized in `docs/` folder
- [ ] No references to old `backend/` or `frontend/` folders
- [ ] Architecture docs reflect Laravel monolith
- [ ] Practices are integrated into development process
- [ ] Notification system is implemented and working
- [ ] User signup flow shows appropriate messages
- [ ] All user stories are documented in FEATURES_SPEC.md

---

## Notes

- Keep monolith architecture - do NOT split Laravel and Vue
- All documentation should link to `docs/` folder
- Keep `app/` folder clean with only essential reference docs
- Follow existing practices where applicable
- Update documentation as features are implemented

