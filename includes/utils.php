<?php

namespace DT\NbAddon\GroupsTaxonomy\Utils;

/**
 * Returns custom post types that are registered by other plugins and must be distributable.
 *
 * @return array
 */
function get_distributable_custom_post_types() {
	return array(
		'agreements',
		'ai_galleries',
		'application',
		'banners',
		'blocks',
		'compliance-rule',
		'documentation',
		'events',
		'faq',
		'features',
		'glossary',
		'help',
		'information',
		'newsletters',
		'page',
		'post',
		'presentations',
		'product',
		'resellers',
		'services',
		'shipping_option',
		'shipping_package',
		'shipping_validation',
		'task',
		'vacancies',
	);
}
