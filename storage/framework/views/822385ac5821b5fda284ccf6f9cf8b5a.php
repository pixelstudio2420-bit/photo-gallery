<?php if(!empty($sourceProtection) && !empty($sourceProtection['enabled'])): ?>
<style>
img {
  user-select: none;
  -webkit-user-drag: none;
  pointer-events: none;
}
</style>
<script>
(function () {
  'use strict';

  var sp = {
    disableRightclick: <?php echo e(!empty($sourceProtection['sp_disable_rightclick']) ? 'true' : 'false'); ?>,
    disableDevtools:  <?php echo e(!empty($sourceProtection['sp_disable_devtools'])  ? 'true' : 'false'); ?>,
    disableViewsource: <?php echo e(!empty($sourceProtection['sp_disable_viewsource']) ? 'true' : 'false'); ?>,
    disableCopy:    <?php echo e(!empty($sourceProtection['sp_disable_copy'])    ? 'true' : 'false'); ?>,
    disableDrag:    <?php echo e(!empty($sourceProtection['sp_disable_drag'])    ? 'true' : 'false'); ?>,
    consoleWarning:  <?php echo e(!empty($sourceProtection['sp_console_warning'])  ? 'true' : 'false'); ?>

  };

  // Right-click disable
  if (sp.disableRightclick) {
    document.addEventListener('contextmenu', function (e) {
      e.preventDefault();
      return false;
    });
  }

  // Copy disable
  if (sp.disableCopy) {
    document.addEventListener('copy', function (e) {
      e.preventDefault();
      return false;
    });
    document.addEventListener('selectstart', function (e) {
      e.preventDefault();
      return false;
    });
  }

  // Image drag disable
  if (sp.disableDrag) {
    document.addEventListener('dragstart', function (e) {
      if (e.target && e.target.tagName === 'IMG') {
        e.preventDefault();
        return false;
      }
    });
  }

  // Keyboard shortcut blocking (Ctrl+U, F12, Ctrl+Shift+I, Ctrl+S)
  if (sp.disableViewsource) {
    document.addEventListener('keydown', function (e) {
      var key = e.key || e.keyCode;

      // F12
      if (key === 'F12' || e.keyCode === 123) {
        e.preventDefault();
        return false;
      }

      // Ctrl+U (view source)
      if ((e.ctrlKey || e.metaKey) && (key === 'u' || key === 'U' || e.keyCode === 85)) {
        e.preventDefault();
        return false;
      }

      // Ctrl+Shift+I (devtools)
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && (key === 'i' || key === 'I' || e.keyCode === 73)) {
        e.preventDefault();
        return false;
      }

      // Ctrl+S (save page)
      if ((e.ctrlKey || e.metaKey) && (key === 's' || key === 'S' || e.keyCode === 83)) {
        e.preventDefault();
        return false;
      }
    });
  }

  // DevTools detection via window size heuristic + Firebug check
  if (sp.disableDevtools) {
    var devtoolsOpen = false;

    // Firebug detection
    if (typeof window.console !== 'undefined' && typeof window.console.firebug !== 'undefined') {
      devtoolsOpen = true;
    }

    // Window size threshold heuristic
    var threshold = 160;
    function checkDevtools() {
      var widthDiff = window.outerWidth - window.innerWidth;
      var heightDiff = window.outerHeight - window.innerHeight;
      if (widthDiff > threshold || heightDiff > threshold) {
        if (!devtoolsOpen) {
          devtoolsOpen = true;
          document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;font-size:1.5rem;">Developer tools are not allowed on this page.</div>';
        }
      } else {
        devtoolsOpen = false;
      }
    }

    setInterval(checkDevtools, 1000);
  }

  // Console warning
  if (sp.consoleWarning) {
    var warningStyle = 'color: red; font-size: 24px; font-weight: bold;';
    var bodyStyle  = 'color: black; font-size: 14px;';
    console.log('%c\u26a0 WARNING!', warningStyle);
    console.log(
      '%cThis browser feature is intended for developers. If someone told you to copy/paste something here, they may be trying to compromise your account.',
      bodyStyle
    );
  }

}());
</script>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/partials/source-shield.blade.php ENDPATH**/ ?>