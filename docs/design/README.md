# Kingtime designs

All Kingtime designs live on one Claude design canvas,
[Kingtime designs](https://claude.ai/artifact/2H34thswAizZcZPok9qBvm), with a page per section.
This folder is the copy in the repository, so the designs are reachable for anyone who cannot open the
canvas and do not disappear when a session ends.

| Page | Boards | What |
| --- | --- | --- |
| Brand | `Main` | The logo lockup, the Mac app icon, the menu bar icon, the favicon, the light and dark theme colours and the two typefaces. |
| Web app | `Dashboard`, `Timesheet`, `Invoices`, `Integrations` | The web app screens as they appear on the product page. |
| Kingtime for Mac | `PanelLight`, `PanelDark`, `SignIn`, `IdlePrompt`, `HeroLight`, `HeroDark` | The menu bar panel in both themes, sign-in, the idle prompt and the panel under the menu bar. |
| Product page | `ProductPageLight`, `ProductPageDark` | kingtime.nl, full length, in both themes (taken 2 October 2026). |

## Where the sources are

The canvas shows the real assets; the files that ship stay where they are:

| Board | Source in the repository |
| --- | --- |
| `Main` | `public/favicon.svg`, `mac/design/app-icon.svg`, `mac/design/menubar-icon.svg`, colours from `resources/css/app.css` |
| `Dashboard`, `Timesheet`, `Invoices`, `Integrations` | `public/images/site/*-card.png` |
| `PanelLight`, `PanelDark`, `SignIn`, `IdlePrompt`, `HeroLight`, `HeroDark` | `public/images/mac/*.png` (from the Mac app's `KINGTIME_SNAPSHOT` mode) |
| `ProductPageLight`, `ProductPageDark` | `docs/design/images/product-page-*.png` |

## What is in this folder

- `kingtime-designs/canvas.json`: which boards exist, on which page, their title and their place on the canvas.
- `kingtime-designs/*.dc.html`: one board per file. Images are referenced by the canvas's upload urls
  (`/_blob/…`), so a board opened loose in a browser shows no pictures; the table above says which repository
  file each one is.
- `images/`: the product page screenshots, which have no other home in the repository.

## Updating

Change the design on the canvas, then copy the changed boards here. When a shipped asset changes (a new
screenshot, a new icon), upload it to the canvas and point its board at the new url.
