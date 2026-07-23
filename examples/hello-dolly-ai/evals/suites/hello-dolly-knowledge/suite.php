<?php

declare(strict_types=1);

use Automattic\AiEvals\Suite;

return Suite::make( 'hello-dolly-knowledge', 'Hello Dolly — Knowledge contracts' )
	->describe( 'Fast, deterministic checks for curated facts and WordPress Ability contracts.' );
