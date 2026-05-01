-- =====================================================================
-- Autowagen Master — EPC Step 2 — Subcategories (Level 2) for all 12
--
-- Parent rows are found by category **slug** (works after Step 1 / any IDs).
-- Does **not** remove or rename existing seed subcategories (Petrol, Diesel,
-- Hydraulic, Exterior). New rows use **INSERT IGNORE** on (category_id, slug).
--
-- Run in phpMyAdmin on `autowagen_master` after:
--   `sql/02_epc.sql`  +  `sql/02b_epc_categories_step1.sql`
-- =====================================================================

-- --- 1. Engine (seed already has Petrol, Diesel) ---------------------
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Engine block & internals',     'engine-block-internals',      30 FROM `epc_categories` c WHERE c.slug = 'engine' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Cylinder head & valvetrain', 'cylinder-head-valvetrain',    40 FROM `epc_categories` c WHERE c.slug = 'engine' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Intake & exhaust (engine)',  'intake-exhaust-engine',       50 FROM `epc_categories` c WHERE c.slug = 'engine' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Forced induction',           'forced-induction',            60 FROM `epc_categories` c WHERE c.slug = 'engine' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Engine electrics (starter, alternator)', 'engine-electrics-starter-alt', 70 FROM `epc_categories` c WHERE c.slug = 'engine' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Timing & belts / chains',    'timing-belts-chains',         80 FROM `epc_categories` c WHERE c.slug = 'engine' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Engine mounts & dampers',    'engine-mounts-dampers',       90 FROM `epc_categories` c WHERE c.slug = 'engine' AND c.is_active = 1 LIMIT 1;

-- --- 2. Transmission & driveline ------------------------------------
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Manual gearbox',             'manual-gearbox',              10 FROM `epc_categories` c WHERE c.slug = 'transmission-driveline' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Automatic gearbox',          'automatic-gearbox',           20 FROM `epc_categories` c WHERE c.slug = 'transmission-driveline' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Clutch & flywheel',        'clutch-flywheel',             30 FROM `epc_categories` c WHERE c.slug = 'transmission-driveline' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Propeller shaft',          'propeller-shaft',             40 FROM `epc_categories` c WHERE c.slug = 'transmission-driveline' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Differential & final drive', 'differential-final-drive',  50 FROM `epc_categories` c WHERE c.slug = 'transmission-driveline' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Half-shafts & CV joints',  'half-shafts-cv-joints',       60 FROM `epc_categories` c WHERE c.slug = 'transmission-driveline' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Transfer case & 4WD coupling', 'transfer-case-4wd',       70 FROM `epc_categories` c WHERE c.slug = 'transmission-driveline' AND c.is_active = 1 LIMIT 1;

-- --- 3. Brakes (seed has Hydraulic) ---------------------------------
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Disc brakes',                'disc-brakes',                 20 FROM `epc_categories` c WHERE c.slug = 'brakes' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Drum brakes',                'drum-brakes',                 30 FROM `epc_categories` c WHERE c.slug = 'brakes' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Parking & hand brake',       'parking-hand-brake',          40 FROM `epc_categories` c WHERE c.slug = 'brakes' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'ABS & electronic brake modules', 'abs-electronic-brake',  50 FROM `epc_categories` c WHERE c.slug = 'brakes' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Brake lines & hoses',        'brake-lines-hoses',           60 FROM `epc_categories` c WHERE c.slug = 'brakes' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Brake servo & booster',    'brake-servo-booster',         70 FROM `epc_categories` c WHERE c.slug = 'brakes' AND c.is_active = 1 LIMIT 1;

