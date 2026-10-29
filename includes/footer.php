    </main>
</div>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<?php if (!empty($extra_js) && is_array($extra_js)): ?>
    <?php foreach ($extra_js as $js): ?>
        <script src="<?= BASE_URL ?>assets/js/<?= e($js) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>