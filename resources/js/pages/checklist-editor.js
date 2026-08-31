document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('#checklist-form');
    if (!form) return;

    const list = form.querySelector('[data-checklist-items]');
    const template = document.querySelector('#checklist-item-template');
    let draggedRow = null;

    const reindex = () => {
        [...list.querySelectorAll('[data-checklist-item]')].forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((field) => {
                const key = field.name.match(/\]\[([^\]]+)\]$/)?.[1];
                if (key) field.name = `items[${index}][${key}]`;
            });
        });
    };

    const bindRow = (row) => {
        row.addEventListener('dragstart', () => { draggedRow = row; row.classList.add('table-active'); });
        row.addEventListener('dragend', () => { draggedRow = null; row.classList.remove('table-active'); });
        row.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (!draggedRow || draggedRow === row) return;
            const before = event.clientY < row.getBoundingClientRect().top + row.offsetHeight / 2;
            list.insertBefore(draggedRow, before ? row : row.nextSibling);
        });
        row.querySelector('[data-remove-checklist-item]').addEventListener('click', () => { row.remove(); reindex(); });
    };

    [...list.querySelectorAll('[data-checklist-item]')].forEach(bindRow);
    form.querySelector('[data-add-checklist-item]').addEventListener('click', () => {
        const row = template.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-field]').forEach((field) => { field.name = `items[${list.children.length}][${field.dataset.field}]`; });
        list.append(row);
        bindRow(row);
    });
    form.addEventListener('submit', reindex);
});
