<?php

declare(strict_types=1);

use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Suite;

return Suite::make( 'standalone' )->add_case( EvaluationCase::make( 'inline' ) );
