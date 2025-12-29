import PrimeVue from 'primevue/config';
import Material from '@primevue/themes/material';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputPassword from 'primevue/password';
import Card from 'primevue/card';
import Message from 'primevue/message';
import Checkbox from 'primevue/checkbox';
import Dropdown from 'primevue/dropdown';
import Menu from 'primevue/menu';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Badge from 'primevue/badge';
import ProgressBar from 'primevue/progressbar';
import Dialog from 'primevue/dialog';

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.use(PrimeVue, {
    theme: {
      preset: Material,
      options: {
        darkModeSelector: false,
      }
    }
  });

  // Register components globally
  nuxtApp.vueApp.component('Button', Button);
  nuxtApp.vueApp.component('InputText', InputText);
  nuxtApp.vueApp.component('InputPassword', InputPassword);
  nuxtApp.vueApp.component('Card', Card);
  nuxtApp.vueApp.component('Message', Message);
  nuxtApp.vueApp.component('Checkbox', Checkbox);
  nuxtApp.vueApp.component('Dropdown', Dropdown);
  nuxtApp.vueApp.component('Menu', Menu);
  nuxtApp.vueApp.component('DataTable', DataTable);
  nuxtApp.vueApp.component('Column', Column);
  nuxtApp.vueApp.component('Badge', Badge);
  nuxtApp.vueApp.component('ProgressBar', ProgressBar);
  nuxtApp.vueApp.component('Dialog', Dialog);
});

