<?php
/**
 * @var string      $title
 * @var string|null $subtitle
 * @var string|null $actions  HTML tepercaya dari view pemanggil
 */
$subtitle = isset($subtitle) && is_string($subtitle) ? $subtitle : '';
$actions  = isset($actions) && is_string($actions) ? $actions : '';
?>
<div class="page-head">
    <div>
        <h1><?= esc((string) $title) ?></h1>
        <?php if ($subtitle !== ''): ?><div class="sub"><?= esc($subtitle) ?></div><?php endif; ?>
    </div>
    <?php if ($actions !== ''): ?><div class="d-flex gap-2 flex-wrap"><?= $actions ?></div><?php endif; ?>
</div>
