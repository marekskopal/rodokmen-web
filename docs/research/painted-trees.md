# Painted family trees: historical references and procedural techniques

Research notes for the "painted tree" ancestor poster (server-rendered vector SVG). The goal is to learn from
historical painted, engraved and lithographed family trees what makes a tree look natural and hand-made, and
to turn that into techniques that can be generated procedurally without raster images and without SVG filters
(Chrome rasterizes filtered elements when printing to PDF).

Method: the references were found on Wikimedia Commons, reduced previews were viewed outside the repository,
and the notes below describe what is actually visible in them. All page URLs returned HTTP 200 when checked in
November 2026. No image is stored in this repository; follow the links.

## 1. References

Licence notes: "PD" means the Commons page marks the work as public domain (old two-dimensional work). Where
the file is a modern photograph of a wall painting or a framed object, the photograph has its own licence.

### A. Ancestor charts (the same data shape as our poster)

These three are the closest models: one person at the bottom, parents, grandparents and so on above, 16
ancestors in the top row.

**A1. Ahnenprobe (proof of 16 quarters) of a countess of Lodron, 1786 copy of an earlier document** – PD  
<https://commons.wikimedia.org/wiki/File:Ahnenprobe_Lodron_Josepha_Maximiliana_1786.jpg>

- Each person's coat of arms sits in the crotch of a fork: two thin ochre branches leave from under the arms,
  run almost horizontally, then turn upward in a soft S or lyre shape towards the father and the mother.
  The forks are not connected to each other by a continuous trunk; each generation "grows" from the one below.
- A short sawn-off stump with a few grass blades under the bottom person stands for the trunk and ground.
- Foliage is very sparse: single lancet leaves and sprigs of two or three leaves on thin stalks, set
  alternately along the branch, more at the branch ends. Lots of empty parchment.
- Names are written in a calligraphic hand on plain paper slips below each coat of arms, two lines, no frame
  or a very light one. Palette: parchment, ochre branches, olive leaves, vermilion, blue and yellow arms.

**A2. Ahnenprobe of the counts of Berchtold, Prague, 1849** – PD  
<https://commons.wikimedia.org/wiki/File:Ahnenprobe_Berchtold_1849.jpg>

- The same fork idiom, drawn more heavily: brown branches form wide cups (U shapes) that hold the couple
  above; the lowest fork spreads over the whole width like a pair of arms.
- Leaves sit in a regular herringbone along both sides of every branch, alternating two greens (yellow-green
  and blue-green), each leaf a simple pointed oval with one brush stroke. Regular, yet clearly hand-painted
  because size and angle vary slightly.
- Names in framed white rectangular labels, name in bold blackletter-like script, titles smaller on the
  second line. A small green mound under the bottom label.

**A3. Ancestor tree of the Arregger family, engraving, 1780–1790 (Zentralbibliothek Solothurn)** – PD  
<https://commons.wikimedia.org/wiki/File:Zentralbibliothek_Solothurn_-_Stammbaum_der_Familie_Arregger_-_aa0296.tif>

- Two slender leafy stems rise from a stone pedestal carrying the children's tablets, fork again and again
  in Y shapes; every fork is asymmetrical and slightly wavy, so no two are identical.
- Name carriers are identical upright tablets with concave (notched) corners and a double outline, the coat
  of arms standing on top. Text is centred: name, office, then "born / married / died" lines.
- Leaves are small ovals in opposite pairs along the whole stem, hatched on one side only. The stems are
  outlined with two lines of different weight (light side thin, shadow side thick).

### B. Painted trees with a real trunk and crown

**B1. Genealogical tree of the zum Jungen siblings, Middle-Rhenish master, 1634 (Städel Museum)** – PD mark  
<https://commons.wikimedia.org/wiki/File:Genealogical_Tree_of_Maria_Justina_and_Johann_Maximilian_zum_Jungen_(SM_1770).png>

- A short, thick, slightly leaning trunk that divides early into three or four main limbs; limbs are dark,
  sinuous and mostly hidden by foliage, visible only in short stretches between leaf masses.
