<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Production Builder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-5">
    <h1 class="mb-4">Production Builder</h1>

    <form method="post" class="d-flex gap-2">
        <input type="text" name="prompt" class="form-control" placeholder="Describe what to build…" required>
        <button class="btn btn-primary">Go</button>
    </form>

    <?php if (isset($output) && $output): ?>
        <h3 class="mt-5">CLI Output</h3>
        <pre class="bg-light p-3 border rounded small"><?= esc($output) ?></pre>
    <?php endif; ?>

    <?php if (isset($zipUrl) && $zipUrl): ?>
        <div class="alert alert-success mt-4">
            Your project is ready! <a href="<?= esc($zipUrl) ?>" class="alert-link">Download ZIP</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html> 