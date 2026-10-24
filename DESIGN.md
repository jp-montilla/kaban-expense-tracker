# Kaban design

## Atmosphere
Household money on a phone first. Soft lilac hero, white floating cards, one violet mark. Cool gray canvas. No cream, no serif, no green-on-charcoal leftover.

## Color
- Canvas `#f3f4f8`
- Surface `#ffffff`
- Ink `#1b1833`
- Muted `#6b6f86`
- Line `#e4e5ef`
- Accent `#5b4ce8`
- Accent lift `#7a6bf5`
- Income `#2f8fdb`
- Spend `#e15d5d`
- Ok `#178a5b`
- Danger `#c0392b`
- Hero `linear-gradient(165deg, #5346e0 0%, #6d5ef6 48%, #8a7bff 100%)`

White on the hero, ink on paper. Muted on canvas must stay readable. Do not put muted gray on the light end of the hero.

## Type
Plus Jakarta Sans only. Tabular numbers. Sentence-case labels. Headings stay on a 1.125–1.2 scale, not fluid clamp.

## Components
- Buttons: 12px radius, 160ms hover, 0.98 press. Primary is accent, white type
- Cards: 16px radius, no 1px line, tinted violet shadow `0 8px 20px rgb(70 60 180 / 0.08)`
- Fields: 12px radius, 1px line, accent focus ring
- Chips: full pill, filled accent when on
- Dialogs: native `<dialog>`, 32rem max, 16px panel radius
- Sheets: native `<dialog>`, docked bottom on small screens
- FAB: 56px circle, accent fill, white plus, sits in the tab bar gap
- Tab bar: four items plus center FAB, 44px targets, labels under icons

## Motion
150–220ms ease-out on state change only. Honor `prefers-reduced-motion`.

## Layout
Mobile: edge-to-edge hero, overlapping money card, bottom tabs. Desktop: light rail, main column max 64rem, no tab bar.
