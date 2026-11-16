<style>
:root {
    --sb-bg: #ffffff;
    --sb-bg-2: #F4F7FB;
    --sb-text: #111827;
    --sb-text-muted: #667085;
    --sb-accent: #003F8F;
    --sb-active-bg: #E8EEF8;
    --sb-icon-bg: #E8EEF8;
    --sb-icon-color: #003F8F;
    --sb-width: 280px;
    --sb-transition: cubic-bezier(0.4, 0, 0.2, 1);
}

.hidden { display: none !important; }

.sidebar-backdrop {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9998;
}
.sidebar-backdrop.active { display: block; background: rgba(0, 0, 0, 0.2); }

.custom-sidebar {
    height: 100vh;
    width: var(--sb-width);
    position: fixed;
    top: 0;
    left: calc(-1 * var(--sb-width));
    background: var(--sb-bg) !important;
    background-color: var(--sb-bg) !important;
    background-image: none !important;
    transition: left 0.4s var(--sb-transition);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    z-index: 9999;
    box-shadow: 4px 0 24px rgba(11, 30, 77, 0.12) !important;
    border: 0 !important;
    border-right: 1px solid rgba(11, 30, 77, 0.08) !important;
    backdrop-filter: blur(8px);
}

.custom-sidebar.active { left: 0 !important; }

.sidebar-close-btn {
    position: absolute;
    top: 12px;
    right: 14px;
    background: transparent !important;
    border: none;
    color: #0B1E4D;
    font-size: 22px;
    line-height: 1;
    width: 32px;
    height: 32px;
    border-radius: 0;
    cursor: pointer;
}

.sidebar-brand-header {
    padding: 2rem 1.5rem;
    background: var(--sb-bg-2) !important;
    border: 0 !important;
    border-bottom: 1px solid rgba(11, 30, 77, 0.08) !important;
    display: flex;
    align-items: center;
}

.brand-logo {
    width: 45px; height: 45px;
    background: var(--sb-icon-bg) !important;
    border: 1px solid rgba(11, 30, 77, 0.12) !important;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: var(--sb-icon-color); font-weight: 900; font-size: 1.2rem;
}

