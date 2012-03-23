<?php
/**
 * 
 */

$url = elgg_get_site_url() . 'googleplus_api/forward';
$img_url = elgg_get_site_url() . 'mod/googleplus_api/graphics/googleplus_sign_in.png';

$login = <<<__HTML
<div id="login_with_googleplus">
	<a href="$url">
		<img src="$img_url" alt="googleplus" />
	</a>
</div>
__HTML;

echo $login;
