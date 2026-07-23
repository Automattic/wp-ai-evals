<?php

declare(strict_types=1);

use Automattic\AiEvals\Suite;

return Suite::make( 'hello-dolly-agent', 'Hello Dolly — Live agent quality' )
	->describe( 'Connector-backed prompt, grounding, safety, and conversational quality checks.' );