.brand-info { margin-left: 1rem; }
.brand-info h4 { color: #0B1E4D; font-size: 1rem; font-weight: 700; margin-bottom: 2px; }
.status-indicator { font-size: 0.65rem; color: #334155; display: flex; align-items: center; }

.ping-dot {
    height: 6px; width: 6px; background: #22D3EE; border-radius: 50%;
    margin-right: 6px; display: inline-block;
    box-shadow: 0 0 0 2px rgba(34, 211, 238, 0.3);
}

.menu-list .sb-theme-open,
.menu-list a.sb-atlas-open {
    margin: 6px 0 8px !important;
}
.sb-theme-open {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    width: 100% !important;
    margin: 0 0 14px !important;
    padding: 11px 12px !important;
    border: 0 !important;
    border-radius: 14px !important;
    cursor: pointer !important;
    text-align: left !important;
    background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 58%, #c9a227 100%) !important;
    color: #fff !important;
    box-shadow: 0 8px 18px rgba(29, 78, 216, 0.28) !important;
}
.sb-theme-open:hover,
.sb-theme-open:focus {
    filter: brightness(1.06);
    outline: none;
}
a.sb-atlas-open {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    width: 100% !important;
    margin: 0 0 8px !important;
    padding: 11px 12px !important;
    border: 0 !important;
    border-radius: 14px !important;
    cursor: pointer !important;
    text-align: left !important;
    background: linear-gradient(135deg, #0f172a 0%, #0f766e 55%, #2dd4bf 100%) !important;
    color: #fff !important;
    box-shadow: 0 8px 18px rgba(15, 118, 110, 0.28) !important;
    text-decoration: none !important;
}
a.sb-atlas-open:hover,
a.sb-atlas-open:focus {
    filter: brightness(1.06);
    outline: none;
}
.sb-theme-swatch {
    width: 38px;
    height: 38px;
    min-width: 38px;
    border-radius: 11px;
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
}
.sb-theme-swatch svg {
    width: 22px;
    height: 22px;
    display: block;
}
.sb-theme-copy {
    min-width: 0;
    display: block;
}
.sb-theme-copy strong {
    display: block;
    font-size: 14px;
    font-weight: 800;
    letter-spacing: 0;
    line-height: 1.2;
    color: #fff;
}
.sb-theme-copy small {
    display: block;
    margin-top: 3px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.35;
    color: rgba(255, 255, 255, 0.88);
}

.sidebar-menu-body { flex: 1; overflow-y: auto; padding: 1rem 0.75rem; background: var(--sb-bg) !important; }
.sidebar-menu-body::-webkit-scrollbar { width: 4px; }
.sidebar-menu-body::-webkit-scrollbar-thumb { background: rgba(11, 30, 77, 0.25); border-radius: 10px; }

.direct-link, .menu-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.55rem 0.35rem; color: #0B1E4D !important; font-size: 0.9rem;
    font-weight: 700;
    background: transparent !important;
    border: 0 !important;
    border-radius: 0 !important;
    margin-bottom: 0.15rem; transition: color 0.15s;
    cursor: pointer; text-decoration: none;
    width: 100%;
    font-family: inherit;
    text-align: left;
}
.menu-head > span:first-child, .direct-link {
    display: flex; align-items: center; gap: 10px;
}

.direct-link:hover, .menu-head:hover {
    background: transparent !important;
    color: #0091EA !important;
}

.direct-link.active {
    background: transparent !important;
    color: #0091EA !important;
    border: 0 !important;
}
a.direct-link[href="/logout.php"] { color: #C62828 !important; }
.menu-head .arrow { color: #64748B; }

.menu-icon-plain {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 34px !important;
    height: 34px !important;
    min-width: 34px !important;
    padding: 0 !important;
    margin: 0 !important;
    border: 1px solid rgba(11, 30, 77, 0.1) !important;
    border-radius: 10px !important;
    background: var(--sb-icon-bg) !important;
    background-color: var(--sb-icon-bg) !important;
    box-shadow: 0 1px 2px rgba(11, 30, 77, 0.06) !important;
    color: var(--sb-icon-color) !important;
    line-height: 0 !important;
    flex-shrink: 0;
}
.menu-icon-plain svg {
    width: 18px !important;
    height: 18px !important;
    display: block !important;
    stroke: currentColor !important;
    fill: none !important;
}
.direct-link:hover .menu-icon-plain,
.menu-head:hover .menu-icon-plain,
.direct-link.active .menu-icon-plain {
    background: rgba(0, 145, 234, 0.14) !important;
    border-color: rgba(0, 145, 234, 0.25) !important;
    color: #0077C8 !important;
}
.custom-sidebar .menu-icon-badge,
.sidebar-menu-body .menu-icon-badge,
span.menu-icon-badge {
    width: 34px !important;
    height: 34px !important;
    min-width: 34px !important;
    min-height: 34px !important;
    padding: 0 !important;
    border: 1px solid rgba(11, 30, 77, 0.1) !important;
    border-radius: 10px !important;
    background: var(--sb-icon-bg) !important;
    background-color: var(--sb-icon-bg) !important;
    background-image: none !important;
    color: var(--sb-icon-color) !important;
    box-shadow: 0 1px 2px rgba(11, 30, 77, 0.06) !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 16px !important;
    line-height: 1 !important;
    flex-shrink: 0;
}

.menu-list {
    padding-left: 0.6rem;
    margin-bottom: 0.5rem;
}
.menu-list a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0.45rem 0.35rem;
    color: #334155 !important;
    font-size: 0.85rem;
    text-decoration: none;
    border-radius: 0;
    margin-bottom: 2px;
    background: transparent !important;
}
.menu-list a:hover { background: transparent !important; color: #0091EA !important; }

.sidebar-footer { padding: 1.25rem 1.5rem; border: 0 !important; border-top: 1px solid rgba(11, 30, 77, 0.08) !important; background: var(--sb-bg-2) !important; }
.user-pill {
    background: transparent !important; padding: 0.35rem 0;
    border-radius: 0; display: flex; align-items: center; justify-content: space-between;
}
.user-pill-name { color: #0B1E4D; font-size: 0.85rem; font-weight: 700; }
.user-pill-logout { color: #E53935; text-decoration: none; font-size: 1.1rem; }
</style>