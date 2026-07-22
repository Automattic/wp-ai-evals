<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\Loader\DirectoryLoader;

final class Registry
{
    /** @var array<string, Suite> */
    private array $suites = [];

    public function register(Suite $suite): self
    {
        $id = $suite->getId();

        if (isset($this->suites[$id])) {
            throw new InvalidArgumentException(sprintf('The eval suite "%s" is already registered.', $id));
        }

        $this->suites[$id] = $suite;

        return $this;
    }

    public function loadDirectory(string $directory): self
    {
        return (new DirectoryLoader())->load($this, $directory);
    }

    public function has(string $id): bool
    {
        return isset($this->suites[$id]);
    }

    public function get(string $id): Suite
    {
        if (!$this->has($id)) {
            throw new InvalidArgumentException(sprintf('Unknown eval suite "%s".', $id));
        }

        return $this->suites[$id];
    }

    /** @return array<string, Suite> */
    public function all(): array
    {
        return $this->suites;
    }

    /** @return list<string> */
    public function tags(): array
    {
        $tags = [];

        foreach ($this->suites as $suite) {
            foreach ($suite->getCases() as $case) {
                foreach ($case->getTags() as $tag) {
                    $tags[$tag] = true;
                }
            }
        }

        $result = array_keys($tags);
        sort($result);

        return array_values($result);
    }
}
