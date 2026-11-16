/**
 * APONSHOP Type OS 8.06 — live preview for every text layer.
 */
(function () {
  'use strict';
  if (window.__ameBrandType806) return;
  window.__ameBrandType806 = true;

  var LAYERS = ['name', 'tag', 'heading', 'body', 'caption', 'button', 'kpi', 'link'];
  var TOGGLES = {
    italic: { on: '1', off: '0' },
    decoration: { on: 'underline', off: 'none' },
    transform: { on: 'capitalize', off: 'none' }
  };

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }
  function $all(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }
  function val(name, fallback) {
    var el = document.querySelector('[name="' + name + '"]');
    return el ? String(el.value || fallback || '') : String(fallback || '');
  }
  function setVal(name, v) {
    var el = document.querySelector('[name="' + name + '"]');
    if (el) el.value = v;
  }
  function field(layer, prop) {
    return 'brand_' + layer + '_' + prop;
  }
  function layerState(layer) {
    return {
      font: val(field(layer, 'font'), 'outfit_noto'),
      color: val(field(layer, 'color'), '#123526'),
      weight: val(field(layer, 'weight'), '600'),
      size: val(field(layer, 'size'), '15'),
      tracking: val(field(layer, 'tracking'), '0'),
      leading: val(field(layer, 'leading'), '1.50'),
      transform: val(field(layer, 'transform'), 'none'),
      italic: val(field(layer, 'italic'), '0'),
      decoration: val(field(layer, 'decoration'), 'none'),
      shadow: val(field(layer, 'shadow'), 'none')
    };
  }
  function family(id) {
    return (window.AME_BRAND_FONTS && window.AME_BRAND_FONTS[id]) || '"Outfit", "Noto Sans Bengali", sans-serif';
  }
  function shadowCss(kind) {
    if (kind === 'soft') return '0 1px 1px rgba(12,36,28,0.18)';
    if (kind === 'glow') return '0 0 18px rgba(34,197,94,0.28)';
    return 'none';
  }
  function applyLayerStyle(el, s) {
    el.style.fontFamily = family(s.font);
    el.style.color = s.color;
    el.style.fontWeight = s.weight;
    el.style.fontSize = s.size + 'px';
    el.style.letterSpacing = s.tracking + 'em';
    el.style.lineHeight = s.leading;
    el.style.textTransform = s.transform;
    el.style.fontStyle = s.italic === '1' ? 'italic' : 'normal';
    el.style.textDecoration = s.decoration === 'underline' ? 'underline' : 'none';
    el.style.textShadow = shadowCss(s.shadow);
  }

  function applyPreview() {
    var studio = $('#ame-type-studio');
    if (!studio) return;
    var name = val('shop_name', 'মা ইঞ্জিনিয়ারিং ওয়ার্কশপ এন্ড মামুন ট্রেডার্স');
    var tag = val('shop_tagline', 'স্পেয়ার পার্টস ব্যবসা ও ইঞ্জিনিয়ারিং ওয়ার্কশপ');
    var activeTab = $('.ame-tab.is-on', studio);
    var current = activeTab ? String(activeTab.getAttribute('data-tab') || '') : 'name';
    LAYERS.forEach(function (layer) {
      var s = layerState(layer);
      $all('[data-t-layer="' + layer + '"]', studio).forEach(function (el) {
        applyLayerStyle(el, s);
        if (layer === 'name') el.textContent = name;
        if (layer === 'tag') el.textContent = tag || '—';
        if (layer === current) {
          el.style.outline = '3px solid ' + s.color;
          el.style.outlineOffset = '4px';
          el.style.borderRadius = '10px';
        } else {
          el.style.outline = 'none';
          el.style.outlineOffset = '';
        }
      });
      var panel = $('[data-layer-panel="' + layer + '"]', studio);
      if (!panel) return;
      $all('[data-set^="' + layer + ':font:"]', panel).forEach(function (card) {
        card.classList.toggle('is-on', card.getAttribute('data-set') === layer + ':font:' + s.font);
      });
      $all('[data-set^="' + layer + ':color:"]', panel).forEach(function (sw) {
        var hex = (sw.getAttribute('data-set') || '').split(':')[2] || '';
        sw.classList.toggle('is-on', hex.toUpperCase() === s.color.toUpperCase());
      });
      $all('[data-set^="' + layer + ':weight:"]', panel).forEach(function (chip) {
        chip.classList.toggle('is-on', chip.getAttribute('data-set') === layer + ':weight:' + s.weight);
      });
      $all('[data-set^="' + layer + ':shadow:"]', panel).forEach(function (chip) {
        chip.classList.toggle('is-on', chip.getAttribute('data-set') === layer + ':shadow:' + s.shadow);
      });
      Object.keys(TOGGLES).forEach(function (prop) {
        var spec = TOGGLES[prop];
        var on = s[prop] === spec.on;
        $all('[data-set^="' + layer + ':' + prop + ':"]', panel).forEach(function (chip) {
          chip.classList.toggle('is-on', on);
          chip.setAttribute('data-set', layer + ':' + prop + ':' + (on ? spec.off : spec.on));
        });
      });
      var hexEl = $('[data-hex="' + layer + '"]', panel);
      if (hexEl) hexEl.textContent = s.color.toUpperCase();
      var picker = $('[data-color="' + layer + '"]', panel);
      if (picker && document.activeElement !== picker) picker.value = s.color;
      var sizeOut = $('[data-out="' + layer + '-size"]', panel);
      if (sizeOut) sizeOut.textContent = s.size + 'px';
      var trackOut = $('[data-out="' + layer + '-tracking"]', panel);
      if (trackOut) trackOut.textContent = s.tracking + 'em';
      var leadOut = $('[data-out="' + layer + '-leading"]', panel);
      if (leadOut) leadOut.textContent = s.leading;
    });
  }

  function showLayer(id) {
    var studio = $('#ame-type-studio');
    if (!studio) return;
    $all('[data-tab]', studio).forEach(function (tab) {
      tab.classList.toggle('is-on', tab.getAttribute('data-tab') === id);
    });
    $all('[data-layer-panel]', studio).forEach(function (panel) {
      var on = panel.getAttribute('data-layer-panel') === id;
      if (on) panel.removeAttribute('hidden');
      else panel.setAttribute('hidden', '');
    });
  }

  function bind() {
    var studio = $('#ame-type-studio');
    if (!studio) return;

    studio.addEventListener('click', function (e) {
      var tab = e.target.closest('[data-tab]');
      if (tab) {
        e.preventDefault();
        showLayer(tab.getAttribute('data-tab'));
        return;
      }
      var setBtn = e.target.closest('[data-set]');
      if (!setBtn) return;
      e.preventDefault();
      var parts = String(setBtn.getAttribute('data-set') || '').split(':');
      if (parts.length < 3) return;
      setVal(field(parts[0], parts[1]), parts[2]);
      applyPreview();
    });

    studio.addEventListener('input', function (e) {
      var t = e.target;
      if (!t) return;
      if (t.getAttribute('data-color')) {
        setVal(field(t.getAttribute('data-color'), 'color'), String(t.value || '').toUpperCase());
        applyPreview();
        return;
      }
      if (t.matches('input[type="range"]') && t.name) applyPreview();
    });

    ['shop_name', 'shop_tagline'].forEach(function (name) {
      var el = document.querySelector('[name="' + name + '"]');
      if (!el) return;
      el.addEventListener('input', applyPreview);
      el.addEventListener('change', applyPreview);
    });

    applyPreview();
  }

  function markLive() {
    $all('.hdr-brand-box h1').forEach(function (el) { el.classList.add('ame-brand-name'); });
    $all('.sidebar-brand-header .brand-info h4').forEach(function (el) { el.classList.add('ame-brand-name'); });
    $all('.login-card h2').forEach(function (el) { el.classList.add('ame-brand-name'); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { bind(); markLive(); });
  } else {
    bind();
    markLive();
  }
})();
