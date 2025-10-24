---
mode: agent
---
---
Инструкция за асистента (когато бъдеш помолен да "използваш" този prompt):

1) Цел
	- Когато потребителят даде кратко описание на направените промени и помоли да се "създаде версия" или "използва този prompt", ти трябва автоматично да:
	  a) Съставиш кратко, лесно за разбиране и потребителско-ориентирано описание на промените (без технически детайли). Това описание ще бъде видимо за всички потребители.
	  b) Определиш семантичното увеличаване на версията според правилата по-долу и направиш нужните промени в `config/app.php` (полето `'version'`).
	  c) Създадеш Markdown файл с описанието на релийза в `resources/releases/en/{version}.md` (с дата и потребителско описание).

2) Правила за семантично увеличаване на версията
	- Ако промените са само поправки/фиксове/малки корекции → увеличи само крайното число (patch): X.Y.Z -> X.Y.(Z+1).
	- Ако има добавена нова функционалност или значително разширение (feature) → увеличи средното число (minor): X.Y.Z -> X.(Y+1).0.
	- Major (голяма, съвместимо-несъвместима промяна) НЕ се увеличава автоматично. Прави това само ако потребителят изрично каже "bump major" или даде явно разрешение.

3) Формат и съдържание на release файла
	- Път: `resources/releases/en/{version}.md` (напр. `resources/releases/en/0.12.3.md`).
	- Съдържание: заглавие с версията, дата (YYYY-MM-DD), кратък заглавен ред и няколко преводими реда/точки, написани на прост и разбираем език (без технически жаргони). Пример:

  # Release 0.12.3

  Date: 2025-10-24

  ## What's new for you

  - Short, non-technical bullet 1
  - Short, non-technical bullet 2

4) Commit, tag и push (поведение)
	- След успешното генериране на файловете, напиши commit с послание: `chore(release): bump version to vX.Y.Z — {кратко заглавие}`.
	- Създай annotated tag `vX.Y.Z` с кратко съобщение (може да е едно изречение или самото заглавие на release-а).
	- По подразбиране попитай потребителя за потвърждение преди да изпълниш `git push origin main` и `git push origin vX.Y.Z` (освен ако потребителят е заявил предварително, че иска автоматично push).

5) Валидиране и безопасност
	- Проверявай че `config/app.php` съдържа валидна семантична версия преди и след промяната.
	- Ако има несъответствие (напр. версията е в .env или в други места), информирай потребителя и предложи как да синхронизира.

6) Уточнения и взаимодействие с потребителя
	- Ако не си сигурен дали описанието е "fix" или "feature", генерирай предложена семантична версия и попитай потребителя за потвърждение.
	- Ако потребителят поиска само генериране на release файл и да НЕ се прави commit/push, изпълни само локалните файлови промени и представи какво е направено.

7) Успех критерии
	- `config/app.php` е актуализиран с нова версия.
	- Има нов markdown файл в `resources/releases/en/{version}.md` с потребителско-ориентирано описание.
	- Комит и tag са готови локално; push е извършен само след потвърждение (или ако потребителят е дал автоматичното разрешение).

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
	- Create an annotated tag `vX.Y.Z` with a short message.
	- By default, ask the user for confirmation before running `git push origin main` and `git push origin vX.Y.Z` unless the user explicitly requested automatic push.

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

