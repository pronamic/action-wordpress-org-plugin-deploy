<?php

echo '😏';
echo getenv( 'GITHUB_REPOSITORY' );
echo '🤔';

$repository = getenv( 'REPOSITORY' );

passthru( "gh release download --pattern 'pronamic-pay-with-rabo-smart-pay-for-woocommerce.1.0.0.zip' --repo $repository" );
