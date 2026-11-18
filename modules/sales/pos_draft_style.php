<style>
.pos-draft-controls {
    margin: 15px 0;
    padding: 14px;
    border: 1px solid var(--pos-c-d8e1ec);
    border-left: 5px solid var(--brand-navy);
    border-radius: 12px;
    background: var(--brand-surface-soft);
}

.pos-draft-controls-title {
    margin: 0 0 10px;
    color: var(--brand-navy-dark);
    font-size: 14px;
    font-weight: 850;
}

.pos-draft-note {
    display: block;
    width: 100%;
    min-height: 72px;
    padding: 10px 12px;
    box-sizing: border-box;
    resize: vertical;
    border: 1.5px solid var(--pos-c-afbdcd);
    border-radius: 9px;
    background: var(--brand-white);
    color: var(--brand-navy-dark);
    font-family: inherit;
    font-size: 14px;
    outline: none;
}

.pos-draft-note:focus {
    border-color: var(--brand-navy);
    box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.10);
}

.pos-draft-buttons {
    display: grid;
    grid-template-columns: 1fr;
    gap: 9px;
    margin-top: 10px;
}

.pos-draft-button {
    min-height: 45px;
    padding: 10px 13px;
    border: 0;
    border-radius: 9px;
    color: var(--brand-white);
    font-weight: 850;
    cursor: pointer;
}

.pos-draft-button.save {
    background: var(--brand-navy-dark);
}

.pos-draft-button.list {
    background: var(--brand-navy);
}

.pos-draft-button:disabled {
    opacity: 0.6;
    cursor: wait;
}

.pos-draft-current {
    display: block;
    margin-top: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    background: var(--pos-c-e1f5fe);
    color: var(--brand-accent);
    font-size: 12px;
    font-weight: 850;
}

.pos-draft-drawer {
    display: none;
    position: fixed;
    z-index: 2000000;
    inset: 0;
    padding: 12px;
    box-sizing: border-box;
    align-items: center;
    justify-content: center;
    background: rgba(17, 24, 39, 0.72);
    backdrop-filter: blur(4px);
}

.pos-draft-drawer.open {
    display: flex;
}

.pos-draft-panel {
    width: min(620px, 100%);
    max-height: 88vh;
    overflow: hidden;
    border-radius: 18px;
    background: var(--brand-white);
    box-shadow: 0 22px 60px rgba(0, 0, 0, 0.30);
}

.pos-recent-modal {
    width: min(560px, 100%);
    max-width: 100%;
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    flex-wrap: nowrap;
    box-sizing: border-box;
    min-width: 0;
    overflow: hidden;
    background: #ffffff;
}

.pos-recent-modal-header {
    padding: 14px 16px 10px;
    background: var(--brand-white);
    border-bottom: 1px solid var(--pos-c-d8e1ec);
}

.pos-recent-modal-header h3 {
    margin: 0;
    padding: 10px 12px;
    border: 1px solid var(--pos-c-d8e1ec);
    border-radius: 10px;
    background: var(--pos-c-f4f7fb);
    color: var(--brand-navy);
    font-size: 18px;
    font-weight: 800;
    text-align: center;
}

.pos-recent-table-wrap {
    overflow-x: hidden;
    overflow-y: auto;
    max-height: calc(88vh - 130px);
    max-width: 100%;
    min-width: 0;
    background: var(--brand-white);
}

.pos-recent-table {
    width: 100%;
    max-width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 16px;
    font-weight: 800;
}

.pos-recent-col-sl { width: 7%; }
.pos-recent-col-time { width: 14%; }
.pos-recent-col-inv { width: 17%; }
.pos-recent-col-cust { width: 23%; }
.pos-recent-col-price { width: 13%; }
.pos-recent-col-ico { width: 26%; }

#draftDrawer .pos-recent-col-cust { width: auto; }
#draftDrawer .pos-recent-col-ico { width: 112px; }

.pos-recent-table th,
.pos-recent-table td {
    padding: 10px 8px;
    border-bottom: 1px solid var(--pos-c-e5edf5);
    text-align: center;
    vertical-align: middle;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pos-recent-table th {
    background: var(--pos-c-f4f7fb);
    color: var(--brand-navy);
    font-weight: 800;
}

.pos-recent-table td {
    color: var(--brand-navy-dark);
}

.pos-recent-cust {
    min-width: 0;
    white-space: normal !important;
    overflow: hidden;
}

#draftDrawer .pos-recent-draft-row {
    cursor: pointer;
}

#draftDrawer .pos-recent-draft-row:hover {
    background: var(--pos-c-e3f2fd);
}

#draftDrawer .pos-recent-draft-row.is-reopened-highlight .pos-recent-cust-name {
    border-color: #0f766e;
    background: #ecfdf5;
    box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.25);
}

.pos-recent-cust-name,
.pos-recent-cashier {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-align: center;
}

.pos-recent-cust-name {
    padding: 10px 8px;
    border: 1px solid var(--pos-c-d8e1ec);
    border-radius: 10px;
    background: var(--pos-c-f4f7fb);
    color: var(--brand-navy);
    font-size: 16px;
    font-weight: 800;
}

