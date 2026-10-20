import './bootstrap';

import Alpine from 'alpinejs';
import Sortable from 'sortablejs';
import './google-auth';

// Globals the page-level inline scripts rely on (comics/edit and
// collections/edit bootstrap Sortable, every layout uses x-data).
window.Alpine = Alpine;
window.Sortable = Sortable;

Alpine.start();
