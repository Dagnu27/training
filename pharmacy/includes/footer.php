<?php
// Prevent direct access
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    exit('❌ Direct access not allowed.');
}
?>

</main>

<footer class="bg-light py-3 mt-auto border-top">
    <div class="container text-center text-muted small">
        &copy; <?= date('Y') ?> PharmaSys. All rights reserved.  
        <span class="d-none d-md-inline">• Secure • HIPAA-aligned practices</span>
    </div>
</footer>

<!-- Core JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>

<!-- Optional: Debug info (dev only) -->
<?php if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1'): ?>
<script>
console.info('✅ Debug mode: Connected. Session:', <?= json_encode($_SESSION ?? []) ?>);
</script>
<?php endif; ?>

</body>
</html>