---
mode: agent
---
---
---
English instructions for the assistant (use when asked to "use this prompt"):

1) Purpose
	- When the user provides a short description of changes and asks to "create a version" or "use this prompt", you must automatically:
	  a) Produce a short, user-friendly, non-technical release description suitable for end users.
	  b) Decide a semantic version bump according to the rules below and update `config/app.php` (the `'version'` key).
	  c) Create a Markdown release file at `resources/releases/en/{version}.md` containing the date and the user-friendly description.

2) Semantic version bump rules
	- If the change is only fixes/patches/minor corrections → bump patch: X.Y.Z -> X.Y.(Z+1).
	- If the change adds a new feature or a significant enhancement → bump minor: X.Y.Z -> X.(Y+1).0.
	- Major version bumps (breaking or very large changes) MUST NOT be done automatically. Only bump major when the user explicitly requests "bump major" or gives clear permission.

3) Release file format and content
	- Path: `resources/releases/en/{version}.md` (for example `resources/releases/en/0.12.3.md`).
	- Content: title with version, Date: YYYY-MM-DD, a short heading and a few simple, non-technical bullet points describing the user-facing changes.

4) Validation and safety
	- Verify `config/app.php` contained a valid semantic version before the change and that the updated file also contains a valid semantic version.
	- If there are inconsistencies (for example version also set in .env or other places), inform the user and suggest ways to synchronize.

5) Interaction rules
	- If uncertain whether the change is a fix or a feature, propose a suggested semantic bump and ask the user to confirm.
	- If the user requests only the release file to be generated (no commit/push), perform the local file changes and show what was created without committing.

6) Success criteria
	- `config/app.php` is updated with the new version.
	- A new markdown file exists at `resources/releases/en/{version}.md` with a user-facing description.
	- Commit and tag are ready locally; push is done only after user confirmation (or if automatic push was requested).

---

7) Help / Documentation check (important)
	- When preparing the version/release prompt, ALWAYS check whether the change introduces new user-facing gameplay behavior or UI instructions that players need to know.
	- If user-facing gameplay or UI changes exist, update the help pages under `resources/lang/*/help.php` (both English and Bulgarian) with short, clear guidance about the new behavior. The release notes MUST include a bullet saying whether help was updated; if no help changes are needed, explicitly state "help: no changes needed" in the release notes.
	- Only update the help for new gameplay behavior or UI changes. Do NOT update help for internal bug fixes or refactors that do not change how the game works from the player's perspective.



