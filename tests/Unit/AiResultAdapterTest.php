<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Automattic\AiEvals\Task\AiResultAdapter;

final class AiResultAdapterTest extends TestCase
{
    public function testReadsTextFromAResultThatExposesToText(): void
    {
        $result = new class {
            public function toText(): string
            {
                return 'Hello Dolly';
            }
        };

        $adapted = AiResultAdapter::adapt($result, 'text');

        self::assertSame('Hello Dolly', $adapted->getOutput());
    }

    public function testCoercesAStringableTextResultInsteadOfReturningAMessage(): void
    {
        $result = new class {
            /** @return object */
            public function toMessage()
            {
                return new \stdClass();
            }

            public function __toString(): string
            {
                return 'stringified answer';
            }
        };

        $adapted = AiResultAdapter::adapt($result, 'text');

        self::assertIsString($adapted->getOutput());
        self::assertSame('stringified answer', $adapted->getOutput());
    }

    public function testCapturesProviderModelAndTokenMetadata(): void
    {
        $result = new class {
            public function toText(): string
            {
                return 'answer';
            }

            /** @return object */
            public function getProviderMetadata()
            {
                return new class {
                    public function getId(): string
                    {
                        return 'test-provider';
                    }
                };
            }

            /** @return object */
            public function getModelMetadata()
            {
                return new class {
                    public function getId(): string
                    {
                        return 'test-model';
                    }
                };
            }

            /** @return object */
            public function getTokenUsage()
            {
                return new class {
                    public function getPromptTokens(): int
                    {
                        return 5;
                    }

                    public function getCompletionTokens(): int
                    {
                        return 7;
                    }

                    public function getTotalTokens(): int
                    {
                        return 12;
                    }

                    public function getThoughtTokens(): int
                    {
                        return 0;
                    }
                };
            }
        };

        $adapted = AiResultAdapter::adapt($result, 'text');
        $metadata = $adapted->getMetadata();

        self::assertSame('test-provider', $metadata['provider']);
        self::assertSame('test-model', $metadata['model']);
        self::assertSame(12, $metadata['tokens']['total']);
    }
}
