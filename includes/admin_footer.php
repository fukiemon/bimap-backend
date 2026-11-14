</div><!-- /page-body -->
  </div><!-- /main-content -->
</div><!-- /layout -->

<script>
(function () {
  var sidebar  = document.querySelector('.sidebar');
  var backdrop = document.getElementById('sidebarBackdrop');
  var toggle   = document.getElementById('menuToggle');
  if (!sidebar || !backdrop || !toggle) return;

  function setOpen(open) {
    sidebar.classList.toggle('open', open);
    backdrop.classList.toggle('show', open);
    document.body.classList.toggle('no-scroll', open);
  }

  toggle.addEventListener('click', function () {
    setOpen(!sidebar.classList.contains('open'));
  });
  backdrop.addEventListener('click', function () { setOpen(false); });
  sidebar.querySelectorAll('a').forEach(function (a) {
    a.addEventListener('click', function () { setOpen(false); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setOpen(false);
  });
  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) setOpen(false);
  });
})();
</script>
</body>
</html>
