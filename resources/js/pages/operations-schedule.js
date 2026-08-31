import { Modal } from 'bootstrap';
import { Calendar } from '@fullcalendar/core';
import bootstrap5Plugin from '@fullcalendar/bootstrap5';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import { BookOpen } from 'lucide';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('#schedule-calendar');
    if (!root) return;

    const modal = new Modal(document.querySelector('#assignment-modal'), { backdrop: 'static' });
    const form = document.querySelector('#assignment-form');
    const error = root.querySelector('[data-calendar-error]');
    const title = root.querySelector('[data-modal-title]');
    const remove = root.querySelector('[data-calendar-delete]');
    const user = root.querySelector('#assignment-user');
    const branch = root.querySelector('#assignment-branch');
    const shift = root.querySelector('#assignment-shift');
    const date = root.querySelector('#assignment-date');
    const filters = [...root.querySelectorAll('[data-calendar-filter]')];
    let selectedEvent = null;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const assignmentUrl = (template, id) => template.replace('__ASSIGNMENT__', id);
    const setError = (message = '') => { error.textContent = message; error.classList.toggle('d-none', !message); };
    const resetForm = () => { form.reset(); form.classList.remove('was-validated'); setError(); };
    const filtersQuery = () => Object.fromEntries(filters.filter((filter) => filter.value).map((filter) => [filter.dataset.calendarFilter, filter.value]));
    const request = async (url, method, payload = null) => {
        const response = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: payload ? JSON.stringify(payload) : null });
        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            const validationMessage = body.errors ? Object.values(body.errors).flat()[0] : null;
            throw new Error(validationMessage || body.message || 'No se pudo guardar la asignación.');
        }
        return response.status === 204 ? null : response.json();
    };
    const openCreate = (day = new Date()) => { resetForm(); selectedEvent = null; title.textContent = 'Nueva asignación'; remove.classList.add('d-none'); user.disabled = false; date.value = typeof day === 'string' ? day : day.toISOString().slice(0, 10); modal.show(); };
    const openEdit = (event) => { resetForm(); selectedEvent = event; title.textContent = 'Editar asignación'; remove.classList.remove('d-none'); date.value = event.startStr; user.value = event.extendedProps.core_user_id; user.disabled = true; branch.value = event.extendedProps.branch_id; shift.value = event.extendedProps.shift_id; modal.show(); };

    const calendar = new Calendar(root.querySelector('[data-calendar-canvas]'), {
        plugins: [bootstrap5Plugin, dayGridPlugin, interactionPlugin], themeSystem: 'bootstrap5', initialView: 'dayGridMonth', height: 'auto', fixedWeekCount: false, selectable: true, editable: false,
        buttonText: { today: 'Hoy', month: 'Mes' }, headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth' },
        events: async (info, success, failure) => { try { const url = new URL(root.dataset.eventsUrl, window.location.origin); url.search = new URLSearchParams({ start: info.startStr, end: info.endStr, ...filtersQuery() }); const response = await fetch(url, { headers: { Accept: 'application/json' } }); if (!response.ok) throw new Error('No se pudieron cargar las asignaciones.'); success(await response.json()); } catch (exception) { failure(exception); } },
        dateClick: (info) => openCreate(info.dateStr), eventClick: (info) => openEdit(info.event),
        eventContent: (info) => {
            const container = document.createElement('div');
            const eventTitle = document.createElement('div');
            const eventBranch = document.createElement('div');
            const eventHours = document.createElement('div');
            const eventShift = document.createElement('div');
            eventTitle.className = 'tr-event-title';
            eventBranch.className = 'tr-event-branch';
            eventHours.className = 'tr-event-hours';
            eventShift.className = 'tr-event-shift';
            eventTitle.textContent = info.event.extendedProps.user_name;
            eventBranch.textContent = `⌖ ${info.event.extendedProps.branch_name}`;
            eventHours.textContent = `◷ ${info.event.extendedProps.shift_hours}`;
            eventShift.textContent = info.event.extendedProps.shift_name;
            if (info.event.extendedProps.has_support_material) { const material = document.createElement('span'); material.className = 'ms-1'; material.title = 'Tiene material de apoyo'; material.innerHTML = BookOpen.toSvg({ width: 14, height: 14, 'stroke-width': 2 }); eventShift.append(material); }
            container.append(eventTitle, eventBranch, eventHours, eventShift);
            return { domNodes: [container] };
        },
    });
    calendar.render();
    filters.forEach((filter) => filter.addEventListener('change', () => calendar.refetchEvents()));
    root.querySelector('[data-calendar-reset]').addEventListener('click', () => { filters.forEach((filter) => { if (!filter.disabled) filter.value = ''; }); calendar.refetchEvents(); });
    root.querySelector('[data-calendar-new]').addEventListener('click', () => openCreate());
    form.addEventListener('submit', async (event) => { event.preventDefault(); if (!form.checkValidity()) { form.classList.add('was-validated'); return; } try { const payload = { date: date.value, core_user_id: user.value, branch_id: branch.value, shift_id: shift.value }; await request(selectedEvent ? assignmentUrl(root.dataset.updateUrl, selectedEvent.id) : root.dataset.storeUrl, selectedEvent ? 'PATCH' : 'POST', payload); modal.hide(); calendar.refetchEvents(); } catch (exception) { setError(exception.message); } });
    remove.addEventListener('click', async () => { if (!selectedEvent || !window.confirm('¿Eliminar esta asignación? Esta acción no se puede deshacer.')) return; try { await request(assignmentUrl(root.dataset.deleteUrl, selectedEvent.id), 'DELETE'); modal.hide(); calendar.refetchEvents(); } catch (exception) { setError(exception.message); } });
});
