<?php

declare(strict_types=1);

namespace Rodokmen\Site\View\Poster;

use Rodokmen\Site\Data\Model\Person;
use Rodokmen\Site\Data\Repository;
use Rodokmen\Site\View\FanChart;

/** What the poster layouts share about the ancestors: which places they draw and how a person is coloured. */
final readonly class PosterPeople
{
	/** Hues (degrees) of the men's blue and the women's old rose on the painted tree. */
	public const MenHue = 212.0;
	public const WomenHue = 345.0;

	/** How far (degrees) the family line turns the hue, from one end of the colour wheel to the other. */
	private const HueSpread = 40.0;

	private const SexSaturation = .42;

	public function __construct(private Repository $repository)
	{
	}

	/**
	 * Places to draw: only where the child is known – an empty place (null) means "parent not found yet".
	 *
	 * @param non-empty-list<list<?string>> $generations from Repository::ancestorGenerations(), the person first
	 * @return array<int, array<int, ?Person>> generation → index in the generation → ancestor
	 */
	public function places(array $generations): array
	{
		$places = [];
		foreach ($generations as $g => $row) {
			foreach ($row as $i => $slug) {
				if ($g === 0 || $generations[$g - 1][intdiv($i, 2)] !== null) {
					$places[$g][$i] = $this->repository->person($slug);
				}
			}
		}

		return $places;
	}

	/** Colour of the i-th ancestor of generation g (g ≥ 1): the family line's, or the father's / mother's side. */
	public function color(Person $person, int $g, int $i): string
	{
		return $person->line !== null
			? $this->repository->lineColor($person->line)
			: FanChart::SideColors[($i >> $g - 1) & 1];
	}

	/**
	 * Colour of the i-th ancestor of generation g on the painted tree: a shade of blue for a man, of old rose for
	 * a woman. The hue of the family line's colour turns the shade a little and its lightness carries over, so
	 * that neighbouring lines stay apart.
	 */
	public function sexShade(Person $person, int $g, int $i): string
	{
		[$hue, , $lightness] = self::hsl($this->color($person, $g, $i));
		$base = $person->isFemale() ? self::WomenHue : self::MenHue;

		return self::hex($base + ($hue / 360 - .5) * self::HueSpread, self::SexSaturation, max(.3, min(.5, $lightness)));
	}

	/** @return array{float, float, float} hue (0–360), saturation and lightness (0–1) of a #rrggbb colour */
	private static function hsl(string $color): array
	{
		if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $color, $m) !== 1) {
			return [180.0, .4, .4];
		}

		[$r, $g, $b] = [hexdec($m[1]) / 255, hexdec($m[2]) / 255, hexdec($m[3]) / 255];
		$max = max($r, $g, $b);
		$min = min($r, $g, $b);
		$lightness = ($max + $min) / 2;
		$delta = $max - $min;
		if ($delta === 0.0) {
			return [0.0, 0.0, $lightness];
		}

		$hue = match ($max) {
			$r => fmod(($g - $b) / $delta + 6, 6),
			$g => ($b - $r) / $delta + 2,
			default => ($r - $g) / $delta + 4,
		};

		return [$hue * 60, $delta / (1 - abs(2 * $lightness - 1)), $lightness];
	}

	private static function hex(float $hue, float $saturation, float $lightness): string
	{
		$chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
		$h = fmod(fmod($hue, 360) + 360, 360) / 60;
		$x = $chroma * (1 - abs(fmod($h, 2) - 1));
		[$r, $g, $b] = match ((int) $h) {
			0 => [$chroma, $x, 0.0],
			1 => [$x, $chroma, 0.0],
			2 => [0.0, $chroma, $x],
			3 => [0.0, $x, $chroma],
			4 => [$x, 0.0, $chroma],
			default => [$chroma, 0.0, $x],
		};
		$m = $lightness - $chroma / 2;

		return sprintf('#%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
	}

	/** Not found in the registers yet: drawn as an empty place with the name in grey. */
	public function isUnknown(Person $person): bool
	{
		return $person->st === 'unk';
	}

	/** Only probable or uncertain: paler colour, name in italics. */
	public function isUncertain(Person $person): bool
	{
		return $person->st === 'prob' || $person->st === 'mh';
	}

	/** CSS class of the person's name and years (with a leading space, or empty). */
	public function textClass(Person $person): string
	{
		return match (true) {
			$this->isUnknown($person) => ' pst-unk',
			$this->isUncertain($person) => ' pst-prob',
			default => '',
		};
	}
}
