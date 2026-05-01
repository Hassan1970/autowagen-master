</main>

<footer class="text-center text-muted py-4 small">
  &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; v1.0
  <?php if (defined('APP_DEBUG') && APP_DEBUG): ?>
    &middot; <span class="badge bg-warning text-dark">DEBUG</span>
  <?php endif; ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
