<?php

declare(strict_types=1);

namespace Rodokmen\Site\View;

use Laminas\Diactoros\Response\HtmlResponse;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Psr\Http\Message\ResponseInterface;
use Rodokmen\Site\Config;
use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\Image\Thumbnails;
use Stringable;
use Throwable;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const EXTR_SKIP;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Renders plain PHP templates inside the common layout and offers small HTML helpers.
 */
final class View
{
	private ?GithubFlavoredMarkdownConverter $markdown = null;

	public function __construct(
		private readonly Config $config,
		private readonly Repository $repository,
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
		$this->markdown ??= new GithubFlavoredMarkdownConverter([
			'html_input' => 'escape',
			'allow_unsafe_links' => false,
		]);

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

	/** Year mentioned in a fact (for ordering the timeline), or null. */
	public function factYear(string $fact): ?int
	{
		return preg_match('/\b(1[5-9]\d\d|20\d\d)\b/', $fact, $m) === 1 ? (int) $m[1] : null;
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
