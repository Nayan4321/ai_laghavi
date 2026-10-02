(function () {
  'use strict';

  // Confirm destructive buttons.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-confirm]');
    if (btn && !window.confirm(btn.getAttribute('data-confirm'))) {
      e.preventDefault();
    }
  });

  // Keep width, height and aspect ratio in step on the generate form.
  var form = document.getElementById('gen-form');
  if (form) {
    var aspect = document.getElementById('aspect');
    var width = document.getElementById('width');
    var height = document.getElementById('height');
    var note = document.getElementById('ratio-note');

    var gcd = function (a, b) { while (b) { var t = b; b = a % b; a = t; } return a || 1; };
    var even = function (n) { n = Math.round(n); return n % 2 ? n + 1 : n; };

    var update = function (source) {
      var w = parseInt(width.value, 10);
      var h = parseInt(height.value, 10);
      if (aspect.value !== 'custom' && source !== 'height' && w > 0) {
        var p = aspect.value.split(':');
        h = even(w * p[1] / p[0]);
        height.value = h;
      }
      if (w > 0 && h > 0) {
        var g = gcd(w, h);
        note.textContent = 'Output: ' + w + ' × ' + h + ' px (ratio ' + (w / g) + ':' + (h / g) + ')';
      }
    };

    aspect.addEventListener('change', function () { update('aspect'); });
    width.addEventListener('input', function () { update('width'); });
    height.addEventListener('input', function () {
      // Typing a height means the user wants their own shape.
      aspect.value = 'custom';
      update('height');
    });
    update('init');

    form.addEventListener('submit', function (e) {
      var files = form.querySelector('input[name="images[]"]');
      var max = parseInt(files.getAttribute('data-max-files'), 10);
      if (files.files.length > max) {
        e.preventDefault();
        window.alert('Attach at most ' + max + ' reference images.');
        return;
      }
      form.querySelector('button').disabled = true;
    });
  }

  // While videos are in progress, check every 10 seconds and reload when one changes.
  var jobs = document.getElementById('jobs');
  if (jobs) {
    var pending = function () {
      return jobs.querySelectorAll('[data-status="queued"], [data-status="processing"]');
    };
    var poll = function () {
      if (!pending().length) { return; }
      fetch('job_status.php', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data) { return; }
          var changed = data.jobs.some(function (j) {
            var el = jobs.querySelector('[data-id="' + j.id + '"]');
            return el && el.getAttribute('data-status') !== j.status;
          });
          if (changed) { window.location.reload(); } else { setTimeout(poll, 10000); }
        })
        .catch(function () { setTimeout(poll, 30000); });
    };
    setTimeout(poll, 10000);
  }
})();