.pos-recent-cashier {
    margin-top: 1px;
    color: var(--pos-c-64748b);
    font-size: 10px;
    font-weight: 700;
}

.pos-recent-time,
.pos-recent-inv {
    min-width: 0;
}

.pos-recent-time-date,
.pos-recent-time-clock {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pos-recent-time-clock {
    color: var(--pos-c-64748b);
    font-size: 10px;
    font-weight: 700;
}

.pos-recent-actions {
    white-space: nowrap !important;
    text-align: center;
    width: 112px;
    overflow: visible !important;
}

.pos-recent-action-bar {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    pointer-events: auto;
}

.pos-recent-table .pos-recent-icon-btn {
    width: 24px;
    height: 24px;
    min-height: 24px;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--pos-c-1565c0);
    font-size: 15px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none !important;
}

.pos-recent-table .pos-recent-icon-btn:hover {
    background: var(--pos-c-e3f2fd);
}

.pos-recent-modal-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-top: 1px solid var(--pos-c-d8e1ec);
    background: var(--brand-surface-soft);
}

.pos-recent-modal-footer .pos-draft-history-link {
    margin-left: 0;
    margin-right: auto;
    padding: 7px 10px;
    border: 1px solid var(--pos-c-c5ced8);
    border-radius: 8px;
    background: var(--brand-white);
    color: var(--brand-navy) !important;
    text-decoration: none !important;
    font-size: 12px;
    font-weight: 800;
}

.pos-recent-close-btn {
    min-height: 38px;
    padding: 8px 18px;
    border: 1px solid var(--pos-c-c5ced8);
    border-radius: 4px;
    background: var(--brand-white);
    color: var(--brand-navy-dark);
    font-weight: 800;
    font-size: 14px;
    cursor: pointer;
}

.pos-draft-panel-colors {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    height: 7px;
}

.pos-draft-panel-colors span:nth-child(1) {
    background: var(--brand-navy);
}

.pos-draft-panel-colors span:nth-child(2) {
    background: var(--brand-accent);
}

.pos-draft-panel-colors span:nth-child(3) {
    background: var(--brand-navy-dark);
}

.pos-draft-panel-colors span:nth-child(4) {
    background: var(--pos-c-c62828);
}

.pos-draft-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 17px;
    background: linear-gradient(135deg, var(--pos-c-050c1f), var(--brand-navy));
    color: var(--brand-white);
}

.pos-draft-panel-header h3 {
    margin: 0;
    font-size: 18px;
}

.pos-draft-history-link {
    margin-left: auto;
    padding: 7px 10px;
    border: 1px solid rgba(255, 255, 255, 0.35);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.14);
    color: var(--brand-white) !important;
    text-decoration: none !important;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

.pos-draft-close {
    width: 38px;
    height: 38px;
    border: 1px solid rgba(255, 255, 255, 0.35);
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.15);
    color: var(--brand-white);
    font-size: 20px;
    cursor: pointer;
}

.pos-draft-list {
    max-height: calc(88vh - 75px);
    padding: 14px;
    overflow-y: auto;
    background: var(--pos-c-f4f7fb);
}

.pos-draft-item {
    display: grid;
    gap: 6px;
    margin-bottom: 11px;
    padding: 14px;
    border: 1px solid var(--pos-c-d8e1ec);
    border-left: 5px solid var(--brand-navy);
    border-radius: 11px;
    background: var(--brand-white);
}

.pos-draft-item strong {
    color: var(--brand-navy);
    font-size: 14px;
}

.pos-draft-item span {
    color: var(--brand-navy-dark);
    font-size: 12px;
}

.pos-draft-item small {
    color: var(--pos-c-64748b);
    font-size: 11px;
}

.pos-draft-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 7px;
}

.pos-draft-actions button,
.pos-draft-actions a {
    min-height: 39px;
    border: 0;
    border-radius: 8px;
    color: var(--brand-white);
    font-weight: 800;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none !important;
}

.pos-draft-load {
    background: var(--brand-accent);
}

.pos-draft-cancel {
    background: var(--pos-c-c62828);
}

.pos-draft-empty {
    padding: 25px;
    border-radius: 11px;
    background: var(--brand-white);
    color: var(--pos-c-64748b);
    text-align: center;
}

.pos-recent-table .pos-draft-empty {
    padding: 28px 12px;
    border-radius: 0;
    text-align: center;
    white-space: normal;
}

@media (min-width: 540px) {
    .pos-draft-buttons {
        grid-template-columns: 1fr 1fr;
    }
}

/* Unique saved-draft memo ticket (replaces browser alert) */
.ame-draft-toast {
    position: fixed;
    inset: 0;
    z-index: 4000000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    box-sizing: border-box;
    background: rgba(5, 12, 31, 0.58);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    animation: ameDraftFadeIn 0.22s ease;
}

.ame-draft-toast[hidden] {
    display: none !important;
}

