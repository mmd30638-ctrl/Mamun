<style>
.pos-customer-form {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    padding: 17px;
}

.pos-customer-form label {
    display: block;
    margin-bottom: 5px;
    color: var(--brand-navy-dark);
    font-size: 12px;
    font-weight: 800;
}

.pos-customer-form input,
.pos-customer-form select,
.pos-customer-form textarea {
    width: 100%;
    min-width: 0;
    padding: 10px;
    box-sizing: border-box;
    border: 1.5px solid var(--pos-c-afbdcd);
    border-radius: 8px;
    font: inherit;
    outline: none;
}

.pos-customer-form input,
.pos-customer-form select {
    height: 44px;
}

.pos-customer-form textarea {
    min-height: 70px;
    resize: vertical;
}

.pos-customer-error {
    display: none;
    padding: 10px;
    border-radius: 8px;
    background: var(--pos-c-ffebee);
    color: var(--pos-c-c62828);
    font-size: 12px;
}

.pos-customer-error.show {
    display: block;
}

.pos-customer-form-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px;
}

.pos-customer-form-actions button {
    min-height: 45px;
    border: 0;
    border-radius: 9px;
    color: var(--brand-white);
    font-weight: 850;
    cursor: pointer;
}

.pos-customer-cancel {
    background: var(--pos-c-c62828);
}

.pos-customer-save {
    background: var(--brand-accent);
}

@media (min-width: 650px) {
    .pos-customer-form {
        grid-template-columns: 1fr 1fr;
    }

    .pos-customer-full {
        grid-column: 1 / -1;
    }
}
</style>