- Name carriers are white banderoles: a horizontal ribbon with a gentle sag, both ends curled or folded
  back, name in black blackletter in one or two lines. The coat of arms stands on top of the ribbon. Couples
  share one longer ribbon or two ribbons that touch. The bottom ribbon is wrapped around the trunk.
- A dense, dark crown made of many small compound-leaf sprays; the silhouette of the crown is lumpy and
  irregular, with twigs sticking out all around. Ribbons are bright shapes on the dark mass.
- Two standing figures flank the trunk; behind them a low landscape with hills, a castle and sky.

**B2. Genealogical tree of the Velluti family, oil, 17th–18th century** – PD  
<https://commons.wikimedia.org/wiki/File:Genealogical_tree_of_the_Velluti_family.jpg>

- A straight, mast-like trunk carrying a column of medallions; side branches leave nearly horizontally and
  curve up at the tips, thin and twiggy, ending in leaf sprays that stick out beyond the last medallion.
- Name carriers are round white medallions with a gold ring. First names fit; longer entries (name, spouse,
  dates) are squeezed into four or five tiny lines – round medallions are a poor fit for long text.
- Oak-like lobed leaves in clusters of three to five at twig ends, lighter on the upper side.
- A reclining armoured founder at the roots, putti holding a large curled title scroll, landscape and sky.

**B3. Genealogical tree of the Corner family, Palazzo Mocenigo, Venice (18th century)** – PD (photo)  
<https://commons.wikimedia.org/wiki/File:(Venice)_Palazzo_Mocenigo_-_Arbre_g%C3%A9n%C3%A9alogique_de_la_famille_Corner.jpg>

- A thin golden tree on a black ground: roots spread like fingers, the trunk is slender and wavy, branches
  leave at about 45° and bend upward, every tip ends in a spray of three to five gold leaves.
- Each person is a small portrait bust sitting in a cup (calyx) of gold leaves, like a flower on the branch;
  the name is on a long silver-white ribbon that flutters in an S curve beside the bust, with forked ends.
- A wide ribbon tied around the base of the trunk carries the family name. Only three colours: gold, white,
  red – the restraint is what makes it elegant.

**B4. Babenberg family tree, Hans Part, 1489–1492 (Klosterneuburg), central panel** – PD  
<https://commons.wikimedia.org/wiki/File:Babenberger_Stammbaum.jpg>

- The tree is a golden vine: from a small knot of roots it spirals into rings that frame 27 round scenes;
  the rings are the branches themselves, twisted into loops.
- All space between the roundels is filled with gold acanthus-like leaves, flowers and grapes on a dark blue
  ground sprinkled with gold dots. Density is uniform ("horror vacui").
- Names are on small labels inside the roundels; a meadow with animals and riders runs along the bottom.

**B5. Habsburg family tree, South Germany, 1497 (Ambras Castle), detail** – photo CC BY-SA 4.0  
<https://commons.wikimedia.org/wiki/File:Ambras_Gem%C3%A4lde_-_Habsburg_Stammbaum.jpg>

- Pen and wash on parchment. Half-figures grow out of large curled leaf calyxes (ochre wash, black pen
  contour, short parallel hatching strokes on the shaded side); shields stand in front of the calyx.
- Text is on big banderoles drawn as a ribbon in perspective: the front face is left blank parchment for
  text, the back face and the folded ends are washed in green or red that fades towards the middle.
- Pen lines have visible weight variation and do not close perfectly; washes do not reach the outline
  everywhere or run slightly over it.

**B6. Habsburg family tree, wall painting, Tratzberg Castle, c. 1508** – photo CC BY-SA 4.0  
<https://commons.wikimedia.org/wiki/File:Schloss_Tratzberg_-_Habsburger_Stammbaum_I.jpg>

- Long, thin, vine-like branches in pale green and brown wander horizontally across the wall, cross each
  other and send out short side shoots with tendrils, small leaves, flowers, berries and a few birds.
- Figures stand behind their shields; under every group is a wide white text scroll with a shadowed lower
  edge and a rolled right end, black blackletter text with red initials.

### C. Tree of Jesse (the prototype of the genre)