-- --- 4. Suspension & steering ---------------------------------------
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Front suspension',           'front-suspension',            10 FROM `epc_categories` c WHERE c.slug = 'suspension-steering' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Rear suspension',            'rear-suspension',             20 FROM `epc_categories` c WHERE c.slug = 'suspension-steering' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Shock absorbers & springs',  'shocks-springs',              30 FROM `epc_categories` c WHERE c.slug = 'suspension-steering' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Steering rack & gearbox',    'steering-rack-gearbox',       40 FROM `epc_categories` c WHERE c.slug = 'suspension-steering' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Steering column & shaft',    'steering-column-shaft',       50 FROM `epc_categories` c WHERE c.slug = 'suspension-steering' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Wheel hubs & bearings',      'wheel-hubs-bearings',         60 FROM `epc_categories` c WHERE c.slug = 'suspension-steering' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Anti-roll bars & links',     'anti-roll-bars-links',        70 FROM `epc_categories` c WHERE c.slug = 'suspension-steering' AND c.is_active = 1 LIMIT 1;

-- --- 5. Electrical & electronics ------------------------------------
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Battery & starting circuits', 'battery-starting-circuits',   10 FROM `epc_categories` c WHERE c.slug = 'electrical-electronics' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Charging & alternator circuits', 'charging-alternator',   20 FROM `epc_categories` c WHERE c.slug = 'electrical-electronics' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Exterior lighting',          'exterior-lighting',           30 FROM `epc_categories` c WHERE c.slug = 'electrical-electronics' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Interior lighting',        'interior-lighting',           40 FROM `epc_categories` c WHERE c.slug = 'electrical-electronics' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Wiring harnesses & fuse boxes', 'wiring-fuse-boxes',       50 FROM `epc_categories` c WHERE c.slug = 'electrical-electronics' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Sensors & actuators',        'sensors-actuators',           60 FROM `epc_categories` c WHERE c.slug = 'electrical-electronics' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Body control & comfort modules', 'body-comfort-modules', 70 FROM `epc_categories` c WHERE c.slug = 'electrical-electronics' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Infotainment & displays',    'infotainment-displays',       80 FROM `epc_categories` c WHERE c.slug = 'electrical-electronics' AND c.is_active = 1 LIMIT 1;

-- --- 6. Fuel system ---------------------------------------------------
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Fuel tank & sender',        'fuel-tank-sender',            10 FROM `epc_categories` c WHERE c.slug = 'fuel-system' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Fuel lines & rails',       'fuel-lines-rails',            20 FROM `epc_categories` c WHERE c.slug = 'fuel-system' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Fuel injection / induction', 'fuel-injection-induction',    30 FROM `epc_categories` c WHERE c.slug = 'fuel-system' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Fuel pumps & filters',       'fuel-pumps-filters',          40 FROM `epc_categories` c WHERE c.slug = 'fuel-system' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Evap & emissions (fuel side)', 'evap-emissions-fuel',       50 FROM `epc_categories` c WHERE c.slug = 'fuel-system' AND c.is_active = 1 LIMIT 1;

-- --- 7. Cooling & HVAC ----------------------------------------------
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Engine cooling (radiator, etc.)', 'engine-cooling-radiator', 10 FROM `epc_categories` c WHERE c.slug = 'cooling-hvac' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Cabin heating & cooling',    'cabin-heating-cooling',       20 FROM `epc_categories` c WHERE c.slug = 'cooling-hvac' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Blower & ducts',             'blower-ducts',                30 FROM `epc_categories` c WHERE c.slug = 'cooling-hvac' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'AC compressor & refrigerant lines', 'ac-compressor-lines',  40 FROM `epc_categories` c WHERE c.slug = 'cooling-hvac' AND c.is_active = 1 LIMIT 1;

-- --- 8. Exhaust & emissions -----------------------------------------
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Exhaust manifolds & headers', 'exhaust-manifolds-headers', 10 FROM `epc_categories` c WHERE c.slug = 'exhaust-emissions' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Exhaust pipes & silencers',  'exhaust-pipes-silencers',    20 FROM `epc_categories` c WHERE c.slug = 'exhaust-emissions' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Catalytic converter & DPF',  'catalytic-converter-dpf',    30 FROM `epc_categories` c WHERE c.slug = 'exhaust-emissions' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'EGR & emission valves',      'egr-emission-valves',        40 FROM `epc_categories` c WHERE c.slug = 'exhaust-emissions' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Oxygen & exhaust gas sensors', 'oxygen-exhaust-sensors',   50 FROM `epc_categories` c WHERE c.slug = 'exhaust-emissions' AND c.is_active = 1 LIMIT 1;

