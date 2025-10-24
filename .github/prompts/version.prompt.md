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

4) Commit, tag and push behaviour
	- After generating files, create a commit with message: `chore(release): bump version to vX.Y.Z — {short title}`.
	- Create an annotated tag `vX.Y.Z` with a short message (the tag message may contain a one-line summary).
	- By default, ask the user for confirmation before running `git push origin main` and `git push origin vX.Y.Z` unless the user explicitly requested automatic push.
	- If the user allows pushing to GitHub, after pushing the branch and tag, optionally create a GitHub Release for `vX.Y.Z` and use the generated markdown file as the release body. Prefer using the GitHub CLI (`gh`) if available, otherwise use the GitHub REST API with a provided token.
	- If `gh` or a GitHub token is not available, inform the user and provide the `git` commands and the release body so they can create the release manually on GitHub.

5) Validation and safety
	- Verify `config/app.php` contained a valid semantic version before the change and that the updated file also contains a valid semantic version.
	- If there are inconsistencies (for example version also set in .env or other places), inform the user and suggest ways to synchronize.

6) Interaction rules
	- If uncertain whether the change is a fix or a feature, propose a suggested semantic bump and ask the user to confirm.
	- If the user requests only the release file to be generated (no commit/push), perform the local file changes and show what was created without committing.

7) Success criteria
	- `config/app.php` is updated with the new version.
	- A new markdown file exists at `resources/releases/en/{version}.md` with a user-facing description.
	- Commit and tag are ready locally; push is done only after user confirmation (or if automatic push was requested).

---