**C1. Tree of Jesse, circle of Geertgen tot Sint Jans, c. 1500 (Rijksmuseum SK-A-3901)** – CC0  
<https://commons.wikimedia.org/wiki/File:De_boom_van_Jesse,_SK-A-3901.jpg> ·
<https://www.rijksmuseum.nl/en/collection/SK-A-3901>

- A smooth grey trunk grows from the sleeping Jesse, almost straight, then splits into a few strong limbs
  that work as seats; limbs are cylinders with a clear light and a dark side.
- Leaves are small, round-oval, in loose sprays; foliage fills the gaps between figures but never covers
  them. A peacock and other birds sit on the branches.

**C2. Tree of Jesse, Lower Rhine, c. 1500 (Rijksmuseum Twenthe)** – PD  
<https://commons.wikimedia.org/wiki/File:Nederrijn_-_De_boom_van_Jesse_-_0035_-_Rijksmuseum_Twenthe.jpg>

- The branches turn into large Gothic acanthus scrolls: each leaf is a curling band with a green front, a
  red, blue or white reverse side and a light edge; kings sit in the curls. Gold ground.
- Speech scrolls with rolled ends loop freely around the trunk.

**C3. Ornamental Tree of Jesse, engraving, Israhel van Meckenem, late 15th century (Rijksmuseum)** – CC0  
<https://commons.wikimedia.org/wiki/File:Boom_van_Jesse,_RP-P-OB-1142.jpg>

- A textbook of branch curves: every branch is a spiral that tapers evenly to its tip, forks leave
  tangentially, and each fork has a small leaf sheath (bract) hiding the joint.
- Black background with white dots; leaves modelled only by parallel hatching that follows the leaf's curl.

**C4. Genealogy of Christ, woodcut, c. 1490** – PD  
<https://commons.wikimedia.org/wiki/File:Stammbaum_Christi_c1490_ubs_G_0173_II.jpg>

- Pure line: branches are two parallel contour lines that cross and interlace; leaves are three-lobed with a
  few short hatching strokes at the base. Names are written on ribbon-like halos around the heads.
- Shows how much "tree" can be said with outline plus a little hatching, no colour at all.

### D. Engraved pedigrees of ruling houses

**D1. Princely Württemberg "Stamm-Baum", Matthäus Seutter, Augsburg, c. 1730, hand-coloured engraving** – PD  
<https://commons.wikimedia.org/wiki/File:Stammbaum_W%C3%BCrttemberg.jpg> ·
<https://commons.wikimedia.org/wiki/File:Georg_Matth%C3%A4us_Seuter_W%C3%BCrttemberg-Stammbaum.jpg>

- A columnar trunk with the main line as a stack of tablets on it. At the top the trunk splays into limbs
  that sweep outward and carry long, comb-like rows of small shields on short curved stalks.
- Trunk and limbs are shaded with long hatching lines parallel to the growth direction, denser on one side;
  limb width decreases steadily; the names of the family lines are lettered along the limbs themselves.
- Leaves are few: single large vine leaves at branch ends and in gaps, coloured by hand in a flat green that
  ignores the engraved outline here and there. The two impressions are coloured differently.
- A reclining founder lies across the roots; allegorical figures, a hung stag, and a title inside a wreath
  of leaves and grapes.

**D2. "Carte généalogique de la Maison d'Autriche", 1708, hand-coloured engraving** – PD  
<https://commons.wikimedia.org/wiki/File:Stammbaum_Haus_Habsburg_1708.jpg>

- A diagram disguised as a tree: thin branches of constant width, each dynasty in its own flat wash colour
  (yellow, green, pink), rectangular text boxes and a few ovals. The trunk rises from a hatched brown mound
  with small plants; the oldest ancestors are ovals placed on the mound like roots.
- Useful as a negative example too: uniform branch width and boxes without leaves read as "technical".

**D3. Family tree of the lords of Rappoltstein, etching, Friedrich Brentel, 1620, detail** – PD  
<https://commons.wikimedia.org/wiki/File:F_Brentel_-_Stammbaum_der_Herren_von_Rappoltstein_Radierung_1620_(Ausschn)_(UzFBi103).jpg>

