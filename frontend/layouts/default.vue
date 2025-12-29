<template>
  <div class="dashboard-layout">
    <!-- Sidebar -->
    <div class="sidebar">
      <div style="padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1);">
        <h2 style="color: white; font-size: 20px; font-weight: 600;">🔒 Cloud Security</h2>
      </div>
      
      <NuxtLink 
        v-for="item in menuItems" 
        :key="item.path"
        :to="item.path"
        class="sidebar-item"
        :class="{ active: isActiveRoute(item.path) }"
      >
        <i :class="item.icon" class="sidebar-icon"></i>
        <span class="sidebar-label">{{ item.label }}</span>
      </NuxtLink>
    </div>

    <!-- Main Content -->
    <div class="main-content">
      <!-- Top Bar -->
      <div class="topbar">
        <div class="topbar-left">
          <h3 class="topbar-title">Cloud Security Dashboard</h3>
        </div>
        <div class="topbar-right">
          <div class="organization-selector">
            <i class="pi pi-building" style="margin-right: 8px; color: #666;"></i>
            <Dropdown
              v-model="selectedOrg"
              :options="organizationStore.organizations"
              optionLabel="name"
              placeholder="Select Organization"
              @change="onOrganizationChange"
              class="org-dropdown"
            />
          </div>
          <div class="user-menu-container">
            <Button
              icon="pi pi-bell"
              text
              rounded
              severity="secondary"
              aria-label="Notifications"
              class="notification-btn"
            />
            <Button
              :label="authStore.userEmail || 'User'"
              icon="pi pi-user"
              text
              @click.stop="showUserMenu = !showUserMenu"
              class="user-btn"
            />
            <div v-if="showUserMenu" class="user-menu-dropdown">
              <div class="user-menu-header">
                <div class="user-menu-email">{{ authStore.userEmail || 'User' }}</div>
                <div class="user-menu-label">Account</div>
              </div>
              <div class="user-menu-divider"></div>
              <div 
                v-for="item in userMenuItems" 
                :key="item.label" 
                class="user-menu-item"
                @click="handleMenuClick(item)"
              >
                <i :class="item.icon" class="user-menu-icon"></i>
                <span>{{ item.label }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Area -->
      <div class="content-area">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const authStore = useAuthStore();
const organizationStore = useOrganizationStore();

const selectedOrg = ref(organizationStore.currentOrganization);
const showUserMenu = ref(false);

const menuItems = [
  { path: '/dashboard', label: 'Home', icon: 'pi pi-home' },
  { path: '/organizations', label: 'Organizations', icon: 'pi pi-building' },
  { path: '/aws-accounts', label: 'AWS Accounts', icon: 'pi pi-cloud' },
  { path: '/scans', label: 'Scans', icon: 'pi pi-search' },
  { path: '/reports', label: 'Reports', icon: 'pi pi-file' },
  { path: '/insights', label: 'Insights', icon: 'pi pi-chart-bar' },
];

const userMenuItems = [
  {
    label: 'Profile',
    icon: 'pi pi-user',
    command: () => {
      navigateTo('/profile');
    },
  },
  {
    label: 'Settings',
    icon: 'pi pi-cog',
    command: () => {
      navigateTo('/settings');
    },
  },
  {
    label: 'Sign Out',
    icon: 'pi pi-sign-out',
    command: async () => {
      await authStore.signOut();
      await navigateTo('/login');
    },
  },
];

const isActiveRoute = (path: string) => {
  const currentPath = useRoute().path;
  if (path === '/dashboard') {
    return currentPath === '/' || currentPath === '/dashboard';
  }
  return currentPath.startsWith(path);
};

const onOrganizationChange = (event: any) => {
  if (event.value) {
    organizationStore.setCurrentOrganization(event.value);
    // Refresh data when organization changes
    const route = useRoute();
    if (route.path.includes('/organizations/')) {
      // If on an organization-specific page, refresh
      window.location.reload();
    }
  }
};

const handleMenuClick = (item: any) => {
  showUserMenu.value = false;
  if (item.command) {
    item.command();
  }
};

onMounted(async () => {
  if (authStore.isAuthenticated) {
    await organizationStore.fetchOrganizations();
    if (organizationStore.currentOrganization) {
      selectedOrg.value = organizationStore.currentOrganization;
    }
  }
  
  // Close menu when clicking outside
  const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    if (!target.closest('.user-menu-container')) {
      showUserMenu.value = false;
    }
  };
  document.addEventListener('click', handleClickOutside);
  
  onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
  });
});
</script>

