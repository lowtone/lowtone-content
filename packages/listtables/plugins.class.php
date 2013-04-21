<?php
namespace lowtone\content\packages\listtables;
use ErrorException,
	WP_Plugins_List_Table,
	lowtone\content\packages\Package;

/**
 * A replacement list table for the default list table with support for 
 * libraries and extra filters.
 * 
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2011-2012, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\content\packages\listtables
 */
class Plugins extends WP_Plugins_List_Table {

	function indexLibs() {
		global $plugins, $totals, $status;
				
		$plugins[Package::TYPE_LIB] = array();

		foreach ($plugins["all"] as $file => &$data) {
			if (Package::TYPE_LIB !== @$data["type"])
				continue;

			$plugins[Package::TYPE_LIB][$file] = $data;
		}

		$totals[Package::TYPE_LIB] = count($plugins[Package::TYPE_LIB]);

		// Remove libraries from inactive

		$plugins["inactive"] = array_diff_key($plugins["inactive"], $plugins[Package::TYPE_LIB]);
		$totals["inactive"] = count($plugins["inactive"]);
		
		if ("inactive" == $status)
			$this->setItems("inactive");

		return $this;
	}

	function setItems($status) {
		global $plugins, $totals, $orderby, $order, $page;

		if (empty($plugins[$status]) && !in_array($status, array('all', 'search')))
			$status = 'all';

		$this->items = array();

		foreach ($plugins[$status] as $plugin_file => $plugin_data) {
			// Translate, Don't Apply Markup, Sanitize HTML
			$this->items[$plugin_file] = _get_plugin_data_markup_translate($plugin_file, $plugin_data, false, true);
		}

		$total_this_page = $totals[$status];

		if ($orderby) {
			$orderby = ucfirst($orderby);
			$order = strtoupper($order);

			uasort($this->items, array(&$this, '_order_callback'));
		}

		$plugins_per_page = $this->get_items_per_page(str_replace('-', '_', $this->screen->id . '_per_page' ), 999);

		$start = ($page - 1) * $plugins_per_page;

		if ($total_this_page > $plugins_per_page)
			$this->items = array_slice($this->items, $start, $plugins_per_page);

		$this->set_pagination_args(array(
			'total_items' => $total_this_page,
			'per_page' => $plugins_per_page,
		));

		return $this;
	}

