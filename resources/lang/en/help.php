<?php

return [
    'title' => 'Help & Wiki',
    'intro_html' => '<strong>Welcome to Resource Legends.</strong> This help page explains core gameplay, mechanics, and account/security tips. Use the links below to jump to sections.',
    'contents_title' => 'Contents',
    'toc' => [
        'getting_started' => 'Getting started',
        'game_basics' => 'Game basics',
        'resources' => 'Resources',
        'lottery' => 'Lottery',
        'parcels' => 'Parcels',
        'construction' => 'Construction & Objects',
        'economy' => 'Economy & Trading',
        'accounts_security' => 'Accounts & Security',
    ],
    'sections' => [
        'getting_started' => [
            'title' => 'Getting started',
            'body' => '<p>Register a new account or login with your private key. After login, you will see the map and the basic controls for interacting with parcels and objects.</p>'
                . '<ul>'
                . '<li><strong>Register:</strong> Create a username in the Register tab. The system will provide a private key or a way to authenticate—save this private key securely. It is your account credential.</li>'
                . '<li><strong>Login:</strong> Use the private key on the Login tab to access your account. You may choose to remember the device.</li>'
                . '<li><strong>Interface overview:</strong> The top/header provides shortcuts (Home, Map, Inventory, Notifications, Settings). Your balance is shown in the footer.</li>'
                . '<li><strong>First actions:</strong> Claim an available parcel on the map, place a basic object, and gather initial resources.</li>'
                . '<li><strong>Language & settings:</strong> Switch language from the home page flags or update preferences in Settings.</li>'
                . '<li><strong>Support & updates:</strong> Check Notifications for system messages and updates.</li>'
                . '</ul>'
        ],
        'game_basics' => [
            'title' => 'Game basics',
            'body' => '<p>The world is organized as parcels on a grid. Each parcel can be owned, developed, and used to collect resources or place objects.</p>'
                . '<ul>'
                . '<li><strong>Actions & cooldowns:</strong> Many in-game actions have cooldowns or build times. Check object/tool descriptions for timings.</li>'
                . '<li><strong>Asynchronous play:</strong> The game runs on server ticks; actions may complete while you are offline. All timestamps are stored in UTC to ensure consistent behavior across timezones.</li>'
                . '<li><strong>Ownership:</strong> Parcels show the owner and are protected — actions like placing objects require you to own the parcel.</li>'
                . '<li><strong>Backend validation:</strong> All game-critical checks are validated on the server — client-side actions are not trusted for security.</li>'
                . '</ul>'
        ],
        'resources' => [
            'title' => 'Resources',
            'body' => '<p>Resources are the core currency for building and progression. Collect resources by interacting with parcels or built objects. Different objects produce different resource types.</p>'
                . '<ul>'
                . '<li><strong>Common resource types:</strong> wood, stone, food (actual names depend on object types).</li>'
                . '<li><strong>Production:</strong> Objects generate resources over time; production rates depend on object level and upgrades.</li>'
                . '<li><strong>Storage limits:</strong> Parcels or storage buildings may cap how much you can hold — plan spending or build storage.</li>'
                . '<li><strong>Collecting:</strong> Use the map or parcel UI to collect generated resources. Some resources may require interacting with the object.</li>'
                . '</ul>'
        ],
        'parcels' => [
            'title' => 'Parcels',
            'body' => '<p>Parcels represent tiles on the map and are the main area of interaction.</p>'
                . '<ul>'
                . '<li><strong>Claiming:</strong> Claim available parcels displayed on the map. Claimed parcels become yours to develop.</li>'
                . '<li><strong>Unique coordinates:</strong> Each parcel has unique x,y coordinates; two players cannot own the same coordinates.</li>'
                . '<li><strong>Adjacency and strategy:</strong> Parcels adjacent to your holdings may provide strategic benefits (e.g., expansion, connected bonuses).</li>'
                . '<li><strong>Parcel upgrades:</strong> Upgrading a parcel can increase build slots, resource bonuses, or unlock features.</li>'
                . '<li><strong>Parcel editor:</strong> Open a parcel to place/remove objects and assign workers.</li>'
                . '</ul>'
        ],
        'construction' => [
            'title' => 'Construction & Objects',
            'body' => '<p>Objects such as houses, farms or workshops can be placed on owned parcels. Use the parcel editor (click a parcel on the map) to place, upgrade or remove objects.</p>'
                . '<ul>'
                . '<li><strong>Build slots per parcel:</strong> Each parcel has a limited number of build slots (for example 1-4 depending on parcel size and upgrades). You can only place as many objects as there are available slots.</li>'
                . '<li><strong>Selecting workers:</strong> To start construction you must assign available workers from your roster. Each worker contributes to build speed. Assigning more or higher-level workers reduces total build time.</li>'
                . '<li><strong>Build time calculation:</strong> Base build time depends on object type and level. Actual time = base_time / (1 + worker_effect + building_level_bonus). Worker_effect is the sum of worker efficiencies; building_level_bonus is derived from existing upgrades.</li>'
                . '<li><strong>Upgrading objects:</strong> Objects can be upgraded to increase output or efficiency. Upgrading requires resources and workers, and follows the same time calculation rules.</li>'
                . '<li><strong>Queue:</strong> You can queue multiple constructions per parcel if slots allow. Currently cancellation of in-progress builds is not supported.</li>'
                . '<li><strong>Worker levels and training:</strong> Workers have levels/skills. Higher-level workers provide greater efficiency. Train workers at specific buildings (e.g., academy) to improve their stats.</li>'
                . '<li><strong>Resource costs:</strong> At the moment constructions and upgrades do not consume resource currencies in the current implementation — they rely on assigning workers and elapsed build time. If resource costs are added later, this section will be updated.</li>'
                . '<li><strong>Example:</strong> Building a Level 1 House with base time 60 minutes. Assigning two workers with total effect 0.5 reduces time: 60 / (1 + 0.5) = 40 minutes. Upgrading to Level 2 may double base time but increases production.</li>'
                . '<li><strong>Precise example (formula):</strong> The implementation uses a server-side helper: <code>calculateBuildSeconds(baseSeconds, currentLevel, workerLevel, workerCount)</code>. Internally: nextLevel = max(1, currentLevel+1); seconds = max(60, baseSeconds * nextLevel - ((workerLevel * workerCount) - 1)*60).</li>'
                . '<li><strong>Build time display:</strong> Build and upgrade times are now shown in human-friendly units (minutes, hours or days) across the parcel editor and upgrade dialogs for easier reading.</li>'
                . '<li><strong>Occupied workers example:</strong> You have 5 level-1 workers. You start construction using 2 level-1 workers: an occupied_workers record is created and those 2 workers are not available until the build is complete. If you start another build with 2 level-1 workers, you will have 1 free level-1 worker left.</li>'
                . '</ul>'
        ],
        'lottery' => [
            'title' => 'Lottery (Instant communal)',
            'body' => '<p>The Lottery is played instantly per-player: when you press Play your stake is added to a shared jackpot and a server-side draw is performed immediately for your entry.</p>'
                . '<ul>'
                . '<li><strong>Communal jackpot:</strong> All stakes are added to a common jackpot stored on the server. Payouts are deducted from that jackpot immediately.</li>'
                . '<li><strong>Payouts:</strong> Payouts are calculated as percentages of the current jackpot at the moment you play: 6 matches = 100% (entire jackpot), 5 = 20%, 4 = 4%, 3 = 1% (minimum 1).</li>'
                . '<li><strong>Atomicity:</strong> Your bet, the draw and any payout are processed inside a database transaction so that two players cannot be paid the same portion of the jackpot.</li>'
                . '<li><strong>Warning:</strong> If the jackpot decreased since you last viewed it, the client will warn you and block the bet so you can retry with the updated jackpot.</li>'
                . '<li><strong>Animaton & UX:</strong> The client still plays a short animation for suspense, but the outcome is already recorded server-side when Play is pressed.</li>'
                . '</ul>'
        ],
    'population' => [
    'title' => 'Population, Hospitals & Productions',
    'body' => '<p>This section describes population dynamics, hospitals and how the server handles occupied workers and cancelled productions.</p>'
    . '<ul>'
    . '<li><strong>Births:</strong> Each day the server runs a population tick. New people are added based on your <em>houses</em> and their attached tools. The formula sums house levels and tool levels and adds that many level-1 people to your population.</li>'
    . '<li><strong>Hospitals and deaths:</strong> Hospitals (object type "hospital") and tools placed in hospitals provide medical capacity. After births, the server checks hospital capacity vs current population. If capacity is lower than population, the server will remove (kill) a portion of the excess people. Removal is applied from highest-level people first (older people die first). When a people group reaches zero it is removed from the database.</li>'
    . '<li><strong>Behavior when no hospitals:</strong> If you have zero hospital capacity, the server will still remove people due to lack of capacity but will cap immediate mortality so it never removes more than 80% of your total population in a single tick — you will not lose your entire population at once.</li>'
        . '<li><strong>Occupied workers reconciliation:</strong> If the number of occupied workers assigned at a particular level exceeds the available people at that level, all affected occupied records for that level are cancelled. When this happens the server:</li>'
        . '<ul>'
        . '<li>Deletes the corresponding <code>occupied_workers</code> records (workers are released).</li>'
        . '<li>Sets the related object <code>ready_at</code> to <code>null</code> (the build/production is stopped and can be started again later).</li>'
        . '<li>Deletes per-object <code>production_outputs</code> (the produced output is lost) and adjusts <code>inventory.temp_count</code> to subtract the expected product.</li>'
        . '<li>Input resources consumed when the production started are <strong>not</strong> refunded (starting a production spends the inputs immediately).</li>'
        . '</ul>'
        . '<li><strong>Why this design:</strong> This prevents having active productions that reference non-existing people and keeps the server state consistent. It also encourages players to build hospitals to protect their population and to manage worker assignments carefully.</li>'
        . '</ul>'
    ],
        'economy' => [
            'title' => 'Economy & Trading',
            'body' => '<p>The in-game economy is based on generating, spending and (optionally) trading resources.</p>'
                . '<ul>'
                . '<li><strong>Balance:</strong> Your current balance is shown in the footer. Spend it on building upgrades, objects or other gameplay features.</li>'
                . '<li><strong>Trading:</strong> If trading features are enabled, use them to exchange resources with other players or marketplaces.</li>'
                . '<li><strong>Costs and ROI:</strong> Consider the return on investment for buildings (production vs cost and build time).</li>'
                . '<li><strong>Player Market:</strong> A new Market page lets you place limit buy and sell orders for tools and products. Orders are matched in periodic batches (every 10 minutes) to ensure fair matching and to reduce server load. When you place an order the required funds or items are reserved; that reservation is released if the order is cancelled or consumed when a trade executes. Trades use integer prices and small taker fees — check the Market UI for current fees and your reserved balances.</li>'
                . '</ul>'

                . '<p><strong>Market UI notes:</strong> The Market orders table includes a dedicated "Actions" column (trash icon) for cancelling orders. Use the small cancel icon to remove open orders. The "Hide cancelled" checkbox on the Market page is persisted in your browser so your preference is preserved between visits.</p>'
        ],
        'accounts_security' => [
            'title' => 'Accounts & Security',
            'body' => '<p>Security of your account is critical. The system uses private keys and tokens for authentication.</p>'
                . '<ul>'
                . '<li><strong>Private key:</strong> The private key is used to log in. Never share it. Store it in a secure password manager or an offline safe place.</li>'
                . '<li><strong>Personal access tokens:</strong> The application supports tokens for API access. Manage and revoke tokens in Settings (Personal Access Tokens).</li>'
                . '<li><strong>Device remember:</strong> Use the remember-me option only on trusted devices.</li>'
                . '<li><strong>Lost access:</strong> If you lose your private key, account recovery may be impossible — keep backups.</li>'
                . '<li><strong>Best practices:</strong> Use secure devices, keep software updated and avoid sharing credentials.</li>'
                . '</ul>'
        ],
    ],
];
