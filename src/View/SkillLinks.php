<?php

declare(strict_types=1);

namespace Rodokmen\Site\View;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

/**
 * Links in the skill's texts (stories, interesting facts) point to the pages of the skill's own website:
 * /osoba/<slug>/, /zdroj/<key>/, /rod/<slug>/, /rodina/<id>/. Here the addresses have no trailing slash,
 * a register entry is /zapis/<key> and families have no page of their own (the link is dropped, its text kept).
 */
final class SkillLinks
{
	/** First path segment on the skill's website => the same page here. */
	private const Renamed = ['zdroj' => 'zapis'];

	/** Pages the skill's website has and this one does not. */
	private const Missing = ['rodina'];

	/** Single pages of the skill's website => their counterpart here (the list of lines is on the home page). */
	private const Moved = ['rody' => '/'];

	public function __invoke(DocumentParsedEvent $event): void
	{
		$links = [];
		foreach ($event->getDocument()->iterator() as $node) {
			if ($node instanceof Link) {
				$links[] = $node;
			}
		}

		foreach ($links as $link) {
			$url = self::rewrite($link->getUrl());
			if ($url !== null) {
				$link->setUrl($url);

				continue;
			}
			foreach ($link->children() as $child) {
				$link->insertBefore($child);
			}
			$link->detach();
		}
	}

	/** The address on this website; null when it has no such page. Other than local links are kept. */
	public static function rewrite(string $url): ?string
	{
		if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
			return $url;
		}

		$end = strcspn($url, '?#');
		$segments = explode('/', trim(substr($url, 0, $end), '/'));
		$rest = substr($url, $end);
		if (in_array($segments[0], self::Missing, true) && count($segments) > 1) {
			return null;
		}
		if (count($segments) === 1 && isset(self::Moved[$segments[0]])) {
			return self::Moved[$segments[0]] . $rest;
		}
		$segments[0] = self::Renamed[$segments[0]] ?? $segments[0];

		return '/' . implode('/', $segments) . $rest;
	}
}
