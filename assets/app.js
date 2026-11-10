import './styles/app.css';

const euros = (cents) => (cents / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0 });

function initBookingForm(form) {
    const list = form.querySelector('[data-guests]');
    const addButton = form.querySelector('[data-add-guest]');
    const totalEl = form.querySelector('[data-total]');
    const detailEl = form.querySelector('[data-total-detail]');
    const adultPrice = Number(form.dataset.adultPrice);
    const childPrice = Number(form.dataset.childPrice);
    const childMax = Number(form.dataset.childMaxAge);
    const seatsLeft = Number(form.dataset.seatsLeft);
    let index = Number(list.dataset.index);

    const guests = () => [...list.querySelectorAll('[data-guest]')];

    const refresh = () => {
        let adults = 0;
        let children = 0;
        guests().forEach((guest, i) => {
            guest.querySelector('[data-guest-title]').textContent = `Personne ${i + 1}`;
            guest.querySelector('[data-remove-guest]').hidden = guests().length === 1;
            const raw = guest.querySelector('[data-age]').value;
            if (raw === '') {
                return;
            }
            Number(raw) <= childMax ? children++ : adults++;
        });
        const total = adults * adultPrice + children * childPrice;
        totalEl.textContent = euros(total);
        const parts = [];
        if (adults) parts.push(`${adults} adulte${adults > 1 ? 's' : ''}`);
        if (children) parts.push(`${children} enfant${children > 1 ? 's' : ''}`);
        detailEl.textContent = parts.length ? `(${parts.join(' + ')})` : '(renseignez l\'âge de chaque personne)';
        addButton.disabled = guests().length >= Math.min(20, seatsLeft);
    };

    addButton.addEventListener('click', () => {
        const html = list.dataset.prototype.replaceAll('__index__', index++);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const node = wrapper.firstElementChild;
        list.append(node);
        node.querySelector('input')?.focus();
        refresh();
    });

    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-guest]');
        if (button && guests().length > 1) {
            button.closest('[data-guest]').remove();
            refresh();
        }
    });
    list.addEventListener('input', refresh);
    refresh();
}

document.querySelectorAll('.booking-form').forEach(initBookingForm);
