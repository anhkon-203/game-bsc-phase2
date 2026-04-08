<?php

// ob_start();
get_header();
// $header_output = ob_get_clean();
$index_file = GAME_BSC_PLUGIN_URL . '/assets/front-end/index.html';
?>

<iframe
    src="<?php echo $index_file; ?>"
    style="width: 100%; height: 100vh; border: none; overflow:hidden" > </iframe>
<?php
get_footer();