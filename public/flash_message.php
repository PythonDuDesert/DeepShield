<!-- Message Flash -->
<?php if (!empty($message)): ?>
    <div class="flash-message <?php echo htmlspecialchars($message_type); ?>" id="flashMessage">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <script>
        setTimeout(function() {
            document.getElementById('flashMessage').style.opacity = '0';
            setTimeout(function() {
                document.getElementById('flashMessage').remove();
            }, 300);
        }, 4000);
    </script>
<?php endif; ?>