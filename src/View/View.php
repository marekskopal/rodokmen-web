<?php

declare(strict_types=1);

namespace Rodokmen\Site\View;

use Laminas\Diactoros\Response\HtmlResponse;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Psr\Http\Message\ResponseInterface;
use Rodokmen\Site\Config;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Model\Story;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Data\Stories;
use Rodokmen\Site\Image\Thumbnails;
use Stringable;
use Throwable;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const EXTR_SKIP;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Renders plain PHP templates inside the common layout and offers small HTML helpers.
 */
final class View
{
	private ?MarkdownConverter $markdown = null;

	public function __construct(
		private readonly Config $config,
		private readonly Repository $repository,
		private readonly Stories $stories,
		private readonly Thumbnails $thumbnails,
	) {
	}

	/** @param array<string, mixed> $params */
	public function page(string $template, array $params = [], int $status = 200): ResponseInterface
	{
		$params['content'] = $this->render($template, $params);

		return new HtmlResponse($this->render('layout', $params), $status);
	}

	/** @param array<string, mixed> $params */
	public function render(string $template, array $params = []): string
	{
		$file = $this->config->rootDir . '/templates/' . $template . '.php';
		$params['v'] = $this;
		$params['repo'] = $this->repository;

		return (static function (string $__file, array $__params): string {
			extract($__params, EXTR_SKIP);
			ob_start();

			try {
				require $__file;
			} catch (Throwable $e) {
				// Drop the half-rendered output, otherwise it would leak before the error page.
				ob_end_clean();

				throw $e;
			}

			return (string) ob_get_clean();
		})($file, $params);
	}

	public function e(mixed $value): string
	{
		return htmlspecialchars(
			is_scalar($value) || $value instanceof Stringable ? (string) $value : '',
			ENT_QUOTES | ENT_SUBSTITUTE,
			'UTF-8',
		);
	}

	public function markdown(string $text): string
	{
		if ($this->markdown === null) {
			$environment = new Environment(['html_input' => 'escape', 'allow_unsafe_links' => false]);
			$environment->addExtension(new CommonMarkCoreExtension());
			$environment->addExtension(new GithubFlavoredMarkdownExtension());
			$environment->addEventListener(DocumentParsedEvent::class, new SkillLinks());
			$this->markdown = new MarkdownConverter($environment);
		}

		return (string) $this->markdown->convert($text);
	}

	/** Example for the search field (SITE_SEARCH_HINT), so that it can name the family's own surnames and places. */
	public function searchHint(): string
	{
		return $this->config->searchHint !== '' ? $this->config->searchHint : 'Např. příjmení, obec, řemeslo…';
	}

	public function personUrl(string $slug): string
	{
		return '/osoba/' . rawurlencode($slug);
	}

	public function explorerUrl(string $slug): string
	{
		return '/predci/' . rawurlencode($slug);
	}

	public function imageUrl(string $key, bool $full = false): string
	{
		return '/obrazek/' . ($full ? 'plny' : 'nahled') . '/' . rawurlencode($key) . '.jpg';
	}

	/**
	 * ` srcset="…" sizes="…"` with the WebP copies of img/{key}.jpg, or '' when there are none (no WebP support, no
	 * source, a source too large to decode). Each candidate is described by its real width: a source narrower than a width is served at its own.
	 *
	 * @param string $sizes the image's displayed width, e.g. "(max-width: 860px) 100vw, 800px"
	 */
	public function srcset(string $key, string $sizes): string
	{
		$source = $this->thumbnails->resizableSize($key);
		if ($source === null) {
			return '';
		}

		$candidates = [];
		foreach (Thumbnails::Widths as $width) {
			$real = min($width, $source->width);
			$candidates[$real] ??= '/obrazek/nahled/' . $width . '/' . rawurlencode($key) . '.webp ' . $real . 'w';
		}

		return ' srcset="' . $this->e(implode(', ', $candidates)) . '" sizes="' . $this->e($sizes) . '"';
	}

	/** Link to a person, with the line colour dot. */
	public function personLink(?string $slug, ?string $fallback = null): string
	{
		$person = $this->repository->person($slug);
		if ($person === null) {
			return '<span class="muted">' . $this->e($fallback ?? 'neznámý') . '</span>';
		}

		return '<a class="plink" href="' . $this->e($this->personUrl($person->slug)) . '">'
			. $this->lineDot($person->line)
			. $this->e($person->name) . '</a>';
	}

	public function lineDot(?int $k): string
	{
		if ($k === null) {
			return '';
		}

		return '<span class="dot" style="--c:' . $this->e($this->repository->lineColor($k)) . '"></span>';
	}

	public function status(Person $person): string
	{
		$labels = ['doc' => 'doloženo', 'prob' => 'pravděpodobné', 'fam' => 'od rodiny', 'mh' => 'cizí strom', 'unk' => 'neznámé'];
		$st = $person->st;

		return '<span class="badge badge-' . $this->e($st) . '" title="' . $this->e($person->status) . '">'
			. $this->e($labels[$st] ?? $st) . '</span>';
	}

	/**
	 * Small coloured shape for person cards (explorer, tree, legend): the card border colour
	 * already means the family line, so the documentation status needs its own mark.
	 */
	public function statusMark(string $st): string
	{
		$labels = ['doc' => 'doloženo matrikou', 'prob' => 'pravděpodobné', 'fam' => 'údaj od rodiny', 'mh' => 'jen z cizího rodokmenu'];
		if (!isset($labels[$st])) {
			return '';
		}

		return '<span class="stm stm-' . $st . '" role="img" title="' . $labels[$st] . '" aria-label="' . $labels[$st] . '"></span>';
	}

