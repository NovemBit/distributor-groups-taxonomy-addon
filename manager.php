<?php
/**
 * Require add-on files and perform their initial setup
 *
 * @package distributor-taxonomy-groups
 */

/* Require plug-in files */
require_once __DIR__ . '/includes/groups-taxonomy-hooks.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/classes/ExternalConnectionGroups.php';

/* Call the setup functions */
\DT\NbAddon\GroupsTaxonomy\Hooks\setup();
