<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Automattic\AiEvals\Evaluator\LlmJudge;
use Automattic\AiEvals\Evaluator\Rubric;
use Automattic\AiEvals\Exception\InvalidArgumentException;

final class RubricTest extends TestCase
{
    public function testBuildsNamedWeightedRubricItems(): void
    {
        $rubric = Rubric::make()
            ->item('accuracy', 'Facts match the reference.', 2.0, 0.8)
            ->item('tone', 'Tone is warm and concise.');
        $judge = new LlmJudge($rubric, 0.75);
        $items = $judge->getRubric()->getItems();

        self::assertTrue($judge->getRubric()->isMultiItem());
        self::assertSame(['accuracy', 'tone'], array_keys($items));
        self::assertSame(2.0, $items['accuracy']->getWeight());
        self::assertSame(0.8, $items['accuracy']->getMinimumScore());
    }

    public function testNormalizesAssociativeAndLegacyStringDefinitions(): void
    {
        $rubric = Rubric::from([
            'grounding' => [
                'criteria' => 'Claims are supported.',
                'weight' => 3,
                'label' => 'Groundedness',
            ],
            'clarity' => 'The answer is easy to understand.',
        ]);
        $legacy = Rubric::from('The answer is correct.');

        self::assertSame('Groundedness', $rubric->getItems()['grounding']->getLabel());
        self::assertSame(['overall'], array_keys($legacy->getItems()));
    }

    public function testRejectsDuplicateItems(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rubric::make()->item('accuracy', 'First.')->item('accuracy', 'Second.');
    }
}
