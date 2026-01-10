import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Ensure cookies are sent with requests (for session authentication)
window.axios.defaults.withCredentials = true;
