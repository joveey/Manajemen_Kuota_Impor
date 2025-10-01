import './bootstrap';

import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

// Import jQuery (required by AdminLTE)
import $ from 'jquery';
window.$ = window.jQuery = $;

// Import AdminLTE
import 'admin-lte/dist/js/adminlte.min.js';