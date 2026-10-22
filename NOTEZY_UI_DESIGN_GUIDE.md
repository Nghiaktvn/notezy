# Notezy UI direction — October 2026

This document is a Figma-ready design handoff for the implemented PHP interface. It is **not** a Figma file. The Figma connection must be enabled before editable frames can be created there.

## Design references (inspiration only)

- Scribble Notes landing: https://dribbble.com/shots/27290655-Scribble-Notes-Productivity-App-Landing-Page-UI-UX-Design — spacious hero and app preview.
- AI Notes SaaS landing: https://dribbble.com/shots/26067220-AI-Notes-SaaS-Landing-Page-Design — calm AI positioning and product hierarchy.
- Note-taking dashboard: https://dribbble.com/shots/19721906-Note-taking-app-dashboard-design — scannable notebook cards and dashboard rhythm.

Do not copy these compositions, source files, logos, or artwork. Notezy uses its own layout, copy, illustrations, and palette.

## Tokens

| Role | Value | Use |
| --- | --- | --- |
| Paper | `#FAF8F4` | Page background |
| Ink | `#343344` | Main headings/body |
| Lavender | `#756C91` | Primary actions and accent text |
| Deep lavender | `#625A7E` | Hover/strong accents |
| Sage | `#9AAE9F` | Secondary accent/progress |
| Soft panel | `#FFFCF9` | Cards and dialogs |
| Hairline | `#E9E3DD` | Borders |

Typography: Plus Jakarta Sans and Space Grotesk on the public landing; the logged-in PHP screen currently uses a system UI font. Use a 4/8 px spacing rhythm, 12–20 px controls, 18–28 px feature panels, and shadows with low-opacity plum rather than saturated glow.

## Figma frames and components

1. Landing desktop: 1440 × 900. Floating pill navigation; two-column hero with copy left and notebook illustration right; app preview begins below the fold.
2. Landing mobile: 390 × 844. Hero stacks copy, actions, art; art stickers stay within the illustration region.
3. Sign-in/register dialog: 440 px max width, scrollable at low viewport heights; mascot top, clear two-tab switch, accessible form fields.
4. Dashboard desktop: 1440 × 900. Welcome panel, date/progress summary, view/label controls, card grid.
5. Dashboard mobile: 390 × 844. Welcome panel stacks text above mascot; note grid becomes one column.
6. Empty state and AI widget: illustrate first-note action; show the notebook mascot in the chat launcher/header, with a muted lavender conversation palette.

Components to create in Figma: `Button/Primary`, `Button/Secondary`, `Nav/Pill`, `Card/Note`, `Card/Statistic`, `Dialog/Auth`, `Widget/AI`, `Illustration/Notebook`, `Illustration/Mascot`. Include hover, focus, loading, empty, and reduced-motion states.

## Illustrations and licensing

Project-original generated assets: `assets/notezy-notebooks-2026.png` and `assets/notezy-mascot-2026.png`. They were created for Notezy with built-in image generation, without copying the linked artworks. The user-supplied Storyset URL that concatenates `pana` and `rafiki` is malformed; neither Storyset nor Blush artwork is bundled in this implementation. If replacing an original illustration with either provider's artwork, review the exact image license first. Storyset's free-use terms require attribution: https://storyset.com/terms and https://storyset.com/faqs . Blush terms must be checked for the chosen illustration and plan before public deployment.

## Implementation map

- Public landing and auth dialog: `index.php`, `CSS/landing-refresh.css`
- Standalone registration route: `register.php`, `CSS/register-refresh.css`
- Logged-in dashboard and first-note state: `index_notezy.php`, `CSS/dashboard-refresh.css`
- AI assistant: `js/ai-assistant.js`, `CSS/ai-assistant.css`, `includes/ai_widget.php`
- React/Vite frontend landing, login and registration: `frontend/src/pages/`, `frontend/src/components/landing/`, `frontend/src/design.css`
- React/Vite AI widget and original illustration copies: `frontend/src/components/AiAssistant.jsx`, `frontend/public/assets/`

The React authentication screens still submit to the existing PHP API; successful login opens `index_notezy.php` for the full note workflow. The legacy PHP note editor, collaboration, reminder and AI API behavior has not been replaced by a visual mockup. The React landing's interactive tabs and timed demo are labeled as previews. No `.env` file was edited for this visual refresh.

## Local preview and verification

- React preview: from `frontend/`, run `npm run dev -- --host 127.0.0.1`, then open `http://127.0.0.1:5173/`.
- PHP app (requires a healthy Docker engine): from the repository root run `docker compose --profile tools up -d --build`, then open `http://localhost:8080/`.
- Frontend checks: `npm run build` and `npm run lint`.
- PHP static regressions: `php tests/offline_deployment_static_check.php`, `php tests/security_regression_check.php`, and `php tests/password_reset_security_check.php`.

Static checks do not prove registration, email delivery, note CRUD, realtime collaboration or AI work end-to-end. Those require MySQL and services running and must be verified in a browser with test accounts before making a no-errors claim.
