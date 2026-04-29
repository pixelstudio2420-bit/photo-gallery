import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import './r2-upload';
import './device-fingerprint';

Alpine.plugin(collapse);
window.Alpine = Alpine;
Alpine.start();
