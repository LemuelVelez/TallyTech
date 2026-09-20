<?php
$toastTypes = [
    'success' => ['label' => 'Success', 'icon' => 'check-circle', 'role' => 'status'],
    'error' => ['label' => 'Error', 'icon' => 'alert-triangle', 'role' => 'alert'],
    'warning' => ['label' => 'Warning', 'icon' => 'alert-triangle', 'role' => 'alert'],
    'info' => ['label' => 'Notice', 'icon' => 'circle', 'role' => 'status'],
];
$toasts = [];
foreach ($toastTypes as $type => $meta) {
    $message = session()->getFlashdata($type);
    if (is_scalar($message) && trim((string) $message) !== '') {
        $toasts[] = ['type' => $type, 'message' => trim((string) $message)] + $meta;
    }
}
?>
<?php if ($toasts): ?>
<div class="toast-stack" data-toast-stack aria-live="polite" aria-atomic="false">
    <?php foreach ($toasts as $toast): ?>
        <div class="toast toast-<?= esc($toast['type'], 'attr') ?>" role="<?= esc($toast['role'], 'attr') ?>" data-toast data-dismiss-after="5000">
            <span class="toast-icon" aria-hidden="true"><?= ui_icon($toast['icon']) ?></span>
            <div class="toast-copy">
                <strong><?= esc($toast['label']) ?></strong>
                <span><?= esc($toast['message']) ?></span>
            </div>
            <button class="toast-close" type="button" data-toast-close aria-label="Dismiss notification"><?= ui_icon('x') ?></button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
