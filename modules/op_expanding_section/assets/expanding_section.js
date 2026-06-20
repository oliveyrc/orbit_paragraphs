 (function () {
    'use strict';

    function initToggle(trigger) {
      const panelId = trigger.getAttribute('aria-controls');
      const panel = document.getElementById(panelId);
      const outer = panel.closest('.toggle-panel-outer');

      function setOpen(isOpen) {
        trigger.setAttribute('aria-expanded', String(isOpen));
        outer.setAttribute('data-open', String(isOpen));
      }

      function toggle() {
        const currentlyOpen = trigger.getAttribute('aria-expanded') === 'true';
        setOpen(!currentlyOpen);
      }

      // Mouse / pointer / touch all fire 'click' on a button.
      trigger.addEventListener('click', toggle);

      // Explicit keyboard support as a safety net.
      // Native <button> already triggers 'click' on Enter and Space,
      // but we guard here in case the element type ever changes,
      // and to prevent page scroll on Space in older browsers.
      trigger.addEventListener('keydown', function (event) {
        if (event.key === ' ' || event.key === 'Spacebar') {
          event.preventDefault();
          toggle();
        } else if (event.key === 'Enter') {
          // Enter already triggers click natively on buttons; no need to call toggle() again.
        }
      });
    }

    document.querySelectorAll('.toggle-trigger').forEach(initToggle);
  })();
