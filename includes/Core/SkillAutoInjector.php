<?php

declare(strict_types=1);
/**
 * Auto-inject relevant skill content into the system prompt based on
 * the user's message. Mirrors the knowledge-base RAG injection pattern
 * but uses keyword matching instead of vector search.
 *
 * @package GratisAiAgent\Core
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Core;

use GratisAiAgent\Models\Skill;
use GratisAiAgent\Models\SkillUsageRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SkillAutoInjector {

	/**
	 * Maximum number of skills to inject per prompt to limit token usage.
	 */
	private const MAX_INJECTED_SKILLS = 2;

	/**
	 * Per-request dedup cache: tracks (session_id, skill_id) pairs already
	 * recorded this PHP request so we don't insert a row on every prompt
	 * rebuild (inject_for_message is called each iteration of the agent loop).
	 *
	 * Key: "{session_id}:{skill_id}", value: true.
	 *
	 * @var array<string, true>
	 */
	private static array $recorded_this_request = [];

	/**
	 * Keyword-to-skill trigger map.
	 *
	 * Keys are regex patterns matched against the user message (case-insensitive).
	 * Values are skill slugs from the skills table.
	 *
	 * @var array<string, string>
	 */
	private const TRIGGER_MAP = [
		'/\b(?:create|build|make|write|generate|add)\b.*\b(?:page|pages|post|posts|blog|article|content|landing|homepage|layout)\b/i' => 'gutenberg-blocks',
		'/\b(?:page|pages|landing|homepage|layout|column|columns|hero|section|block|blocks|gutenberg)\b/i'                            => 'gutenberg-blocks',
		'/\b(?:woocommerce|product|products|store|shop|order|orders|cart|checkout|coupon)\b/i'                                         => 'woocommerce',
		'/\b(?:seo|ranking|rankings|meta\s*tags?|meta\s*description|sitemap|search\s*engine|keyword|keywords)\b/i'                     => 'seo-optimization',
		'/\b(?:full\s*site\s*edit|fse|block\s*theme|template\s*part|site\s*editor|theme\.json)\b/i'                                    => 'full-site-editing',
		'/\b(?:multisite|network|subsite|subsites|sub-site)\b/i'                                                                       => 'multisite-management',
		'/\b(?:content\s*market|editorial|content\s*strateg|publish\s*schedule|content\s*audit)\b/i'                                    => 'content-marketing',
		'/\b(?:analytic|report|metric|dashboard|performance\s*report|growth)\b/i'                                                      => 'analytics-reporting',
		'/\b(?:debug|error|broken|fix|troubleshoot|white\s*screen|500|fatal|crash|slow)\b/i'                                           => 'site-troubleshooting',
	];

	/**
	 * Analyze the user message and return matching skill content to inject.
	 *
	 * When $session_id > 0 or $model_id is non-empty, each injected skill is
	 * recorded in the skill_usage table for telemetry.
	 *
	 * @param string $user_message The user's chat message.
	 * @param int    $session_id   Session ID for telemetry (0 = no session).
	 * @param string $model_id     Model ID for telemetry ('').
	 * @return string Formatted skill content for system prompt injection, or empty string.
	 */
	public static function inject_for_message( string $user_message, int $session_id = 0, string $model_id = '' ): string {
		if ( '' === trim( $user_message ) ) {
			return '';
		}

		$matched_slugs = self::match_skills( $user_message );

		if ( empty( $matched_slugs ) ) {
			return '';
		}

		$sections = [];

		foreach ( $matched_slugs as $slug ) {
			$skill = Skill::get_by_slug( $slug );

			if ( null === $skill || ! $skill->enabled ) {
				continue;
			}

			$content = $skill->content;

			if ( '' === $content ) {
				continue;
			}

			$sections[] = $content;

			// Record the auto-injection event for telemetry (once per session+skill per request).
			$dedup_key = "{$session_id}:{$skill->id}";
			if ( ! isset( self::$recorded_this_request[ $dedup_key ] ) ) {
				$injected_tokens = (int) ceil( mb_strlen( $content ) / 4 );
				SkillUsageRepository::create(
					$skill->id,
					$session_id,
					SkillUsageRepository::TRIGGER_AUTO,
					$injected_tokens,
					$model_id
				);
				self::$recorded_this_request[ $dedup_key ] = true;
			}
		}

		if ( empty( $sections ) ) {
			return '';
		}

		return "## Active Skill Guide\n"
			. 'The following skill guide has been auto-loaded based on your request. '
			. "Follow these instructions for the best results.\n\n"
			. implode( "\n\n---\n\n", $sections );
	}

	/**
	 * Match user message against the trigger map and return unique skill slugs.
	 *
	 * @param string $user_message The user's chat message.
	 * @return list<string> Matched skill slugs (max MAX_INJECTED_SKILLS).
	 */
	private static function match_skills( string $user_message ): array {
		$matched = [];

		foreach ( self::TRIGGER_MAP as $pattern => $slug ) {
			if ( in_array( $slug, $matched, true ) ) {
				continue;
			}

			if ( preg_match( $pattern, $user_message ) ) {
				$matched[] = $slug;

				if ( count( $matched ) >= self::MAX_INJECTED_SKILLS ) {
					break;
				}
			}
		}

		return $matched;
	}
}