- A thick trunk with scrolls wrapped around it: each cartouche is a strip whose ends roll up into small
  cylinders, drawn with a visible end spiral and a cast shadow on the trunk. Text in three short lines.
- The trunk has vertical bark hatching and a dark side; a landscape with figures fills the ground level.

### E. 19th and early 20th century lithographs and blank charts

**E1. "The Family Photograph Tree", Currier & Ives, New York, 1871, lithograph** – PD  
<https://commons.wikimedia.org/wiki/File:The_family_photograph_tree_LCCN2001699059.jpg>

- A massive, short oak: the trunk flares strongly into buttress roots that grip a mound, bark is rendered
  with broken, wavy lines; heavy limbs twist, taper quickly and break up into angular twigs.
- Oval frames hang from twigs on small rings; two ovals for the couple sit at the base of the trunk.
- The crown is built of leaf clumps with light tops and dark undersides; a village landscape behind.

**E2. Blankenhorn family tree, drawn on stone by a Stuttgart painter, 1908, colour lithograph** – photo
CC BY-SA 4.0  
<https://commons.wikimedia.org/wiki/File:Blankenhorn_Stammbaum_Museum_M%C3%BCllheim.jpg>

- The best model for many name plates: a stout brown trunk, limbs that taper strongly and carry flat,
  slightly sagging horizontal "boughs"; each generation is a row of small upright tablets standing on a bough.
- Every tablet has a tuft of three to five green oak leaves growing behind its top edge, so that the crown is
  made of plates plus leaves, with paper showing through between rows.
- Flat colours with a black key drawing; a castle ruin, bushes and a title cartouche at the bottom.

**E3. Colsman family tree, hand-coloured lithograph after Caspar Scheuren, Düsseldorf, 1854** – PD  
<https://commons.wikimedia.org/wiki/File:Stammbaum_Familie_Colsman_handcolorierte_Lithographie_von_Sonderland_nach_Zeichnung_von_Caspar_Scheuren_gedruckt_bei_Arnz_%26_Co_in_D%C3%BCsseldorf_1854.jpg>

- Not a tree but an architectural frame: the main line in round medallions with text running around the rim,
  siblings in small rectangular labels, portrait roundels and vignettes at the sides, a town view at the foot.
- Shows the 19th-century taste: thin gold-ochre outlines, pale tints, fine filigree tendrils in empty panels.

**E4. Blank four-generation ancestor chart, lithograph by Oskar Roick, c. 1910** – PD  
<https://commons.wikimedia.org/wiki/File:Roick_Stammbaum_Vordruck.jpg>

- A printed blank form for exactly our case (1 + 2 + 4 + 8 + 16 fields). Stems run vertically between the
  fields and fork in soft, rounded Y shapes directly under each pair of fields.
- Fields are plain rectangles whose top edge is a ribbon with small curled ends; ivy grows between them in
  dense patches while the fields themselves stay empty and white. An oak wreath marks the starting person.

## 2. What the references have in common

Observed in most or all of the references above:

- **Nothing is straight and nothing repeats exactly.** Branches are S curves or spirals, forks are
  asymmetrical, leaves differ in angle and size. Even the engraved "diagram" trees wobble.
- **Branches taper.** Width falls from the trunk to the twigs and drops at every fork; branches of constant
  width (D2) immediately look like a diagram.
- **Forks are tangential.** A child branch leaves along the parent's direction and then bends away; there are
  no T junctions. In ancestor charts the fork is a lyre or cup with the person in its crotch (A1–A3, E4).
- **The trunk stands on something.** Flared roots, a mound with grass, a stump, a pedestal, a reclining
  founder, a landscape strip. A trunk that just ends at the page edge does not occur.
- **Name carriers are objects with thickness.** Ribbons have a reverse side and curled ends, tablets have a
  double outline and a shadow edge, medallions have a ring. None is a bare outline shape.
- **Leaves belong to twigs and to plaques.** They grow in sprays at twig ends or as tufts behind the plaque;
  they frame the names and never cover them. There are only one or two leaf shapes per picture.
- **Limited palette.** Parchment ground, brown or ochre wood, one or two greens, and one or two accents
  (vermilion, blue, gold). Outlines are brown-black ink, not pure black.
