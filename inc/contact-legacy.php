<?php
/** Contact page #372 compatibility. */
defined( 'ABSPATH' ) || exit;
function nt_contact_is_legacy(): bool {
	return is_page( 372 );
}
