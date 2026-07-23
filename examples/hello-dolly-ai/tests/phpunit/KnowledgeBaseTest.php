<?php

declare(strict_types=1);

namespace HelloDollyAI\Tests;

use HelloDollyAI\KnowledgeBase;
use PHPUnit\Framework\TestCase;

final class KnowledgeBaseTest extends TestCase {

	public function testBirthFactIsGroundedAndAttributed(): void {
		$fact = KnowledgeBase::fact( 'birth' );

		self::assertStringContainsString( 'Locust Ridge', $fact['answer'] );
		self::assertStringContainsString( 'January 19, 1946', $fact['answer'] );
		self::assertStringStartsWith( 'https://', $fact['source'] );
	}

	public function testTimelineCanSelectADecade(): void {
		$timeline = KnowledgeBase::timeline( '1970s' );

		self::assertNotEmpty( $timeline['events'] );
		foreach ( $timeline['events'] as $event ) {
			self::assertGreaterThanOrEqual( 1970, $event['year'] );
			self::assertLessThan( 1980, $event['year'] );
		}
	}

	public function testSongLookupNormalizesTitles(): void {
		$song = KnowledgeBase::song( 'I Will Always Love You' );

		self::assertSame( 'i-will-always-love-you', $song['slug'] );
		self::assertSame( 1974, $song['year'] );
		self::assertStringNotContainsString( "\n", $song['theme'] );
	}

	public function testUnknownKnowledgeIsExplicit(): void {
		self::assertStringContainsString( 'No curated fact', KnowledgeBase::fact( 'unknown' )['answer'] );
		self::assertSame( 0, KnowledgeBase::song( 'unknown' )['year'] );
	}
}