	/** Lifespan for display: "1896 – ?" style, without the leading birth star for living people. */

	public function years(Person $person): string
	{
		return $person->y;
	}

	/**
	 * Splits a fact into an event type and text for the timeline.
	 *
	 * @return array{type: string, label: string, text: string}
	 */
	public function fact(string $fact): array
	{
		$fact = trim($fact);
		$map = [
			'*' => ['birth', 'Narození'],
			'×' => ['marriage', 'Sňatek'],
			'†' => ['death', 'Úmrtí'],
			'⚭' => ['marriage', 'Sňatek'],
		];
		$first = mb_substr($fact, 0, 1);
		if (isset($map[$first])) {
			return ['type' => $map[$first][0], 'label' => $map[$first][1], 'text' => trim(mb_substr($fact, 1))];
		}

		if (preg_match('/^(pokřt|křest)/u', $fact) === 1) {
			return ['type' => 'birth', 'label' => 'Křest', 'text' => $fact];
		}

		return ['type' => 'info', 'label' => '', 'text' => $fact];
	}

	/**
	 * The facts of a person for the timeline, from birth to death: dated facts by their date (in one year births
	 * first, then other facts, deaths and burials last), undated births and baptisms with the first dated birth (or
	 * at the start), undated deaths at the end and other undated facts (a trade) right after the fact they follow in
	 * the data (or after the birth), but before a death.
	 *
	 * @param list<string> $facts
	 * @return list<array{type: string, label: string, text: string}>
	 */
	public function timeline(array $facts): array
	{
		$ranks = ['birth' => 0, 'marriage' => 1, 'info' => 1, 'death' => 2];
		$parsed = [];
		$birth = null;
		foreach ($facts as $text) {
			$fact = $this->fact($text);
			$date = $this->factDate($text);
			if ($fact['type'] === 'birth' && $date !== null && ($birth === null || $date < $birth)) {
				$birth = $date;
			}

			$parsed[] = [$fact, $date];
		}

		[$year, $day] = $birth ?? [PHP_INT_MIN, 0];
		$entries = [];
		foreach ($parsed as $i => [$fact, $date]) {
			// A burial follows the death even when it is not a death fact itself.
			$rank = preg_match('/^pohřb/iu', $fact['text']) === 1 ? 3 : ($ranks[$fact['type']] ?? 1);
			$key = match (true) {
				$date !== null => [$date[0], $rank, $date[1]],
				$fact['type'] === 'birth' => [$birth[0] ?? PHP_INT_MIN, 0, $birth[1] ?? 0],
				$fact['type'] === 'death' => [PHP_INT_MAX, 2, 0],
				// Never after a death in the same year: the person was alive for it.
				default => [$year, 1, $day],
			};
			[$year, , $day] = $key;
			// An undated fact comes after the dated one it shares its place with.
			$entries[] = ['key' => [...$key, $date === null ? 1 : 0, $i], 'fact' => $fact];
		}

		usort($entries, static fn (array $a, array $b): int => $a['key'] <=> $b['key']);

		return array_column($entries, 'fact');
	}

	/**
	 * The first date mentioned in a fact ("23. 4. 1746", "3/1867", "1746") as [year, month * 100 + day], with 0 for
	 * an unknown month or day, or null. House numbers ("čp. 1850") are not years.
	 *
	 * @return array{int, int}|null
	 */
	public function factDate(string $fact): ?array
	{
		$fact = (string) preg_replace('/\bčp\.?\s*\d+/u', '', $fact);
		$pattern = '/\b(?:(\d{1,2})\.\s*(\d{1,2})\.\s*|(\d{1,2})\/)?(1[5-9]\d\d|20\d\d)\b/u';
		if (preg_match($pattern, $fact, $m) !== 1) {
			return null;
		}

		$month = (int) ($m[2] !== '' ? $m[2] : $m[3]);

		return [(int) $m[4], $month * 100 + (int) $m[1]];
	}

	public function kindLabel(string $kind): string
	{
		return [
			'N' => 'Křest',
			'O' => 'Sňatek',
			'Z' => 'Úmrtí',
			'I' => 'Rejstřík',
			'S' => 'Sčítání lidu',
			'J' => 'Jiný pramen',
		][$kind] ?? $kind;
	}

	/** Heading of a generation of ancestors (0 = the home person). */
	public function generationName(int $gen): string
	{
		return [0 => 'Výchozí osoba', 1 => 'Rodiče', 2 => 'Prarodiče', 3 => 'Praprarodiče'][$gen] ?? $gen . '. generace předků';
	}

	/**
	 * Menu item of the stories (Stories::menu()); none on the error pages without data.
	 *
	 * @return array{string, string}|null [address, label]
	 */
	public function storyMenu(): ?array
	{
		return $this->repository->isAvailable() ? $this->stories->menu() : null;
	}

	/** Label of the link to a story (web.json → pribeh.tlacitko for the main one). */
	public function storyButton(Story $story): string
	{
		$button = $this->repository->siteConfig()->story->button;

		return $story->main && $button !== '' ? $button : 'Přečíst příběh';
	}

	/** Czech plural: plural(1, 'osoba', 'osoby', 'osob'). */
	public function plural(int $n, string $one, string $few, string $many): string
	{
		$word = $n === 1 ? $one : ($n >= 2 && $n <= 4 ? $few : $many);

		return $n . ' ' . $word;
	}

	public function json(mixed $value): string
	{
		return $this->e(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
	}
}
