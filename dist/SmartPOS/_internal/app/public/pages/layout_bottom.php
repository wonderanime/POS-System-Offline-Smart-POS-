</div><!-- .content -->
</div><!-- .main -->
</div><!-- .shell -->
<script src="/assets/js/main.js"></script>
<?php if (!empty($pageScripts)): foreach((array)$pageScripts as $sc): ?>
<script src="<?= htmlspecialchars($sc) ?>"></script>
<?php endforeach; endif; ?>
<?php if (!empty($inlineScript)): ?>
<script><?= $inlineScript ?></script>
<?php endif; ?>
</body>
</html>
