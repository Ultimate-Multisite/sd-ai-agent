<?php

declare(strict_types=1);

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\ConversationTrimmer;
use WP_UnitTestCase;

/** Tests serialized-history compaction without requiring the AI Client SDK. */
class ConversationTrimmerSerializedTest extends WP_UnitTestCase {

	/** Compaction retains reusable IDs from ability-call resource envelopes. */
	public function test_preserves_resource_receipts(): void {
		$messages = array(
			array(
				'role'  => 'model',
				'parts' => array(
					array(
						'functionCall' => array(
							'name' => 'wpab__sd-ai-agent__ability-call',
							'args' => array(
								'ability'   => 'sd-ai-agent/stock-image',
								'arguments' => array( 'keyword' => 'newlywed couple', 'usage' => 'hero' ),
							),
						),
					),
				),
			),
			array(
				'role'  => 'user',
				'parts' => array(
					array(
						'functionResponse' => array(
							'name'     => 'wpab__sd-ai-agent__ability-call',
							'response' => array(
								'ability' => 'sd-ai-agent/stock-image',
								'success' => true,
								'result'  => array(
									'attachment_id' => 49,
									'title'         => 'Wedding portrait',
									'source'        => 'openverse',
									'attribution'   => 'Licensed under CC BY 2.0',
									'url'           => 'https://private.example/uploads/image.jpg',
									'secret'        => 'SECRET_RESOURCE_VALUE',
								),
							),
						),
					),
				),
			),
		);

		$result = ConversationTrimmer::compact_serialized_history( $messages, 2048, 512 );
		$text   = (string) $result['messages'][0]['parts'][0]['text'];

		$this->assertStringContainsString( 'sd-ai-agent/stock-image', $text );
		$this->assertStringContainsString( 'newlywed couple', $text );
		$this->assertStringContainsString( '"attachment_id":49', $text );
		$this->assertStringContainsString( 'Licensed under CC BY 2.0', $text );
		$this->assertStringNotContainsString( 'SECRET_RESOURCE_VALUE', $text );
		$this->assertStringNotContainsString( 'private.example', $text );
	}

	/** Chunked compaction keeps both sides of a persisted tool cycle in order. */
	public function test_chunked_compaction_preserves_complete_tool_cycles(): void {
		$history = array(
			array(
				'role'  => 'user',
				'parts' => array( array( 'text' => 'Check the site before changing it.' ) ),
			),
			array(
				'role'  => 'model',
				'parts' => array(
					array(
						'functionCall' => array(
							'name' => 'sd-ai-agent/site-info',
							'args' => array( 'scope' => 'summary' ),
						),
					),
				),
			),
			array(
				'role'  => 'user',
				'parts' => array(
					array(
						'functionResponse' => array(
							'name'     => 'sd-ai-agent/site-info',
							'response' => array( 'name' => 'Example Site' ),
						),
					),
				),
			),
			array(
				'role'  => 'user',
				'parts' => array( array( 'text' => 'Continue with the maintenance plan.' ) ),
			),
		);

		$encoded = (string) wp_json_encode( $history );
		$chunks  = str_split( $encoded, 17 );
		$result  = ConversationTrimmer::compact_serialized_history_chunks( $chunks, 4096, 1024 );
		$text    = (string) $result['messages'][0]['parts'][0]['text'];

		$this->assertTrue( $result['meta']['stream_complete'] );
		$this->assertTrue( $result['meta']['stream_valid'] );
		$this->assertStringContainsString( '[tool call: sd-ai-agent/site-info]', $text );
		$this->assertStringContainsString( '[tool result omitted: sd-ai-agent/site-info]', $text );
		$this->assertLessThan(
			strpos( $text, '[tool result omitted: sd-ai-agent/site-info]' ),
			strpos( $text, '[tool call: sd-ai-agent/site-info]' )
		);
	}

	/** A 60 MB historical fixture is compacted from bounded JSON slices. */
	public function test_chunked_compaction_handles_a_60_mb_history_without_materializing_it(): void {
		$message_count = 18759;
		$chunks        = static function () use ( $message_count ): \Generator {
			yield '[';
			for ( $index = 0; $index < $message_count; ++$index ) {
				$encoded = (string) wp_json_encode(
					array(
						'role'  => 'user',
						'parts' => array(
							array( 'text' => 'legacy-message-' . $index . ' ' . str_repeat( 'x', 3400 ) ),
						),
					)
				);
				$encoded = ( 0 === $index ? '' : ',' ) . $encoded;
				for ( $offset = 0, $length = strlen( $encoded ); $offset < $length; $offset += 4096 ) {
					yield substr( $encoded, $offset, 4096 );
				}
			}
			yield ']';
		};

		$result = ConversationTrimmer::compact_serialized_history_chunks( $chunks(), 8192, 2048 );
		$text   = (string) $result['messages'][0]['parts'][0]['text'];

		$this->assertTrue( $result['meta']['stream_complete'] );
		$this->assertTrue( $result['meta']['stream_valid'] );
		$this->assertSame( $message_count, $result['meta']['source_message_count'] );
		$this->assertLessThanOrEqual( 8192, $result['meta']['estimated_bytes'] );
		$this->assertStringContainsString( 'legacy-message-18758', $text );
	}
}