-- --- 9. Body (seed has Exterior) --------------------------------------
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Bumpers & grilles',          'bumpers-grilles',             20 FROM `epc_categories` c WHERE c.slug = 'body' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Glass & mirrors',            'glass-mirrors',               30 FROM `epc_categories` c WHERE c.slug = 'body' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Doors & tailgate',           'doors-tailgate',              40 FROM `epc_categories` c WHERE c.slug = 'body' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Roof & cab structure',     'roof-cab-structure',          50 FROM `epc_categories` c WHERE c.slug = 'body' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Panels & outer skin',        'panels-outer-skin',           60 FROM `epc_categories` c WHERE c.slug = 'body' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Trims, badges & mouldings',  'trims-badges-mouldings',      70 FROM `epc_categories` c WHERE c.slug = 'body' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Weatherstrips & seals',      'weatherstrips-seals',         80 FROM `epc_categories` c WHERE c.slug = 'body' AND c.is_active = 1 LIMIT 1;

-- --- 10. Interior -----------------------------------------------------
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Seats & belts',              'seats-belts',                 10 FROM `epc_categories` c WHERE c.slug = 'interior' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Dashboard & instruments',    'dashboard-instruments',      20 FROM `epc_categories` c WHERE c.slug = 'interior' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Steering wheel & airbag',    'steering-wheel-airbag',       30 FROM `epc_categories` c WHERE c.slug = 'interior' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Door cards & pillar trim',  'door-cards-pillar-trim',      40 FROM `epc_categories` c WHERE c.slug = 'interior' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Floor & carpets',          'floor-carpets',               50 FROM `epc_categories` c WHERE c.slug = 'interior' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Headlining & parcel shelf',  'headlining-parcel-shelf',     60 FROM `epc_categories` c WHERE c.slug = 'interior' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Console & storage',          'console-storage',             70 FROM `epc_categories` c WHERE c.slug = 'interior' AND c.is_active = 1 LIMIT 1;

-- --- 11. Wheels & tyres -----------------------------------------------
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Road wheels & caps',       'road-wheels-caps',            10 FROM `epc_categories` c WHERE c.slug = 'wheels-tyres' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Tyres',                     'tyres',                       20 FROM `epc_categories` c WHERE c.slug = 'wheels-tyres' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'TPMS & valves',            'tpms-valves',                 30 FROM `epc_categories` c WHERE c.slug = 'wheels-tyres' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Wheel fasteners & locks',   'wheel-fasteners-locks',       40 FROM `epc_categories` c WHERE c.slug = 'wheels-tyres' AND c.is_active = 1 LIMIT 1;

-- --- 12. Accessories & consumables -----------------------------------
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Wipers & washers',           'wipers-washers',              10 FROM `epc_categories` c WHERE c.slug = 'accessories-consumables' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Filters (oil, air, cabin, fuel)', 'filters-service',      20 FROM `epc_categories` c WHERE c.slug = 'accessories-consumables' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Auxiliary belts',            'auxiliary-belts',             30 FROM `epc_categories` c WHERE c.slug = 'accessories-consumables' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Fluids, caps & sundries',  'fluids-caps-sundries',        40 FROM `epc_categories` c WHERE c.slug = 'accessories-consumables' AND c.is_active = 1 LIMIT 1;
INSERT IGNORE INTO `epc_subcategories` (`category_id`, `name`, `slug`, `sort_order`)
SELECT c.id, 'Jack & emergency tools',     'jack-emergency-tools',        50 FROM `epc_categories` c WHERE c.slug = 'accessories-consumables' AND c.is_active = 1 LIMIT 1;

-- Expect ~74 new subcategory rows if Step 1 categories exist; fewer if a parent is missing.
-- Step 3 (later): Types → Subsystems → Components → Variants under each subcategory as needed.
