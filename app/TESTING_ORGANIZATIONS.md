# Testing Organizations Feature

## Prerequisites

1. **Servers are automatically started by Playwright:**
   - Laravel server (`php artisan serve`) on port 8000
   - Vite dev server (`npm run dev`) on port 5173
   - Both are required for the Laravel + Vue application
   - If servers are already running, Playwright will reuse them

2. **Ensure database is migrated:**
   ```bash
   php artisan migrate
   ```

3. **Seed test user for automated tests:**
   ```bash
   php artisan db:seed --class=TestUserSeeder
   ```
   - Email: `test@auditaws.cloud`
   - Password: `password`

## Test Checklist

### 1. Organizations List Page

**URL:** `http://localhost:8000/organizations`

**Expected Behavior:**
- [ ] Page loads without errors
- [ ] Shows default organization (created on signup)
- [ ] Organization card displays:
  - [ ] Organization name
  - [ ] "Default" badge (if default)
  - [ ] AWS accounts count (should be 0 initially)
  - [ ] Created date
  - [ ] Actions menu (three dots icon)
  - [ ] "Switch to this organization" button (if not current)
  - [ ] "Current organization" text (if current)
- [ ] "Add Organization" button is visible in header
- [ ] Sidebar shows "Organizations" link with active state

**Test Steps:**
1. Navigate to `/organizations`
2. Verify default organization is displayed
3. Check that all UI elements render correctly

---

### 2. Create Organization

**Test Steps:**
1. Click "Add Organization" button
2. Modal should open
3. Enter organization name (e.g., "Test Organization")
4. Click "Create"

**Expected Behavior:**
- [ ] Modal opens correctly
- [ ] Form validation works (try empty name)
- [ ] Success notification appears: "Organization 'Test Organization' created successfully"
- [ ] New organization appears in the list
- [ ] Automatically switches to new organization
- [ ] Modal closes after creation

**Edge Cases:**
- [ ] Try creating with empty name (should show validation error)
- [ ] Try creating with very long name (should work or show validation)

---

### 3. Switch Organization

**Test Steps:**
1. Create at least 2 organizations
2. Click "Switch to this organization" on a different org

**Expected Behavior:**
- [ ] Success notification: "Switched to [Organization Name]"
- [ ] Page reloads
- [ ] Selected organization shows "Current organization" text
- [ ] Other organizations show "Switch to this organization" button
- [ ] Organization context persists after page reload

---

### 4. Organization Settings

**URL:** `http://localhost:8000/organizations/{orgId}/settings`

**Test Steps:**
1. Click actions menu (three dots) on an organization
2. Click "Settings"
3. Edit organization name
4. Click "Save changes"

**Expected Behavior:**
- [ ] Settings page loads
- [ ] Current organization name is pre-filled
- [ ] Form validation works
- [ ] Success notification: "Organization updated successfully"
- [ ] Name updates in the list
- [ ] "Cancel" button returns to list

**Test Edit:**
- [ ] Change name to "Updated Organization"
- [ ] Save and verify change persists

---

### 5. Delete Organization

**Test Steps:**
1. Create a test organization (without AWS accounts)
2. Go to Settings
3. Scroll to "Danger Zone"
4. Click "Delete" button
5. Confirm deletion in modal

**Expected Behavior:**
- [ ] Delete button is visible (only if no AWS accounts)
- [ ] Confirmation modal appears
- [ ] Modal shows organization name
- [ ] Success notification: "Organization deleted successfully"
- [ ] Redirects to organizations list
- [ ] Deleted organization no longer appears

**Protection Rules:**
- [ ] Default organization: Delete button should NOT be visible
- [ ] Organization with AWS accounts: Warning message should appear
- [ ] Last organization: Should not be deletable (test by trying to delete when only 1 org exists)

---

### 6. Error Handling

**Test Scenarios:**
- [ ] Network error: Disconnect internet, try to create organization (should show error)
- [ ] Invalid organization ID: Try accessing `/organizations/invalid-id/settings` (should show 404)
- [ ] Unauthorized access: Try accessing another user's organization (should be blocked by backend)

---

### 7. Responsive Design

**Test on different screen sizes:**
- [ ] Desktop (1920x1080): 3-column grid
- [ ] Tablet (768px): 2-column grid
- [ ] Mobile (375px): 1-column grid
- [ ] Sidebar collapses on mobile
- [ ] Modals are responsive

---

### 8. Dark Mode

**Test in dark mode:**
- [ ] All text is readable
- [ ] Cards have proper contrast
- [ ] Buttons are visible
- [ ] Modals work correctly

---

## API Endpoints to Verify

Test these endpoints directly (using browser DevTools Network tab or Postman):

1. **GET /api/organizations**
   - Should return list of user's organizations
   - Requires authentication

2. **POST /api/organizations**
   - Body: `{ "name": "Test Org" }`
   - Should create new organization
   - Should return 201 with organization data

3. **GET /api/organizations/{orgId}**
   - Should return organization details
   - Should include AWS accounts count

4. **PUT /api/organizations/{orgId}**
   - Body: `{ "name": "Updated Name" }`
   - Should update organization name

5. **DELETE /api/organizations/{orgId}**
   - Should delete organization
   - Should return 422 if default or has AWS accounts

---

## Common Issues & Solutions

### Issue: "Failed to load organizations"
**Solution:** Check browser console for errors. Verify API routes are accessible and user is authenticated.

### Issue: "Organization not found"
**Solution:** Check that organization ID is correct. Verify user owns the organization.

### Issue: Modal doesn't close
**Solution:** Check browser console for JavaScript errors. Verify `v-model` binding is correct.

### Issue: Organization switch doesn't persist
**Solution:** Check localStorage in browser DevTools. Verify `localStorage.setItem` is being called.

### Issue: Build errors
**Solution:** Run `npm run build` to see TypeScript errors. Fix any type issues.

---

## Success Criteria

✅ All test cases pass
✅ No console errors
✅ No TypeScript errors
✅ Responsive design works
✅ Dark mode works
✅ Error handling works
✅ Business rules enforced (delete protection)
✅ Notifications appear correctly
✅ Organization context persists

---

## Next Steps After Testing

Once Organizations feature is verified:
1. Move on to AWS Accounts feature
2. Add organization selector to top bar (currently placeholder)
3. Implement organization context in API calls
4. Add organization filtering to AWS accounts list

