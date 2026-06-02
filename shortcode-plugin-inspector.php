<?php
/**
 * Plugin Name: Shortcode & Plugin Inspector
 * Description: Developer tool. Lists every registered shortcode, the plugin/theme that registered it, and the pages that actually use it (scans post content and Elementor data). Also lists active plugins and dead/unregistered shortcodes.
 * Version: 1.0
 * Author: Custom Development
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Shortcode_Plugin_Inspector {

    /** Post statuses worth scanning. */
    private $statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );

    /** Tokens that look like shortcodes but usually aren't, excluded from "dead" list. */
    private $stoplist = array( 'email', 'caption', 'embed' );

    /** Cache of get_plugins(). */
    private $all_plugins = null;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
    }

    public function menu() {
        add_management_page(
            'Shortcode & Plugin Inspector',
            'Shortcode Inspector',
            'manage_options',
            'shortcode-plugin-inspector',
            array( $this, 'render_page' )
        );
    }

    // =========================================================================
    // Page
    // =========================================================================

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        @set_time_limit( 0 );

        $registered = $this->registered_shortcodes();   // tag => source[]
        $scan       = $this->scan_usage( array_keys( $registered ) ); // [ 'used' => tag=>posts, 'dead' => tag=>posts ]

        $used  = $scan['used'];
        $dead  = $scan['dead'];

        $used_count = 0;
        foreach ( $used as $posts ) { if ( ! empty( $posts ) ) $used_count++; }

        $active = $this->active_plugins();
        ?>
        <div class="wrap">
            <h1>Shortcode &amp; Plugin Inspector</h1>
            <p class="description">Scans pages, posts and Elementor content on each load. For developer use — remove when finished.</p>

            <div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">
                <?php
                $this->stat_card( count( $registered ), 'Registered shortcodes' );
                $this->stat_card( $used_count, 'Shortcodes in use' );
                $this->stat_card( count( $dead ), 'Unregistered tags found' );
                $this->stat_card( count( $active ), 'Active plugins' );
                ?>
            </div>

            <h2>Shortcode output by plugin</h2>
            <p class="description">Each plugin or theme that registers shortcodes, and where its output appears on the front end. Plugins actually surfacing data are listed first.</p>
            <?php
            $groups = $this->group_by_source( $registered, $used );
            foreach ( $groups as $group ) {
                echo $this->render_group( $group );
            }
            if ( empty( $groups ) ) {
                echo '<p>No shortcodes are registered.</p>';
            }
            ?>

            <h2 style="margin-top:32px;">Registered shortcodes</h2>
            <p>
                <input type="text" id="spi-filter" placeholder="Filter shortcodes…"
                       style="padding:6px 10px;min-width:280px;" onkeyup="spiFilter()">
            </p>
            <table class="wp-list-table widefat fixed striped" id="spi-shortcodes">
                <thead>
                    <tr>
                        <th style="width:22%;">Shortcode</th>
                        <th style="width:33%;">Registered by</th>
                        <th style="width:45%;">Used on</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    ksort( $registered );
                    foreach ( $registered as $tag => $sources ) :
                        $posts = isset( $used[ $tag ] ) ? $used[ $tag ] : array();
                    ?>
                        <tr class="spi-row" data-tag="<?php echo esc_attr( $tag ); ?>">
                            <td><code>[<?php echo esc_html( $tag ); ?>]</code></td>
                            <td><?php echo $this->render_sources( $sources ); ?></td>
                            <td><?php echo $this->render_post_list( $posts ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ( empty( $registered ) ) : ?>
                        <tr><td colspan="3">No shortcodes are registered.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( ! empty( $dead ) ) : ?>
                <h2 style="margin-top:32px;">Unregistered shortcodes found in content</h2>
                <p class="description">These look like shortcodes but no active plugin or theme registers them — likely left over from a removed plugin, so they render as raw text on the front end.</p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr><th style="width:30%;">Tag</th><th style="width:70%;">Appears on</th></tr>
                    </thead>
                    <tbody>
                        <?php ksort( $dead ); foreach ( $dead as $tag => $posts ) : ?>
                            <tr>
                                <td><code>[<?php echo esc_html( $tag ); ?>]</code></td>
                                <td><?php echo $this->render_post_list( $posts ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 style="margin-top:32px;">Active plugins</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:30%;">Plugin</th>
                        <th style="width:12%;">Version</th>
                        <th style="width:43%;">File</th>
                        <th style="width:15%;">Scope</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $active as $a ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $a['name'] ); ?></strong></td>
                            <td><?php echo esc_html( $a['version'] ); ?></td>
                            <td><code><?php echo esc_html( $a['file'] ); ?></code></td>
                            <td><?php echo esc_html( $a['scope'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <script>
        function spiFilter() {
            var q = document.getElementById('spi-filter').value.toLowerCase();
            document.querySelectorAll('#spi-shortcodes .spi-row').forEach(function (row) {
                var tag = (row.getAttribute('data-tag') || '').toLowerCase();
                row.style.display = tag.indexOf(q) !== -1 ? '' : 'none';
            });
        }
        </script>
        <style>
            .spi-card{background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:14px 18px;min-width:150px;}
            .spi-card .num{font-size:26px;font-weight:600;line-height:1;}
            .spi-card .label{color:#646970;font-size:12px;margin-top:4px;}
            #spi-shortcodes details summary{cursor:pointer;}
            #spi-shortcodes details ul{margin:8px 0 0 0;}
            .spi-source{display:inline-block;margin:1px 0;}
            .spi-badge{display:inline-block;font-size:11px;padding:1px 6px;border-radius:3px;margin-right:6px;color:#fff;}
            .spi-badge.plugin{background:#2271b1;}
            .spi-badge.theme{background:#996800;}
            .spi-badge.mu{background:#3a7d44;}
            .spi-badge.core{background:#646970;}
            .spi-badge.unknown{background:#b32d2e;}
            .spi-group{background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:10px 14px;margin:8px 0;}
            .spi-group>summary{cursor:pointer;font-size:14px;padding:4px 0;}
            .spi-group details ul{margin:8px 0 0 0;}
        </style>
        <?php
    }

    private function stat_card( $num, $label ) {
        echo '<div class="spi-card"><div class="num">' . intval( $num ) . '</div><div class="label">' . esc_html( $label ) . '</div></div>';
    }

    // =========================================================================
    // Registered shortcodes + source resolution
    // =========================================================================

    private function registered_shortcodes() {
        global $shortcode_tags;
        $out = array();

        if ( empty( $shortcode_tags ) || ! is_array( $shortcode_tags ) ) return $out;

        foreach ( $shortcode_tags as $tag => $callback ) {
            $out[ $tag ] = $this->resolve_source( $callback );
        }
        return $out;
    }

    /**
     * Reflect on a shortcode callback to find the file that defines it,
     * then map that file to a plugin / theme / mu-plugin / core.
     *
     * @return array source descriptor: [ 'type', 'name' ]
     */
    private function resolve_source( $callback ) {
        $file = '';

        try {
            if ( $callback instanceof Closure ) {
                $ref  = new ReflectionFunction( $callback );
                $file = $ref->getFileName();
            } elseif ( is_string( $callback ) ) {
                if ( strpos( $callback, '::' ) !== false ) {
                    list( $class, $method ) = explode( '::', $callback, 2 );
                    $ref  = new ReflectionMethod( $class, $method );
                    $file = $ref->getFileName();
                } elseif ( function_exists( $callback ) ) {
                    $ref  = new ReflectionFunction( $callback );
                    $file = $ref->getFileName();
                }
            } elseif ( is_array( $callback ) && count( $callback ) === 2 ) {
                $obj_or_class = $callback[0];
                $method       = $callback[1];
                $ref          = new ReflectionMethod( is_object( $obj_or_class ) ? get_class( $obj_or_class ) : $obj_or_class, $method );
                $file         = $ref->getFileName();
            }
        } catch ( \Throwable $e ) {
            $file = '';
        }

        return $this->classify_file( $file );
    }

    private function classify_file( $file ) {
        if ( ! $file ) return array( 'type' => 'unknown', 'name' => 'Unknown' );

        $file = wp_normalize_path( $file );

        $plugin_dir = wp_normalize_path( WP_PLUGIN_DIR );
        $mu_dir     = defined( 'WPMU_PLUGIN_DIR' ) ? wp_normalize_path( WPMU_PLUGIN_DIR ) : '';
        $theme_root = wp_normalize_path( get_theme_root() );

        if ( strpos( $file, '/wp-includes/' ) !== false || strpos( $file, '/wp-admin/' ) !== false ) {
            return array( 'type' => 'core', 'name' => 'WordPress core' );
        }

        if ( $plugin_dir && strpos( $file, $plugin_dir ) === 0 ) {
            return array( 'type' => 'plugin', 'name' => $this->plugin_name_from_path( substr( $file, strlen( $plugin_dir ) + 1 ) ) );
        }

        if ( $mu_dir && strpos( $file, $mu_dir ) === 0 ) {
            return array( 'type' => 'mu', 'name' => basename( $file ) . ' (mu-plugin)' );
        }

        if ( $theme_root && strpos( $file, $theme_root ) === 0 ) {
            $rel     = ltrim( substr( $file, strlen( $theme_root ) ), '/' );
            $folder  = strtok( $rel, '/' );
            $theme   = wp_get_theme( $folder );
            $name    = ( $theme && $theme->exists() ) ? $theme->get( 'Name' ) : $folder;
            return array( 'type' => 'theme', 'name' => $name );
        }

        return array( 'type' => 'unknown', 'name' => basename( $file ) );
    }

    private function plugin_name_from_path( $rel ) {
        $rel    = ltrim( $rel, '/' );
        $folder = strtok( $rel, '/' );

        if ( null === $this->all_plugins ) {
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $this->all_plugins = get_plugins();
        }

        // Single-file plugin living directly in the plugins root.
        if ( $folder === $rel && isset( $this->all_plugins[ $rel ] ) ) {
            return $this->all_plugins[ $rel ]['Name'];
        }

        // Standard folder/plugin.php — match the first header in that folder.
        foreach ( $this->all_plugins as $key => $data ) {
            if ( strpos( $key, $folder . '/' ) === 0 ) {
                return $data['Name'];
            }
        }

        return $folder;
    }

    private function render_sources( $source ) {
        $type = $source['type'];
        $name = $source['name'];
        return '<span class="spi-source"><span class="spi-badge ' . esc_attr( $type ) . '">' . esc_html( ucfirst( $type ) ) . '</span>' . esc_html( $name ) . '</span>';
    }

    // =========================================================================
    // Grouping by owning plugin / theme
    // =========================================================================

    /**
     * Group registered shortcodes by the plugin/theme that registered them,
     * with the pages each shortcode is used on and a distinct page count.
     */
    private function group_by_source( $registered, $used ) {
        $groups = array();

        foreach ( $registered as $tag => $source ) {
            $key = $source['type'] . '|' . $source['name'];

            if ( ! isset( $groups[ $key ] ) ) {
                $groups[ $key ] = array(
                    'type' => $source['type'],
                    'name' => $source['name'],
                    'tags' => array(),
                );
            }

            $groups[ $key ]['tags'][ $tag ] = isset( $used[ $tag ] ) ? $used[ $tag ] : array();
        }

        // Per-group tallies: distinct pages and how many shortcodes are in use.
        foreach ( $groups as $key => $group ) {
            $page_ids  = array();
            $used_tags = 0;

            foreach ( $group['tags'] as $posts ) {
                if ( ! empty( $posts ) ) $used_tags++;
                foreach ( $posts as $p ) {
                    $page_ids[ $p['id'] ] = true;
                }
            }

            $groups[ $key ]['page_count'] = count( $page_ids );
            $groups[ $key ]['used_tags']  = $used_tags;
            ksort( $groups[ $key ]['tags'] );
        }

        // Plugins that actually surface data first, then alphabetical.
        uasort( $groups, function ( $a, $b ) {
            if ( $a['page_count'] === $b['page_count'] ) {
                return strcasecmp( $a['name'], $b['name'] );
            }
            return $b['page_count'] - $a['page_count'];
        } );

        return $groups;
    }

    private function render_group( $group ) {
        $tag_count  = count( $group['tags'] );
        $summary    = $this->render_sources( array( 'type' => $group['type'], 'name' => $group['name'] ) );
        $summary   .= ' — <strong>' . intval( $tag_count ) . '</strong> shortcode' . ( $tag_count === 1 ? '' : 's' );
        $summary   .= ', used on <strong>' . intval( $group['page_count'] ) . '</strong> page' . ( $group['page_count'] === 1 ? '' : 's' );

        $open = $group['page_count'] > 0 ? ' open' : '';

        $rows = '';
        foreach ( $group['tags'] as $tag => $posts ) {
            $rows .= '<tr>';
            $rows .= '<td style="width:30%;"><code>[' . esc_html( $tag ) . ']</code></td>';
            $rows .= '<td>' . $this->render_post_list( $posts ) . '</td>';
            $rows .= '</tr>';
        }

        $html  = '<details class="spi-group"' . $open . '>';
        $html .= '<summary>' . $summary . '</summary>';
        $html .= '<table class="wp-list-table widefat striped" style="margin:10px 0 18px;">';
        $html .= '<thead><tr><th>Shortcode</th><th>Used on</th></tr></thead>';
        $html .= '<tbody>' . $rows . '</tbody></table>';
        $html .= '</details>';

        return $html;
    }

    // =========================================================================
    // Usage scanning (content + Elementor data)
    // =========================================================================

    private function scan_usage( $registered_tags ) {
        global $wpdb;

        $registered_lookup = array_fill_keys( $registered_tags, true );
        $used = array();
        $dead = array();

        $types = array_values( get_post_types( array( 'public' => true ), 'names' ) );
        $types[] = 'wp_block';
        $types    = array_unique( array_diff( $types, array( 'attachment' ) ) );

        if ( empty( $types ) ) return array( 'used' => $used, 'dead' => $dead );

        $type_in   = "'" . implode( "','", array_map( 'esc_sql', $types ) ) . "'";
        $status_in = "'" . implode( "','", array_map( 'esc_sql', $this->statuses ) ) . "'";

        $rows = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_status, post_content
             FROM {$wpdb->posts}
             WHERE post_type IN ($type_in) AND post_status IN ($status_in)"
        );

        if ( empty( $rows ) ) return array( 'used' => $used, 'dead' => $dead );

        // Bulk-load Elementor data for the same posts.
        $ids = wp_list_pluck( $rows, 'ID' );
        $elementor = array();
        if ( ! empty( $ids ) ) {
            $id_in = implode( ',', array_map( 'intval', $ids ) );
            $meta_rows = $wpdb->get_results(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                 WHERE meta_key = '_elementor_data' AND post_id IN ($id_in)"
            );
            foreach ( $meta_rows as $m ) {
                $elementor[ (int) $m->post_id ] = $m->meta_value;
            }
        }

        foreach ( $rows as $row ) {
            $blob = (string) $row->post_content;
            if ( isset( $elementor[ (int) $row->ID ] ) ) {
                $blob .= "\n" . $elementor[ (int) $row->ID ];
            }

            $tags = $this->extract_tags( $blob );
            if ( empty( $tags ) ) continue;

            $ref = array(
                'id'     => (int) $row->ID,
                'title'  => $row->post_title,
                'type'   => $row->post_type,
                'status' => $row->post_status,
            );

            foreach ( $tags as $tag ) {
                if ( isset( $registered_lookup[ $tag ] ) ) {
                    $used[ $tag ][] = $ref;
                } elseif ( ! in_array( $tag, $this->stoplist, true ) && ! ctype_digit( $tag ) ) {
                    $dead[ $tag ][] = $ref;
                }
            }
        }

        return array( 'used' => $used, 'dead' => $dead );
    }

    /**
     * Pull shortcode-like tag names from a blob of content. Requires a closing
     * bracket so stray "[" characters are not treated as shortcodes.
     */
    private function extract_tags( $blob ) {
        if ( strpos( $blob, '[' ) === false ) return array();

        preg_match_all( '/\[\/?\s*([a-zA-Z0-9_\-]+)(?:[\s\/][^\]]*)?\]/', $blob, $matches );

        if ( empty( $matches[1] ) ) return array();
        return array_values( array_unique( $matches[1] ) );
    }

    private function render_post_list( $posts ) {
        if ( empty( $posts ) ) {
            return '<span style="color:#999;">—</span>';
        }

        // De-duplicate by ID.
        $seen = array();
        $items = '';
        foreach ( $posts as $p ) {
            if ( isset( $seen[ $p['id'] ] ) ) continue;
            $seen[ $p['id'] ] = true;

            $title    = $p['title'] !== '' ? $p['title'] : '(no title #' . $p['id'] . ')';
            $edit     = get_edit_post_link( $p['id'] );
            $view     = get_permalink( $p['id'] );
            $status   = $p['status'] !== 'publish' ? ' <em style="color:#999;">(' . esc_html( $p['status'] ) . ')</em>' : '';

            $items .= '<li>';
            $items .= '<strong>' . esc_html( $title ) . '</strong> ';
            $items .= '<span style="color:#999;">' . esc_html( $p['type'] ) . '</span>' . $status . ' — ';
            if ( $edit ) $items .= '<a href="' . esc_url( $edit ) . '">edit</a>';
            if ( $edit && $view ) $items .= ' | ';
            if ( $view ) $items .= '<a href="' . esc_url( $view ) . '" target="_blank" rel="noopener">view</a>';
            $items .= '</li>';
        }

        $count = count( $seen );
        return '<details><summary>' . intval( $count ) . ' ' . ( $count === 1 ? 'page' : 'pages' ) . '</summary><ul>' . $items . '</ul></details>';
    }

    // =========================================================================
    // Active plugins
    // =========================================================================

    private function active_plugins() {
        if ( null === $this->all_plugins ) {
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $this->all_plugins = get_plugins();
        }

        $out  = array();
        $seen = array();

        $site_active = (array) get_option( 'active_plugins', array() );
        foreach ( $site_active as $file ) {
            $out[] = $this->plugin_row( $file, 'Site' );
            $seen[ $file ] = true;
        }

        if ( is_multisite() ) {
            $network = (array) get_site_option( 'active_sitewide_plugins', array() );
            foreach ( array_keys( $network ) as $file ) {
                if ( isset( $seen[ $file ] ) ) continue;
                $out[] = $this->plugin_row( $file, 'Network' );
            }
        }

        usort( $out, function ( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );
        return $out;
    }

    private function plugin_row( $file, $scope ) {
        $data = isset( $this->all_plugins[ $file ] ) ? $this->all_plugins[ $file ] : array();
        return array(
            'name'    => ! empty( $data['Name'] ) ? $data['Name'] : $file,
            'version' => ! empty( $data['Version'] ) ? $data['Version'] : '—',
            'file'    => $file,
            'scope'   => $scope,
        );
    }
}

new Shortcode_Plugin_Inspector();