.ame-draft-ticket {
    position: relative;
    width: min(92vw, 360px);
    overflow: hidden;
    border-radius: 20px;
    background:
        linear-gradient(180deg, var(--pos-c-fffef9) 0%, var(--pos-c-fff8e8) 48%, var(--brand-white) 100%);
    box-shadow:
        0 28px 64px rgba(5, 12, 31, 0.38),
        0 0 0 1px rgba(255, 215, 120, 0.35);
    animation: ameDraftPop 0.42s cubic-bezier(0.18, 1.15, 0.32, 1);
}

.ame-draft-ticket.is-error {
    background: linear-gradient(180deg, var(--pos-c-fff7f7) 0%, var(--brand-white) 100%);
    box-shadow: 0 28px 64px rgba(5, 12, 31, 0.38);
}

.ame-draft-ticket-bar {
    height: 8px;
    background: repeating-linear-gradient(
        90deg,
        var(--brand-navy) 0 28px,
        var(--brand-success) 28px 56px,
        var(--pos-c-c9a227) 56px 84px,
        var(--brand-accent) 84px 112px
    );
}

.ame-draft-ticket.is-error .ame-draft-ticket-bar {
    background: repeating-linear-gradient(
        90deg,
        var(--pos-c-7f1d1d) 0 28px,
        var(--pos-c-c62828) 28px 56px,
        var(--pos-c-ef9a9a) 56px 84px
    );
}

.ame-draft-ticket-body {
    padding: 22px 22px 18px;
    text-align: center;
}

.ame-draft-stamp {
    position: relative;
    width: 78px;
    height: 78px;
    margin: 4px auto 14px;
    border-radius: 50%;
    background: radial-gradient(circle at 32% 28%, var(--pos-c-7cffc4), var(--brand-success) 62%, var(--pos-c-086b3c));
    box-shadow:
        0 10px 22px rgba(14, 143, 82, 0.35),
        inset 0 0 0 4px rgba(255, 255, 255, 0.28);
    animation: ameDraftStamp 0.5s 0.08s both;
}

.ame-draft-ticket.is-error .ame-draft-stamp {
    background: radial-gradient(circle at 32% 28%, var(--pos-c-ff8a80), var(--pos-c-c62828) 62%, var(--pos-c-8e0000));
    box-shadow: 0 10px 22px rgba(198, 40, 40, 0.32);
}

.ame-draft-stamp svg {
    position: absolute;
    inset: 18px;
    width: 42px;
    height: 42px;
    stroke: var(--brand-white);
    fill: none;
    stroke-width: 3.4;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.ame-draft-stamp .ame-draft-check {
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    animation: ameDraftDraw 0.45s 0.28s forwards;
}

.ame-draft-title {
    margin: 0 0 6px;
    color: var(--brand-navy);
    font-size: 20px;
    font-weight: 850;
    letter-spacing: 0.01em;
}

.ame-draft-ticket.is-error .ame-draft-title {
    color: var(--pos-c-7f1d1d);
}

.ame-draft-hint {
    margin: 0 0 14px;
    color: var(--pos-c-5b6b7c);
    font-size: 13px;
    font-weight: 700;
    line-height: 1.45;
}

.ame-draft-code {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 0 auto 16px;
    padding: 10px 12px;
    max-width: 100%;
    border: 1.5px dashed var(--pos-c-c9a227);
    border-radius: 12px;
    background: rgba(201, 162, 39, 0.08);
}

.ame-draft-code[hidden] {
    display: none !important;
}

.ame-draft-code b {
    color: var(--brand-navy);
    font-family: ui-monospace, "Cascadia Mono", monospace;
    font-size: 13px;
    font-weight: 850;
    letter-spacing: 0.04em;
    word-break: break-all;
}

.ame-draft-copy {
    flex: 0 0 auto;
    min-width: 58px;
    height: 30px;
    padding: 0 8px;
    border: 0;
    border-radius: 8px;
    background: var(--brand-navy);
    color: var(--brand-white);
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
}

.ame-draft-ok {
    width: 100%;
    min-height: 46px;
    border: 0;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--brand-navy), var(--pos-c-1565c0));
    color: var(--brand-white);
    font-size: 15px;
    font-weight: 850;
    cursor: pointer;
    box-shadow: 0 8px 18px rgba(11, 30, 77, 0.25);
}

.ame-draft-ticket.is-error .ame-draft-ok {
    background: linear-gradient(135deg, var(--pos-c-7f1d1d), var(--pos-c-c62828));
}

.ame-draft-ok:active {
    transform: scale(0.98);
}

@keyframes ameDraftFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes ameDraftPop {
    from {
        opacity: 0;
        transform: translateY(18px) scale(0.92);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes ameDraftStamp {
    from { transform: scale(0.4) rotate(-18deg); opacity: 0; }
    to { transform: scale(1) rotate(0); opacity: 1; }
}

@keyframes ameDraftDraw {
    to { stroke-dashoffset: 0; }
}

@media (prefers-reduced-motion: reduce) {
    .ame-draft-toast,
    .ame-draft-ticket,
    .ame-draft-stamp,
    .ame-draft-stamp .ame-draft-check {
        animation: none;
    }
}
</style>