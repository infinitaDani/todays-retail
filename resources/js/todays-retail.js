import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import { createIcons, icons } from 'lucide';
import SimpleBar from 'simplebar';

const root = document.documentElement;

document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons });

    document.querySelectorAll('[data-simplebar]').forEach((element) => {
        if (!element.SimpleBar) new SimpleBar(element);
    });

    document.querySelectorAll('[data-sidenav-toggle]').forEach((button) => {
        button.addEventListener('click', () => root.classList.toggle('sidenav-collapsed'));
    });

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextTheme = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-bs-theme', nextTheme);
            window.localStorage.setItem('todays-retail-theme', nextTheme);
        });
    });

    const savedTheme = window.localStorage.getItem('todays-retail-theme');
    if (savedTheme === 'light' || savedTheme === 'dark') root.setAttribute('data-bs-theme', savedTheme);
});