- **One light direction.** Wood, ribbons and leaves all have their dark side on the same side, shown by
  hatching or a darker wash.
- **Imperfect registration.** Colour does not sit exactly inside the line (hand-coloured prints, washes).
- **Lettering is part of the drawing.** Blackletter or roman capitals, a larger first line for the name,
  smaller lines for dates; sometimes text follows the ribbon's curve.

## 3. Ranked traits

Ranked by (visual impact) × (feasibility in plain vector SVG). Impact and feasibility: high / medium / low.

| #  | Trait                                                          | Impact | Feasibility |
|----|----------------------------------------------------------------|--------|-------------|
| 1  | Tapering branches drawn as filled shapes, not constant strokes | high   | high        |
| 2  | Curved, tangential, asymmetrical forks (lyre/cup under couple) | high   | high        |
| 3  | Name carriers with body: banderole with curled ends, shadow    | high   | high        |
| 4  | Leaf sprays tied to twig ends and plaque edges, 1–2 leaf shapes| high   | high        |
| 5  | Grounding: root flare, mound, grass, stump or base cartouche   | high   | high        |
| 6  | Restricted period palette on a parchment ground, sepia ink     | high   | high        |
| 7  | Seeded irregularity everywhere (angles, sizes, sag, wobble)    | high   | high        |
| 8  | Ink contour with varying weight, drawn separately from fill    | medium | high        |
| 9  | One-sided shading by hatching / second flat tone               | medium | medium      |
| 10 | Wash misregistration and layered translucent tints             | medium | medium      |
| 11 | Period lettering, two-line hierarchy, optional curved baseline | medium | medium      |
| 12 | Secondary life: fruit, flowers, birds, tendrils, title scroll  | low    | medium      |

Not recommended: full landscapes and figures (B1, B2, E1) – high impact but not credible procedurally; dense
all-over ornament (B4, C2) – thousands of paths, and it competes with the names.

## 4. Procedural techniques