	function get_views() {
		global $totals, $status;

		$status_links = array();

		if (isset($_REQUEST["plugin_type"]))
			$status = $_REQUEST["plugin_type"];

		foreach ($totals as $type => $count) {
			if (!$count)
				continue;

			$arg = "plugin_status";

			switch ($type) {
				case "all":
					$text = _nx('All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $count, 'plugins');
					break;

				case "active":
					$text = _n('Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', $count);
					break;

				case "recently_activated":
					$text = _n('Recently Active <span class="count">(%s)</span>', 'Recently Active <span class="count">(%s)</span>', $count);
					break;

				case "inactive":
					$text = _n('Inactive <span class="count">(%s)</span>', 'Inactive <span class="count">(%s)</span>', $count);
					break;

				case "mustuse":
					$text = _n('Must-Use <span class="count">(%s)</span>', 'Must-Use <span class="count">(%s)</span>', $count);
					break;

				case "dropins":
					$text = _n('Drop-ins <span class="count">(%s)</span>', 'Drop-ins <span class="count">(%s)</span>', $count);
					break;

				case "upgrade":
					$text = _n('Update Available <span class="count">(%s)</span>', 'Update Available <span class="count">(%s)</span>', $count);
					break;

				case "lib":
					$text = __('Libraries <span class="count">(%s)</span>', "lowtone_content");
					$arg = "plugin_type";
					break;
			}

			if ("search" != $type ) {

				$status_links[$type] = sprintf("<a href='%s' %s>%s</a>",
						add_query_arg($arg, $type, "plugins.php"),
						($type == $status) ? ' class="current"' : '',
						sprintf($text, number_format_i18n($count))
					);

			}
		}

		return $status_links;
	}

	function single_row( $item ) {
		global $status, $page, $s, $totals;

		list( $plugin_file, $plugin_data ) = $item;
		$context = $status;
		$screen = $this->screen;

		// preorder
		$actions = array(
			'deactivate' => '',
			'activate' => '',
			'edit' => '',
			'delete' => '',
		);

		if ( 'mustuse' == $context ) {
			$is_active = true;
		} elseif ( 'dropins' == $context ) {
			$dropins = _get_dropins();
			$plugin_name = $plugin_file;
			if ( $plugin_file != $plugin_data['Name'] )
				$plugin_name .= '<br/>' . $plugin_data['Name'];
			if ( true === ( $dropins[ $plugin_file ][1] ) ) { // Doesn't require a constant
				$is_active = true;
				$description = '<p><strong>' . $dropins[ $plugin_file ][0] . '</strong></p>';
			} elseif ( constant( $dropins[ $plugin_file ][1] ) ) { // Constant is true
				$is_active = true;
				$description = '<p><strong>' . $dropins[ $plugin_file ][0] . '</strong></p>';
			} else {
				$is_active = false;
				$description = '<p><strong>' . $dropins[ $plugin_file ][0] . ' <span class="attention">' . __('Inactive:') . '</span></strong> ' . sprintf( __( 'Requires <code>%s</code> in <code>wp-config.php</code>.' ), "define('" . $dropins[ $plugin_file ][1] . "', true);" ) . '</p>';
			}
			if ( $plugin_data['Description'] )
				$description .= '<p>' . $plugin_data['Description'] . '</p>';
		} else {
			if ( $screen->is_network )
				$is_active = is_plugin_active_for_network( $plugin_file );
			else
				$is_active = is_plugin_active( $plugin_file );

			if ( $screen->is_network ) {
				if ( $is_active ) {
					if ( current_user_can( 'manage_network_plugins' ) )
						$actions['deactivate'] = '<a href="' . wp_nonce_url('plugins.php?action=deactivate&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'deactivate-plugin_' . $plugin_file) . '" title="' . esc_attr__('Deactivate this plugin') . '">' . __('Network Deactivate') . '</a>';
				} else {
					if ( current_user_can( 'manage_network_plugins' ) )
						$actions['activate'] = '<a href="' . wp_nonce_url('plugins.php?action=activate&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'activate-plugin_' . $plugin_file) . '" title="' . esc_attr__('Activate this plugin for all sites in this network') . '" class="edit">' . __('Network Activate') . '</a>';
					if ( current_user_can( 'delete_plugins' ) && ! is_plugin_active( $plugin_file ) )
						$actions['delete'] = '<a href="' . wp_nonce_url('plugins.php?action=delete-selected&amp;checked[]=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'bulk-plugins') . '" title="' . esc_attr__('Delete this plugin') . '" class="delete">' . __('Delete') . '</a>';
				}
			} else {
				if ( $is_active ) {
					$actions['deactivate'] = '<a href="' . wp_nonce_url('plugins.php?action=deactivate&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'deactivate-plugin_' . $plugin_file) . '" title="' . esc_attr__('Deactivate this plugin') . '">' . __('Deactivate') . '</a>';
				} else {
					$actions['activate'] = '<a href="' . wp_nonce_url('plugins.php?action=activate&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'activate-plugin_' . $plugin_file) . '" title="' . esc_attr__('Activate this plugin') . '" class="edit">' . __('Activate') . '</a>';

					if ( ! is_multisite() && current_user_can('delete_plugins') )
						$actions['delete'] = '<a href="' . wp_nonce_url('plugins.php?action=delete-selected&amp;checked[]=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'bulk-plugins') . '" title="' . esc_attr__('Delete this plugin') . '" class="delete">' . __('Delete') . '</a>';
				} // end if $is_active
			 } // end if $screen->is_network

			if ( ( ! is_multisite() || $screen->is_network ) && current_user_can('edit_plugins') && is_writable(WP_PLUGIN_DIR . '/' . $plugin_file) )
				$actions['edit'] = '<a href="plugin-editor.php?file=' . $plugin_file . '" title="' . esc_attr__('Open this file in the Plugin Editor') . '" class="edit">' . __('Edit') . '</a>';
		} // end if $context

		$prefix = $screen->is_network ? 'network_admin_' : '';
		$actions = apply_filters( $prefix . 'plugin_action_links', array_filter( $actions ), $plugin_file, $plugin_data, $context );
		$actions = apply_filters( $prefix . "plugin_action_links_$plugin_file", $actions, $plugin_file, $plugin_data, $context );

		$class = $is_active ? 'active' : 'inactive';
		$checkbox_id =  "checkbox_" . md5($plugin_data['Name']);
		if ( in_array( $status, array( 'mustuse', 'dropins' ) ) ) {
			$checkbox = '';
		} else {
			$checkbox = "<label class='screen-reader-text' for='" . $checkbox_id . "' >" . sprintf( __( 'Select %s' ), $plugin_data['Name'] ) . "</label>"
				. "<input type='checkbox' name='checked[]' value='" . esc_attr( $plugin_file ) . "' id='" . $checkbox_id . "' />";
		}
		if ( 'dropins' != $context ) {
			$description = '<p>' . ( $plugin_data['Description'] ? $plugin_data['Description'] : '&nbsp;' ) . '</p>';
			$plugin_name = $plugin_data['Name'];
		}

		$id = sanitize_title( $plugin_name );
		if ( ! empty( $totals['upgrade'] ) && ! empty( $plugin_data['update'] ) )
			$class .= ' update';

		$class = apply_filters("list_table_plugins_row_class", $class, $plugin_data);

		echo "<tr id='$id' class='$class'>";

		list( $columns, $hidden ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			switch ( $column_name ) {
				case 'cb':
					echo "<th scope='row' class='check-column'>$checkbox</th>";
					break;
				case 'name':
					echo "<td class='plugin-title'$style><strong>$plugin_name</strong>";
					echo $this->row_actions( $actions, true );
					echo "</td>";
					break;
				case 'description':
					echo "<td class='column-description desc'$style>
						<div class='plugin-description'>$description</div>
						<div class='$class second plugin-version-author-uri'>";

					$plugin_meta = array();
					if ( !empty( $plugin_data['Version'] ) )
						$plugin_meta[] = sprintf( __( 'Version %s' ), $plugin_data['Version'] );
					if ( !empty( $plugin_data['Author'] ) ) {
						$author = $plugin_data['Author'];
						if ( !empty( $plugin_data['AuthorURI'] ) )
							$author = '<a href="' . $plugin_data['AuthorURI'] . '" title="' . esc_attr__( 'Visit author homepage' ) . '">' . $plugin_data['Author'] . '</a>';
						$plugin_meta[] = sprintf( __( 'By %s' ), $author );
					}
					if ( ! empty( $plugin_data['PluginURI'] ) )
						$plugin_meta[] = '<a href="' . $plugin_data['PluginURI'] . '" title="' . esc_attr__( 'Visit plugin site' ) . '">' . __( 'Visit plugin site' ) . '</a>';

					$plugin_meta = apply_filters( 'plugin_row_meta', $plugin_meta, $plugin_file, $plugin_data, $status );
					echo implode( ' | ', $plugin_meta );

					echo "</div></td>";
					break;
				default:
					echo "<td class='$column_name column-$column_name'$style>";
					do_action( 'manage_plugins_custom_column', $column_name, $plugin_file, $plugin_data );
					echo "</td>";
			}
		}

		echo "</tr>";

		do_action( 'after_plugin_row', $plugin_file, $plugin_data, $status );
		do_action( "after_plugin_row_$plugin_file", $plugin_file, $plugin_data, $status );
	}

	// Static

	public static function __switch() {
		if (!isset($GLOBALS["wp_list_table"]))
			throw new ErrorException("Can't switch if \$wp_list_table isn't set");

		$old = $GLOBALS["wp_list_table"];

		// Create new from old

		$new = new static();

		foreach ($old as $name => $value)
			$new->{$name} = $value;

		// Index libs

		$new->indexLibs();

		// Set items

		if (isset($_REQUEST["plugin_type"]))
			$new->setItems($_REQUEST["plugin_type"]);

		// Replace

		$GLOBALS["wp_list_table"] = $new;

		return $old;
	}

}