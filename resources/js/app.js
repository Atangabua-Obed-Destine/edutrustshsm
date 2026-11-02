import './bootstrap';

// Alpine ships with the app rather than from a CDN: on weak mobile data a
// failed CDN request left every menu, modal and form toggle dead.
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