General rules: everything is driven by a seeded pseudo-random generator (seed derived from stable data such
as the home person's identifier), so the same tree renders identically every time and tests stay stable.
No `<filter>`, no raster. Linear and radial gradients, `clipPath`, `<use>` and opacity are plain vector
operations; blend modes (`mix-blend-mode`) should be checked in Chrome's PDF output before relying on them.

### 4.1 Branch skeleton (traits 1, 2, 7)

- Keep the existing generational layout for plaque positions; change only how they are connected.
- Model every parent link as a cubic Bézier centre line. Start tangent = direction of the branch below at
  the fork (mostly upward); end tangent = entering the plaque from below, near its centre. This gives the
  tangential fork for free. For a couple, mirror the two curves and then break the symmetry: perturb the
  control points by a seeded 4–8 % of the chord length and give the two sides different "lift".
- Fork shape for an ancestor chart: use the lyre/cup of A1–A3 and E4. From the top of a person's plaque a
  short common stem rises (10–20 % of the row gap), then splits; each arm runs outward nearly horizontally
  and turns up into the parent plaque. In inner generations the arms are long and sweeping, in outer
  generations they become short Y forks.
- Add a low-frequency wobble: sample the Bézier at 8–16 points and offset each point along the normal by a
  smooth seeded noise (sum of two sines with random phase is enough), amplitude 1–2 % of length, zero at both
  ends. Refit as a smooth path (Catmull-Rom to Bézier).
- Optional leafy dead-end twigs: from 20–40 % of the branches spawn a short side twig (15–30 % of the branch
  length, leaving at 25–45°, curving upward) that ends in a leaf spray. These fill gaps and break the
  diagram look more than anything else (B2, B3, E1).

### 4.2 Thickness: pipe model (trait 1)

- Width at the branch tip serving one person = `w0`. At a fork use Leonardo's rule / the pipe model:
  `w_parent^n = w_a^n + w_b^n` with `n` between 2 and 2.5. For a full binary ancestor tree this gives the
  width of a branch carrying `k` leaf ancestors as `w0 · k^(1/n)`; with missing ancestors count only the
  ancestors actually present, so sparse sides become visibly thinner – a natural effect.
- Taper along each segment linearly (or with an ease-out) from the lower to the upper width, and add a 10–15 %
  swelling just below each fork (branch collar).
- Trunk: add a root flare – multiply the half-width by `1 + a·exp(-d/b)` where `d` is the height above the
  ground; let 3–5 roots leave as short tapering curves hugging the mound.
- Geometry: do not use `stroke-width`. Build each branch as a closed polygon: offset the sampled centre line
  by ±half-width along the normal, join left and right sides, close with a round tip. Draw children first and
  the parent on top (or union them into one path) so that forks have no visible seams.

### 4.3 Name carriers (trait 3)

Shape families seen and how they fit two lines of text (a name of 10–25 characters plus a years line):

- **Banderole / scroll** (B1, B3, B5, B6, D3, E4): aspect ratio 3:1 to 5:1, which is the natural shape of
  one name line plus one short line. Reads as "old family tree" at once. Width can stretch with the name.
- **Upright tablet with notched corners** (A3, D1, E2): very compact horizontally, good for 16 or 32
  plaques in a row, but long names must wrap to 3–4 lines and it looks more like a form.
- **Round or oval medallion** (B2, E1, E3): elegant for first names or portraits; wastes width, long names
  need tiny type. Plain ovals are also what currently makes the poster look technical.
- **Shield / coat of arms** (A1, A2): the shield is always accompanied by a separate text label; without
  real heraldry it is an empty symbol.
- **Leaf calyx, fruit, heart**: decorative carriers for figures or short words, not for two lines of text.

**Recommendation: a horizontal banderole with curled ends**, with a simplified variant for the outer rows.

- Body: a band about 4:1, whose top and bottom edges are parallel shallow curves (a sag or a slight wave of
  3–6 % of the width, seeded so that neighbours differ). Fill with the parchment-white of the paper, slightly
  lighter than the background.
- Ends: either a fold-back (a small parallelogram behind the band in a darker tone plus a triangular shadow
  wedge, forked "swallow-tail" tip as in B3) or a roll (a small spiral of 1.25 turns with the end ellipse in
  a darker tone, as in D3 and B6). The fold-back needs 3 small paths per end and is the cheaper one.
- Body thickness: a darker 1–1.5 px edge along the lower side and a thin translucent shadow shape offset
  down-right; an ink outline drawn as described in 4.5.
- Text: name on the first line, years on the second in a smaller size, both centred. Keep the text straight
  (the sag is small enough) for legibility and simple measuring; curved text on a `textPath` is an optional
  refinement for the big plaques only. Plaque width = measured text width + padding, clamped to the slot.
- Attachment: the branch ends behind the middle of the lower edge; a leaf tuft or two grow from behind the
  top edge (E2), which hides the joint and ties the plaque to the tree.
- Home person: a larger scroll wrapped around the trunk (B1, B3) or a cartouche on the trunk base.
- Outer generations where the slot is narrower than about 3:1: switch to a short two-line ribbon without
  curled ends or to the notched tablet of A3/E2, and allow the name to wrap to two lines plus years.

### 4.4 Foliage (trait 4)

- Define two or three leaf outlines once in `<defs>` (for Central Europe: a lime/linden heart, or a lobed oak
  leaf as in E2, plus a simple lancet for small sprigs) each with a midrib line, and reuse them with `<use>`
  and a transform (rotate, non-uniform scale 0.8–1.2, optional mirror).
- Place leaves only in sprays: 3–7 leaves fanned over 80–140° around a twig end, slightly overlapping, drawn
  back to front. Spray anchors: every branch tip behind a plaque's upper edge, every dead-end twig, and at
  intervals along thin branches (alternate sides, as in A1/A2).
- Density gradient: more and larger sprays on the periphery of the crown and in empty areas between
  generations, none on the trunk. Find empty areas by testing candidate anchors against the plaque boxes
  with a margin; never let a leaf overlap a text box, only tuck leaves behind plaques (draw them earlier).
- Colour: two or three flat greens chosen per leaf by seed, plus an occasional ochre leaf; a darker
  half-leaf shape (one side of the midrib) gives volume without gradients.
- Keep a budget (for example 300–600 `<use>` elements for six generations) so that the SVG stays small.

### 4.5 Ink line, hatching and washes (traits 8, 9, 10)

- Contour separate from fill: draw the fill polygon, then draw the outline as its own path generated from
  the same points with a small independent jitter (0.3–0.8 px) and let it be open in places (skip 5–10 % of
  the length in short gaps). This is the single cheapest "hand-made" signal.
- Varying line weight without filters: draw the contour as a thin filled shape (two offset curves whose
  distance varies between 0.4 and 1.4 px), thicker on the shadow side; or, more cheaply, two overlapping
  strokes of different width and slightly different jitter (the rough.js "double stroke" idea).
- Hatching: on the shadow side of trunk and main limbs add short strokes parallel to the growth direction
  (D1, D3), 2–4 rows, lengths and spacing seeded, fading out towards the light side. On ribbons a few
  strokes at the curled ends. Add 2–5 bark marks and one or two knots (small open ovals) on the trunk.
- Washes: under the contour put a flat base colour and one or two translucent darker shapes covering the
  shadow half (opacity 0.15–0.3). Offset the whole fill layer by about 0.5–1 px against the ink layer and
  let its edge wobble independently, as in hand-coloured engravings (D1). No blur is needed.
- A linear gradient along a ribbon's reverse side (B5) is a cheap vector way to suggest a fading wash.

### 4.6 Ground and base (trait 5)

- A low mound: a closed shape with a wavy top edge in an olive-brown wash, hatched underneath, with 10–20
  grass tufts (3–5 short curved strokes each) and the root flare running into it (D2, A2, E1).
- Alternatives that suit an ancestor chart: a cut stump under the home person (A1) or a stone pedestal (A3).
- Put the poster title and the home person on a large curled scroll or cartouche at the base (B2, D1).

### 4.7 Palette and lettering (traits 6, 11)

- Ground: warm parchment (not white), optionally a slightly darker vignette as a large radial gradient.
- Wood: ochre to umber; ink: dark sepia; leaves: two greens (yellow-green, blue-green) and an occasional
  ochre; accents: vermilion and a muted blue for ribbon reverse sides, small fruit or initials; optional
  gold-ochre rings and outlines. Keep it to about eight colours in total.
- Lettering: a serif or humanist face for legibility with diacritics; name larger, years smaller and
  lighter; letter-spacing slightly open; sepia instead of black. A blackletter display face only for the
  title. A red initial for the home person echoes B6.

### 4.8 Secondary details (trait 12)

- A handful of extras spread by seed: two or three birds as silhouettes, small fruit or blossoms at twig
  ends, a few tendrils (single spiral paths) on thin branches. Keep them rare; they are seasoning.

### 4.9 Layer order

Background and vignette → ground mound → far leaf sprays → branches (thin first, trunk last) with wash, then
hatching, then ink contour → near leaf sprays and tufts behind plaques → plaque shadows → plaques → ribbon
ends → text → sparse foreground extras.

## 5. Priorities for implementation

1. Replace constant-width connectors by tapered, filled branch polygons with tangential lyre forks and
   pipe-model widths (traits 1, 2).
2. Replace ovals by banderoles with fold-back ends and a shadow edge (trait 3).
3. Tie leaf sprays to branch tips, twigs and plaque tops; add dead-end twigs (trait 4).
4. Add the root flare and mound, parchment ground and the restricted palette (traits 5, 6).
5. Separate jittered ink contour, one-sided hatching, offset wash (traits 8–10).
6. Extras and lettering refinements (traits 11, 12).

## 6. Background reading on the algorithms

- Runions, Lane, Prusinkiewicz: "Modeling Trees with a Space Colonization Algorithm" (2007) – branch
  skeletons that compete for space, limb diameters from the pipe model:
  <https://www.algorithmicbotany.org/papers/colonization.egwnp2007.pdf>
- Shinozaki et al. (1964), the pipe model: the cross-sections of child branches add up to the parent's;
  Leonardo da Vinci's rule is the special case with exponent 2.
- rough.js – sketchy rendering by doubled, jittered strokes and hachure fills; the idea carries over to
  server-side path generation: <https://roughjs.com/>
