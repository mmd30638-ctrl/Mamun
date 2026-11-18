<style>
.pos-customer-modal {
    display: none;
    position: fixed;
    z-index: 2100000;
    inset: 0;
    padding: 12px;
    box-sizing: border-box;
    align-items: center;
    justify-content: center;
    background: rgba(17, 24, 39, 0.75);
    backdrop-filter: blur(4px);
}

.pos-customer-modal.open {
    display: flex;
}

.pos-customer-dialog {
    width: min(760px, 100%);
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 18px;
    background: #EFE6D4;
    box-shadow: 0 22px 60px rgba(0, 0, 0, 0.32);
}

.pos-customer-colors {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    height: 7px;
}

.pos-customer-colors span:nth-child(1) { background: var(--brand-navy); }
.pos-customer-colors span:nth-child(2) { background: var(--brand-accent); }
.pos-customer-colors span:nth-child(3) { background: var(--brand-navy-dark); }
.pos-customer-colors span:nth-child(4) { background: var(--pos-c-c62828); }

.pos-customer-dialog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 17px;
    background: linear-gradient(135deg, var(--pos-c-050c1f), var(--brand-navy));
    color: var(--brand-white);
}

.pos-customer-dialog-header h3 {
    margin: 0;
    font-size: 18px;
}

.pos-customer-close {
    width: 38px;
    height: 38px;
    border: 1px solid rgba(255,255,255,0.35);
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    color: var(--brand-white);
    font-size: 20px;
    cursor: pointer;
}

.pos-customer-dialog-body {
    padding: 17px;
}

.pos-customer-done {
    width: 100%;
    min-height: 46px;
    margin-top: 14px;
    border: 0;
    border-radius: 9px;
    background: var(--brand-accent);
    color: var(--brand-white);
    font-weight: 850;
    cursor: pointer;
}
</style>