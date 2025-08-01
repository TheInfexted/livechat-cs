<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Live Chat System' ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/chat.css') ?>">
</head>
<body>
    <?= $this->renderSection('content') ?>
    
    <script src="<?= base_url('assets/js/chat.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>