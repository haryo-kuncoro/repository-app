<?php
$session = session();
$types   = ['success', 'error', 'warning', 'info'];
$attrs   = '';
foreach ($types as $t) {
    $msg = $session->getFlashdata($t);
    if (is_string($msg) && $msg !== '') {
        $attrs .= ' data-' . $t . '="' . esc($msg, 'attr') . '"';
    }
}
?>
<div id="flash" hidden<?= $attrs ?>></div>
