import $ from 'jquery';
import DataTable, { util } from 'datatables.net';
import Chart from 'chart.js/auto';
import { hierarchy, select, selectAll, tree, zoom } from 'd3';
import Alpine from 'alpinejs';
import './scheduled-date-calendar';

// The admin views bootstrap DataTables/Chart.js/d3 inline, so the bundle
// exposes the same globals the CDN tags used to provide. Everything is
// pinned in package.json and served from the Vite build (no CDN, works
// offline and behind a strict CSP).
window.$ = window.jQuery = $;
window.DataTable = DataTable;
window.Chart = Chart;
// Only the d3 pieces the referral tree uses are bundled (a namespace
// import would pull the whole library in).
window.d3 = { hierarchy, select, selectAll, tree, zoom };
window.Alpine = Alpine;

// DataTables' ESM build only picks up a *global* jQuery, which does not
// exist before this module runs, so wire it up explicitly — this is what
// registers $.fn.DataTable for the inline `$('#table').DataTable()` calls.
util.external($);

Alpine.start();
