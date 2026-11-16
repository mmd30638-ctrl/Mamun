<style>
.pos-customer-panel {
    margin-top: 8px;
    padding: 0;
    border: 0;
    background: transparent;
}

.pos-customer-tabs {
    display: flex;
    gap: 6px;
    margin: 0 0 8px;
}

.pos-customer-tab {
    flex: 1;
    padding: 12px 8px;
    border: 1.5px solid var(--pos-c-d7e0eb);
    border-radius: 8px;
    background: var(--pos-c-f1f5f9);
    color: var(--brand-navy-dark);
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.pos-customer-tab.active {
    border-color: var(--brand-navy);
    background: var(--brand-navy);
    color: var(--brand-white);
}

.pos-customer-summary {
    padding: 10px 12px;
    border-radius: 8px;
    background: var(--brand-surface-soft);
    border: 1px solid var(--brand-border-soft);
    color: var(--brand-navy-dark);
    font-size: 13px;
    font-weight: 700;
}

.pos-customer-summary.has-value {
    background: var(--pos-c-e1f5fe);
    border-color: var(--brand-accent);
    color: var(--brand-accent);
}

.pos-customer-modes {
    display: none !important;
}

.pos-customer-select-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
}

.pos-customer-picker {
    position: relative;
    min-width: 0;
}

.pos-info-picker {
    position: relative;
    z-index: 400020;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 6px;
    width: 100%;
}

.pos-info-picker .pos-customer-suggest {
    grid-column: 1 / -1;
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 4px);
    z-index: 400021;
    max-height: min(320px, 50vh);
}

.pos-customer-add-inline {
    min-width: 42px;
    width: 42px;
    padding: 0;
    font-size: 22px;
    line-height: 1;
}

/* মেমোর সাথে গাড়ির নম্বরের ছোট্ট বক্স (workshop sale) */
.pos-vehicle-box {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
    padding: 0 10px;
    height: 40px;
    border: 1.5px solid var(--pos-c-d7e0eb);
    border-radius: 9px;
    background: var(--brand-surface-soft);
}
.pos-vehicle-box:focus-within {
    border-color: var(--brand-navy);
    box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.10);
    background: var(--brand-white);
}
.pos-vehicle-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    color: var(--pos-c-f59e0b);
}
.pos-vehicle-icon svg {
    width: 18px;
    height: 18px;
}
.pos-vehicle-box input#posVehicleNo {
    flex: 1;
    min-width: 0;
    height: 100%;
    border: 0;
    background: transparent;
    padding: 0;
    font-size: 14px;
    font-weight: 700;
    color: var(--brand-navy-dark);
    outline: none;
}

.pos-customer-select-native {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    border: 0;
}

.pos-customer-select {
    width: 100%;
    min-width: 0;
    height: 48px;
    padding: 0 12px;
    box-sizing: border-box;
    border: 1.5px solid var(--pos-c-afbdcd);
    border-radius: 9px;
    background: var(--brand-white);
    color: var(--brand-navy-dark);
    font-size: 14px;
    outline: none;
}

.pos-customer-select:focus {
    border-color: var(--brand-navy);
    box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.10);
}

.pos-customer-suggest {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 4px);
    z-index: 400021;
    max-height: 240px;
    overflow-y: auto;
    margin: 0;
    padding: 4px;
    list-style: none;
    background: var(--brand-white);
    border: 1.5px solid var(--pos-c-afbdcd);
    border-radius: 9px;
    box-shadow: 0 14px 32px rgba(15, 23, 42, 0.16);
}

.pos-customer-suggest[hidden] {
    display: none;
}

.pos-customer-suggest button {
    display: block;
    width: 100%;
    text-align: left;
    padding: 10px 12px;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: var(--brand-navy-dark);
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.pos-customer-suggest button:hover,
.pos-customer-suggest button.is-active {
    background: var(--pos-c-e1f5fe);
    color: var(--brand-accent);
}

.pos-customer-suggest .pos-customer-suggest-meta {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    font-weight: 600;
    color: var(--pos-c-64748b);
}

.pos-customer-suggest .pos-customer-suggest-empty {
    padding: 10px 12px;
    color: var(--pos-c-64748b);
    font-size: 13px;
    font-weight: 600;
}

.pos-customer-select:focus {
    border-color: var(--brand-navy);
    box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.10);
}

.pos-customer-add {
    width: auto;
    height: 48px;
    padding: 0 14px;
    border: 0;
    border-radius: 9px;
    background: var(--brand-navy);
    color: var(--brand-white);
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.pos-guest-fields {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
}

.pos-customer-field {
    width: 100%;
    min-width: 0;
    height: 46px;
    padding: 0 10px;
    box-sizing: border-box;
    border: 1.5px solid var(--pos-c-bcc8d6);
    border-radius: 8px;
    color: var(--brand-navy-dark);
    outline: none;
}

.pos-customer-field:focus {
    border-color: var(--brand-navy);
}

.pos-registered-info {
    display: none;
    margin-top: 11px;
    padding: 11px;
    border-radius: 10px;
    background: var(--pos-c-e1f5fe);
    color: var(--brand-accent);
}

.pos-registered-info strong,
.pos-registered-info span {
    display: block;
}

.pos-registered-info span {
    margin-top: 4px;
    font-size: 12px;
}

.pos-customer-meta-line {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    font-weight: 600;
    color: var(--brand-navy);
}

.pos-customer-balance-tag {
    font-style: normal;
    font-weight: 800;
    margin-left: 4px;
}

.pos-due-watch {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 12px;
    border: 1.5px solid var(--pos-c-f9a825);
    border-radius: 10px;
    background: var(--brand-warn-soft);
    cursor: pointer;
}

.pos-due-watch input {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    flex-shrink: 0;
    accent-color: var(--pos-c-e65100);
}

.pos-due-watch strong {
    display: block;
    font-size: 13px;
    color: var(--brand-navy);
}

.pos-due-watch em {
    display: block;
    font-style: normal;
    font-size: 11px;
    font-weight: 600;
    color: var(--pos-c-bf360c);
    margin-top: 4px;
    line-height: 1.4;
}
</style>